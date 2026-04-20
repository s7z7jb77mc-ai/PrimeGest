use serde::{Deserialize, Serialize};
use sqlx::{Pool, Sqlite, SqlitePool};
use tauri::{AppHandle, Manager};
use tauri_plugin_sql::{Migration, MigrationKind};

pub struct AppDb(pub Pool<Sqlite>);

#[derive(Debug, Serialize, Deserialize)]
pub struct SyncStatus {
    pub pending_count: i64,
    pub last_sync_ts:  i64,
    pub is_online:     bool,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct PushResult {
    pub synced:    i64,
    pub conflicts: i64,
    pub errors:    i64,
}

fn now_ts() -> i64 {
    std::time::SystemTime::now()
        .duration_since(std::time::UNIX_EPOCH)
        .unwrap_or_default()
        .as_secs() as i64
}

async fn get_db(app: &AppHandle) -> Result<Pool<Sqlite>, String> {
    let state = app.state::<AppDb>();
    Ok(state.0.clone())
}

#[tauri::command]
async fn queue_operation(
    app:        AppHandle,
    table_name: String,
    record_id:  String,
    operation:  String,
    payload:    serde_json::Value,
) -> Result<i64, String> {
    let db = get_db(&app).await?;
    let payload_str = serde_json::to_string(&payload).map_err(|e| e.to_string())?;

    let result = sqlx::query(
        "INSERT INTO sync_queue (table_name, record_id, operation, payload, status, created_at)
         VALUES (?, ?, ?, ?, 'pending', ?)"
    )
    .bind(&table_name)
    .bind(&record_id)
    .bind(&operation)
    .bind(&payload_str)
    .bind(now_ts())
    .execute(&db)
    .await
    .map_err(|e| e.to_string())?;

    Ok(result.last_insert_rowid())
}

#[tauri::command]
async fn get_sync_status(app: AppHandle) -> Result<SyncStatus, String> {
    let db = get_db(&app).await?;

    let row: (i64, i64) = sqlx::query_as(
        "SELECT COUNT(*), COALESCE(MAX(synced_at), 0)
         FROM sync_queue WHERE status = 'pending'"
    )
    .fetch_one(&db)
    .await
    .map_err(|e| e.to_string())?;

    Ok(SyncStatus {
        pending_count: row.0,
        last_sync_ts:  row.1,
        is_online:     false,
    })
}

#[tauri::command]
async fn sync_push(
    app:       AppHandle,
    api_url:   String,
    api_token: String,
    device_id: String,
) -> Result<PushResult, String> {
    let db = get_db(&app).await?;

    let rows: Vec<(i64, String, String, String, String, i64)> = sqlx::query_as(
        "SELECT id, table_name, record_id, operation, payload, 0
         FROM sync_queue WHERE status = 'pending'
         ORDER BY created_at LIMIT 50"
    )
    .fetch_all(&db)
    .await
    .map_err(|e| e.to_string())?;

    if rows.is_empty() {
        return Ok(PushResult { synced: 0, conflicts: 0, errors: 0 });
    }

    let ids: Vec<i64> = rows.iter().map(|r| r.0).collect();

    for id in &ids {
        sqlx::query("UPDATE sync_queue SET status = 'syncing' WHERE id = ?")
            .bind(id)
            .execute(&db)
            .await
            .map_err(|e| e.to_string())?;
    }

    let operations: Vec<serde_json::Value> = rows.iter().map(|r| {
        let payload: serde_json::Value = serde_json::from_str(&r.4)
            .unwrap_or(serde_json::json!({}));
        serde_json::json!({
            "record_id":           r.2,
            "operation":           r.3,
            "payload":             payload,
            "client_sync_version": r.5,
        })
    }).collect();

    let body = serde_json::json!({
        "device_id":  device_id,
        "operations": operations,
    });

    let client = reqwest::Client::new();
    let response = client
        .post(format!("{}/api/sync/push", api_url))
        .header("Authorization", format!("Bearer {}", api_token))
        .header("Accept", "application/json")
        .json(&body)
        .timeout(std::time::Duration::from_secs(30))
        .send()
        .await;

    match response {
        Err(e) => {
            for id in &ids {
                sqlx::query(
                    "UPDATE sync_queue SET status = 'pending', retry_count = retry_count + 1
                     WHERE id = ?"
                )
                .bind(id)
                .execute(&db)
                .await
                .ok();
            }
            Err(format!("Réseau indisponible: {}", e))
        }
        Ok(resp) => {
            let ts = now_ts();
            let json: serde_json::Value = resp.json().await
                .map_err(|e| e.to_string())?;
            let results = json["results"].as_array().cloned().unwrap_or_default();

            let mut synced = 0i64;
            let mut conflicts = 0i64;
            let mut errors = 0i64;

            for result in &results {
                let record_id  = result["record_id"].as_str().unwrap_or("");
                let status     = result["status"].as_str().unwrap_or("error");
                let new_status = match status {
                    "synced"   => { synced += 1;    "synced" }
                    "conflict" => { conflicts += 1; "conflict" }
                    _          => { errors += 1;    "error" }
                };
                sqlx::query(
                    "UPDATE sync_queue SET status = ?, synced_at = ?
                     WHERE record_id = ? AND status = 'syncing'"
                )
                .bind(new_status)
                .bind(ts)
                .bind(record_id)
                .execute(&db)
                .await
                .ok();
            }

            Ok(PushResult { synced, conflicts, errors })
        }
    }
}

#[tauri::command]
async fn sync_pull(
    app:          AppHandle,
    api_url:      String,
    api_token:    String,
    device_id:    String,
    last_sync_ts: i64,
) -> Result<i64, String> {
    let db = get_db(&app).await?;

    let client = reqwest::Client::new();
    let response = client
        .get(format!("{}/api/sync/pull", api_url))
        .header("Authorization", format!("Bearer {}", api_token))
        .header("Accept", "application/json")
        .query(&[
            ("since",     last_sync_ts.to_string()),
            ("device_id", device_id),
        ])
        .timeout(std::time::Duration::from_secs(30))
        .send()
        .await
        .map_err(|e| format!("Réseau indisponible: {}", e))?;

    let json: serde_json::Value = response.json().await
        .map_err(|e| e.to_string())?;

    let delta = json["delta"].as_array().cloned().unwrap_or_default();
    let count = delta.len() as i64;

    for record in &delta {
        let id           = record["id"].as_str().unwrap_or("");
        let name         = record["name"].as_str().unwrap_or("");
        let address      = record["address"].as_str().unwrap_or("");
        let phone        = record["phone"].as_str().unwrap_or("");
        let email        = record["email"].as_str().unwrap_or("");
        let sync_version = record["sync_version"].as_i64().unwrap_or(0);
        let updated_at   = record["updated_at_ts"].as_i64().unwrap_or(0);
        let deleted_at   = record["deleted_at"].as_i64();

        sqlx::query(
            "INSERT INTO entreprises_local
                (id, name, address, phone, email, sync_version, updated_at, deleted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON CONFLICT(id) DO UPDATE SET
                name=excluded.name,
                address=excluded.address,
                phone=excluded.phone,
                email=excluded.email,
                sync_version=excluded.sync_version,
                updated_at=excluded.updated_at,
                deleted_at=excluded.deleted_at"
        )
        .bind(id).bind(name).bind(address).bind(phone)
        .bind(email).bind(sync_version).bind(updated_at).bind(deleted_at)
        .execute(&db)
        .await
        .map_err(|e| e.to_string())?;
    }

    Ok(count)
}

const MIGRATIONS: &str = "
    CREATE TABLE IF NOT EXISTS sync_queue (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        table_name  TEXT NOT NULL,
        record_id   TEXT NOT NULL,
        operation   TEXT NOT NULL CHECK(operation IN ('create','update','delete')),
        payload     TEXT NOT NULL,
        status      TEXT NOT NULL DEFAULT 'pending'
                         CHECK(status IN ('pending','syncing','synced','conflict','error')),
        retry_count INTEGER NOT NULL DEFAULT 0,
        created_at  INTEGER NOT NULL,
        synced_at   INTEGER
    );
    CREATE INDEX IF NOT EXISTS idx_sync_queue_status
        ON sync_queue(status, created_at);
    CREATE TABLE IF NOT EXISTS entreprises_local (
        id           TEXT PRIMARY KEY,
        name         TEXT NOT NULL,
        address      TEXT,
        phone        TEXT,
        email        TEXT,
        sync_version INTEGER NOT NULL DEFAULT 0,
        updated_at   INTEGER NOT NULL,
        deleted_at   INTEGER
    );
";

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    let migrations_plugin = vec![
        Migration {
            version: 1,
            description: "create_tables",
            sql: MIGRATIONS,
            kind: MigrationKind::Up,
        },
    ];

    tauri::Builder::default()
        .plugin(tauri_plugin_shell::init())
        .plugin(
            tauri_plugin_sql::Builder::default()
                .add_migrations("sqlite:primegest.db", migrations_plugin)
                .build(),
        )
        .setup(|app| {
            let app_dir = app.path().app_config_dir()
                .expect("Pas de dossier config app");
            std::fs::create_dir_all(&app_dir).ok();
            let db_path = format!(
                "sqlite:{}/primegest.db",
                app_dir.to_str().unwrap()
            );
            let pool = tauri::async_runtime::block_on(
                SqlitePool::connect(&db_path)
            ).expect("Impossible d'ouvrir SQLite");

            tauri::async_runtime::block_on(
                sqlx::query(MIGRATIONS).execute(&pool)
            ).ok();

            app.manage(AppDb(pool));
            Ok(())
        })
        .invoke_handler(tauri::generate_handler![
            queue_operation,
            get_sync_status,
            sync_push,
            sync_pull,
        ])
        .run(tauri::generate_context!())
        .expect("Erreur au démarrage de Tauri");
}

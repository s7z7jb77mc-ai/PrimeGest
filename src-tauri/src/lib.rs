use serde::{Deserialize, Serialize};
use sqlx::{Pool, Sqlite, SqlitePool};
use tauri::{AppHandle, Manager};
use tauri_plugin_sql::{Migration, MigrationKind};

pub struct AppDb(pub Pool<Sqlite>);

#[derive(Debug, Serialize, Deserialize)]
pub struct SyncStatus {
    pub pending_count: i64,
    pub last_sync_ts: i64,
    pub is_online: bool,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct PushResult {
    pub synced: i64,
    pub conflicts: i64,
    pub errors: i64,
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

// ── Helpers ──────────────────────────────────────────────────────────────────

fn str_val(v: &serde_json::Value) -> Option<&str> {
    v.as_str()
}
fn f64_val(v: &serde_json::Value) -> f64 {
    v.as_f64().unwrap_or(0.0)
}

// ── Helpers écriture locale ───────────────────────────────────────────────────

async fn get_local_sync_version(db: &Pool<Sqlite>, entity: &str, uuid: &str) -> i64 {
    let table = match entity {
        "parametres"       => "parametres_local",
        "produits"         => "produits_local",
        "clients"          => "clients_local",
        "fournisseurs"     => "fournisseurs_local",
        "factures"         => "factures_local",
        "mouvement_stocks" => "mouvement_stocks_local",
        "journals"         => "journals_local",
        "employes"         => "employes_local",
        "succursales"      => "succursales_local",
        "caisses"          => "caisses_local",
        "transferts"       => "transferts_local",
        "bon_entrees"      => "bon_entrees_local",
        _ => return 0,
    };
    let sql = format!("SELECT COALESCE(sync_version, 0) FROM {} WHERE uuid = ?", table);
    let row: Option<(i64,)> = sqlx::query_as(&sql)
        .bind(uuid)
        .fetch_optional(db)
        .await
        .unwrap_or(None);
    row.map(|(v,)| v).unwrap_or(0)
}

async fn local_soft_delete(db: &Pool<Sqlite>, entity: &str, uuid: &str) -> bool {
    let table = match entity {
        "parametres"       => "parametres_local",
        "produits"         => "produits_local",
        "clients"          => "clients_local",
        "fournisseurs"     => "fournisseurs_local",
        "factures"         => "factures_local",
        "mouvement_stocks" => "mouvement_stocks_local",
        "journals"         => "journals_local",
        "employes"         => "employes_local",
        "succursales"      => "succursales_local",
        "caisses"          => "caisses_local",
        "transferts"       => "transferts_local",
        "bon_entrees"      => "bon_entrees_local",
        _ => return false,
    };
    let sql = format!("UPDATE {} SET deleted_at = ? WHERE uuid = ?", table);
    sqlx::query(&sql).bind(now_ts()).bind(uuid).execute(db).await.is_ok()
}

/// Écrit dans la table locale SANS vérification de sync_version (local-first).
/// Contrairement à upsert_entity (utilisé pour les pulls serveur), cette fonction
/// écrase toujours — les changements locaux doivent être visibles immédiatement.
async fn optimistic_write_entity(
    db: &Pool<Sqlite>,
    entity: &str,
    uuid: &str,
    sync_version: i64,
    p: &serde_json::Value,
) -> bool {
    let ts = now_ts();
    match entity {
        "produits" => {
            let json = serde_json::json!({
                "uuid": uuid, "sync_version": sync_version, "updated_at": ts, "deleted_at": null,
                "nom_produit": p["nom_produit"], "prix_vente": p["prix_vente"],
                "prix_achat": p["prix_achat"], "quantite": p["quantite"],
                "categorie": p["categorie"],
            });
            sqlx::query(
                "INSERT INTO produits_local
                    (uuid, nom_produit, prix_vente, prix_achat, quantite, categorie,
                     sync_version, updated_at, deleted_at, json_data)
                 VALUES (?,?,?,?,?,?,?,?,NULL,?)
                 ON CONFLICT(uuid) DO UPDATE SET
                    nom_produit=excluded.nom_produit, prix_vente=excluded.prix_vente,
                    prix_achat=excluded.prix_achat, quantite=excluded.quantite,
                    categorie=excluded.categorie, updated_at=excluded.updated_at,
                    json_data=excluded.json_data",
            )
            .bind(uuid).bind(str_val(&p["nom_produit"]))
            .bind(f64_val(&p["prix_vente"])).bind(f64_val(&p["prix_achat"]))
            .bind(f64_val(&p["quantite"])).bind(str_val(&p["categorie"]))
            .bind(sync_version).bind(ts).bind(json.to_string())
            .execute(db).await.is_ok()
        }
        "clients" => {
            let json = serde_json::json!({
                "uuid": uuid, "sync_version": sync_version, "updated_at": ts, "deleted_at": null,
                "nom_client": p["nom_client"], "numero_telephone": p["numero_telephone"],
                "adresse": p["adresse"],
            });
            sqlx::query(
                "INSERT INTO clients_local
                    (uuid, nom_client, numero_telephone, adresse,
                     sync_version, updated_at, deleted_at, json_data)
                 VALUES (?,?,?,?,?,?,NULL,?)
                 ON CONFLICT(uuid) DO UPDATE SET
                    nom_client=excluded.nom_client, numero_telephone=excluded.numero_telephone,
                    adresse=excluded.adresse, updated_at=excluded.updated_at,
                    json_data=excluded.json_data",
            )
            .bind(uuid).bind(str_val(&p["nom_client"]))
            .bind(str_val(&p["numero_telephone"])).bind(str_val(&p["adresse"]))
            .bind(sync_version).bind(ts).bind(json.to_string())
            .execute(db).await.is_ok()
        }
        "fournisseurs" => {
            let json = serde_json::json!({
                "uuid": uuid, "sync_version": sync_version, "updated_at": ts, "deleted_at": null,
                "nom_entreprise_fournisseur": p["nom_entreprise_fournisseur"],
                "adresse": p["adresse"], "reduction_pourcentage": p["reduction_pourcentage"],
            });
            sqlx::query(
                "INSERT INTO fournisseurs_local
                    (uuid, nom_entreprise_fournisseur, adresse, reduction_pourcentage,
                     sync_version, updated_at, deleted_at, json_data)
                 VALUES (?,?,?,?,?,?,NULL,?)
                 ON CONFLICT(uuid) DO UPDATE SET
                    nom_entreprise_fournisseur=excluded.nom_entreprise_fournisseur,
                    adresse=excluded.adresse, reduction_pourcentage=excluded.reduction_pourcentage,
                    updated_at=excluded.updated_at, json_data=excluded.json_data",
            )
            .bind(uuid).bind(str_val(&p["nom_entreprise_fournisseur"]))
            .bind(str_val(&p["adresse"])).bind(f64_val(&p["reduction_pourcentage"]))
            .bind(sync_version).bind(ts).bind(json.to_string())
            .execute(db).await.is_ok()
        }
        "mouvement_stocks" => {
            let json = serde_json::json!({
                "uuid": uuid, "sync_version": sync_version, "updated_at": ts, "deleted_at": null,
                "type": p["type"], "quantite": p["quantite"],
                "prix_unitaire": p["prix_unitaire"], "prix_total": p["prix_total"],
                "commentaire": p["commentaire"], "produit_id": p["produit_id"],
            });
            sqlx::query(
                "INSERT INTO mouvement_stocks_local
                    (uuid, type_mvt, quantite, prix_unitaire, prix_total, commentaire, produit_id,
                     sync_version, updated_at, deleted_at, json_data)
                 VALUES (?,?,?,?,?,?,?,?,?,NULL,?)
                 ON CONFLICT(uuid) DO UPDATE SET
                    type_mvt=excluded.type_mvt, quantite=excluded.quantite,
                    prix_unitaire=excluded.prix_unitaire, prix_total=excluded.prix_total,
                    commentaire=excluded.commentaire, produit_id=excluded.produit_id,
                    updated_at=excluded.updated_at, json_data=excluded.json_data",
            )
            .bind(uuid).bind(str_val(&p["type"]))
            .bind(f64_val(&p["quantite"])).bind(p["prix_unitaire"].as_f64())
            .bind(p["prix_total"].as_f64()).bind(str_val(&p["commentaire"]))
            .bind(p["produit_id"].as_i64())
            .bind(sync_version).bind(ts).bind(json.to_string())
            .execute(db).await.is_ok()
        }
        "journals" => {
            let json = serde_json::json!({
                "uuid": uuid, "sync_version": sync_version, "updated_at": ts, "deleted_at": null,
                "type": p["type"], "description": p["description"],
                "montant": p["montant"], "dateHeure_operation": p["dateHeure_operation"],
                "produit_id": p["produit_id"],
            });
            sqlx::query(
                "INSERT INTO journals_local
                    (uuid, type_journal, description, montant, date_heure_operation, produit_id,
                     sync_version, updated_at, deleted_at, json_data)
                 VALUES (?,?,?,?,?,?,?,?,NULL,?)
                 ON CONFLICT(uuid) DO UPDATE SET
                    type_journal=excluded.type_journal, description=excluded.description,
                    montant=excluded.montant, date_heure_operation=excluded.date_heure_operation,
                    produit_id=excluded.produit_id, updated_at=excluded.updated_at,
                    json_data=excluded.json_data",
            )
            .bind(uuid).bind(str_val(&p["type"]))
            .bind(str_val(&p["description"])).bind(f64_val(&p["montant"]))
            .bind(str_val(&p["dateHeure_operation"])).bind(p["produit_id"].as_i64())
            .bind(sync_version).bind(ts).bind(json.to_string())
            .execute(db).await.is_ok()
        }
        "employes" => {
            let nom = str_val(&p["nom"]).unwrap_or("");
            let prenom = str_val(&p["prenom"]).unwrap_or("");
            let search_text = format!("{} {}", nom, prenom);
            let json = serde_json::json!({
                "uuid": uuid, "sync_version": sync_version, "updated_at": ts, "deleted_at": null,
                "nom": p["nom"], "prenom": p["prenom"], "poste": p["poste"],
                "salaire_base": p["salaire_base"], "telephone": p["telephone"],
                "email": p["email"], "date_embauche": p["date_embauche"], "statut": p["statut"],
            });
            sqlx::query(
                "INSERT INTO employes_local
                    (uuid, nom, prenom, poste, salaire_base, telephone, email,
                     date_embauche, statut, search_text,
                     sync_version, updated_at, deleted_at, json_data)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NULL,?)
                 ON CONFLICT(uuid) DO UPDATE SET
                    nom=excluded.nom, prenom=excluded.prenom, poste=excluded.poste,
                    salaire_base=excluded.salaire_base, telephone=excluded.telephone,
                    email=excluded.email, date_embauche=excluded.date_embauche,
                    statut=excluded.statut, search_text=excluded.search_text,
                    updated_at=excluded.updated_at, json_data=excluded.json_data",
            )
            .bind(uuid).bind(str_val(&p["nom"])).bind(str_val(&p["prenom"]))
            .bind(str_val(&p["poste"])).bind(f64_val(&p["salaire_base"]))
            .bind(str_val(&p["telephone"])).bind(str_val(&p["email"]))
            .bind(str_val(&p["date_embauche"]))
            .bind(str_val(&p["statut"]).unwrap_or("actif"))
            .bind(search_text).bind(sync_version).bind(ts).bind(json.to_string())
            .execute(db).await.is_ok()
        }
        "caisses" => {
            let json = serde_json::json!({
                "uuid": uuid, "sync_version": sync_version, "updated_at": ts, "deleted_at": null,
                "date_operation": p["date_operation"], "description": p["description"],
                "entree": p["entree"], "sortie": p["sortie"], "solde": p["solde"],
                "type_operation": p["type_operation"], "succursale_id": p["succursale_id"],
            });
            sqlx::query(
                "INSERT INTO caisses_local
                    (uuid, date_operation, description, entree, sortie, solde, type_operation,
                     succursale_id, sync_version, updated_at, deleted_at, json_data)
                 VALUES (?,?,?,?,?,?,?,?,?,?,NULL,?)
                 ON CONFLICT(uuid) DO UPDATE SET
                    date_operation=excluded.date_operation, description=excluded.description,
                    entree=excluded.entree, sortie=excluded.sortie, solde=excluded.solde,
                    type_operation=excluded.type_operation, succursale_id=excluded.succursale_id,
                    updated_at=excluded.updated_at, json_data=excluded.json_data",
            )
            .bind(uuid).bind(str_val(&p["date_operation"])).bind(str_val(&p["description"]))
            .bind(f64_val(&p["entree"])).bind(f64_val(&p["sortie"]))
            .bind(p["solde"].as_f64()).bind(str_val(&p["type_operation"]))
            .bind(p["succursale_id"].as_i64())
            .bind(sync_version).bind(ts).bind(json.to_string())
            .execute(db).await.is_ok()
        }
        "succursales" => {
            let json = serde_json::json!({
                "uuid": uuid, "sync_version": sync_version, "updated_at": ts, "deleted_at": null,
                "nom": p["nom"], "adresse": p["adresse"],
                "manager_user_id": p["manager_user_id"], "active": p["active"],
            });
            sqlx::query(
                "INSERT INTO succursales_local
                    (uuid, nom, adresse, manager_user_id, active,
                     sync_version, updated_at, deleted_at, json_data)
                 VALUES (?,?,?,?,?,?,?,NULL,?)
                 ON CONFLICT(uuid) DO UPDATE SET
                    nom=excluded.nom, adresse=excluded.adresse,
                    manager_user_id=excluded.manager_user_id, active=excluded.active,
                    updated_at=excluded.updated_at, json_data=excluded.json_data",
            )
            .bind(uuid).bind(str_val(&p["nom"])).bind(str_val(&p["adresse"]))
            .bind(p["manager_user_id"].as_i64())
            .bind(p["active"].as_bool().map(|b| b as i64).unwrap_or(1))
            .bind(sync_version).bind(ts).bind(json.to_string())
            .execute(db).await.is_ok()
        }
        "parametres" => {
            let json = serde_json::json!({
                "uuid": uuid, "sync_version": sync_version, "updated_at": ts, "deleted_at": null,
                "nom_entreprise": p["nom_entreprise"], "adresse": p["adresse"],
                "email": p["email"], "telephone": p["telephone"],
                "devise": p["devise"], "langue": p["langue"],
                "tva": p["tva"], "reduction_accordee": p["reduction_accordee"],
                "theme": p["theme"], "multi_succursales": p["multi_succursales"],
                "seuil_alerte": p["seuil_alerte"], "logo_path": p["logo_path"],
            });
            sqlx::query(
                "INSERT INTO parametres_local
                    (uuid, nom_entreprise, adresse, email, telephone, devise, langue,
                     tva, reduction_accordee, theme, multi_succursales, seuil_alerte, logo_path,
                     sync_version, updated_at, deleted_at, json_data)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL,?)
                 ON CONFLICT(uuid) DO UPDATE SET
                    nom_entreprise=excluded.nom_entreprise, adresse=excluded.adresse,
                    email=excluded.email, telephone=excluded.telephone,
                    devise=excluded.devise, langue=excluded.langue,
                    tva=excluded.tva, reduction_accordee=excluded.reduction_accordee,
                    theme=excluded.theme, multi_succursales=excluded.multi_succursales,
                    seuil_alerte=excluded.seuil_alerte, logo_path=excluded.logo_path,
                    updated_at=excluded.updated_at, json_data=excluded.json_data",
            )
            .bind(uuid).bind(str_val(&p["nom_entreprise"])).bind(str_val(&p["adresse"]))
            .bind(str_val(&p["email"])).bind(str_val(&p["telephone"]))
            .bind(str_val(&p["devise"])).bind(str_val(&p["langue"]))
            .bind(f64_val(&p["tva"])).bind(f64_val(&p["reduction_accordee"]))
            .bind(str_val(&p["theme"]))
            .bind(p["multi_succursales"].as_bool().map(|b| b as i64).unwrap_or(0))
            .bind(p["seuil_alerte"].as_f64()).bind(str_val(&p["logo_path"]))
            .bind(sync_version).bind(ts).bind(json.to_string())
            .execute(db).await.is_ok()
        }
        _ => false,
    }
}

// ── queue_operation ───────────────────────────────────────────────────────────

#[tauri::command]
async fn queue_operation(
    app: AppHandle,
    table_name: String,
    record_id: String,
    operation: String,
    payload: serde_json::Value,
) -> Result<i64, String> {
    let db = get_db(&app).await?;
    let payload_str = serde_json::to_string(&payload).map_err(|e| e.to_string())?;

    // Lire le sync_version actuel pour éviter les faux conflits côté serveur
    let current_sync_version = get_local_sync_version(&db, &table_name, &record_id).await;

    let result = sqlx::query(
        "INSERT INTO sync_queue
            (table_name, record_id, operation, payload, status, created_at, client_sync_version)
         VALUES (?, ?, ?, ?, 'pending', ?, ?)",
    )
    .bind(&table_name)
    .bind(&record_id)
    .bind(&operation)
    .bind(&payload_str)
    .bind(now_ts())
    .bind(current_sync_version)
    .execute(&db)
    .await
    .map_err(|e| e.to_string())?;

    // Écriture optimiste dans la table locale → UI reflète immédiatement le changement
    if operation == "delete" {
        let _ = local_soft_delete(&db, &table_name, &record_id).await;
    } else {
        let _ = optimistic_write_entity(&db, &table_name, &record_id, current_sync_version, &payload).await;
    }

    Ok(result.last_insert_rowid())
}

// ── get_sync_status ───────────────────────────────────────────────────────────

#[tauri::command]
async fn get_sync_status(app: AppHandle) -> Result<SyncStatus, String> {
    let db = get_db(&app).await?;

    let row: (i64, i64) = sqlx::query_as(
        "SELECT COUNT(*), COALESCE(MAX(synced_at), 0)
         FROM sync_queue WHERE status = 'pending'",
    )
    .fetch_one(&db)
    .await
    .map_err(|e| e.to_string())?;

    Ok(SyncStatus {
        pending_count: row.0,
        last_sync_ts: row.1,
        is_online: false,
    })
}

// ── sync_push ─────────────────────────────────────────────────────────────────

#[tauri::command]
async fn sync_push(
    app: AppHandle,
    api_url: String,
    api_token: String,
    device_id: String,
) -> Result<PushResult, String> {
    let db = get_db(&app).await?;

    let rows: Vec<(i64, String, String, String, String, i64)> = sqlx::query_as(
        "SELECT id, table_name, record_id, operation, payload, client_sync_version
         FROM sync_queue WHERE status = 'pending'
         ORDER BY created_at LIMIT 50",
    )
    .fetch_all(&db)
    .await
    .map_err(|e| e.to_string())?;

    if rows.is_empty() {
        return Ok(PushResult {
            synced: 0,
            conflicts: 0,
            errors: 0,
        });
    }

    let ids: Vec<i64> = rows.iter().map(|r| r.0).collect();

    for id in &ids {
        sqlx::query("UPDATE sync_queue SET status = 'syncing' WHERE id = ?")
            .bind(id)
            .execute(&db)
            .await
            .map_err(|e| e.to_string())?;
    }

    let operations: Vec<serde_json::Value> = rows
        .iter()
        .map(|r| {
            let payload: serde_json::Value =
                serde_json::from_str(&r.4).unwrap_or(serde_json::json!({}));
            serde_json::json!({
                "table_name":          r.1,
                "record_id":           r.2,
                "operation":           r.3,
                "payload":             payload,
                "client_sync_version": r.5,
            })
        })
        .collect();

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
                     WHERE id = ?",
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
            let json: serde_json::Value = resp.json().await.map_err(|e| e.to_string())?;
            let results = json["results"].as_array().cloned().unwrap_or_default();

            let mut synced = 0i64;
            let mut conflicts = 0i64;
            let mut errors = 0i64;

            for result in &results {
                let record_id = result["record_id"].as_str().unwrap_or("");
                let status = result["status"].as_str().unwrap_or("error");
                let new_status = match status {
                    "synced" => {
                        synced += 1;
                        "synced"
                    }
                    "conflict" => {
                        conflicts += 1;
                        "conflict"
                    }
                    _ => {
                        errors += 1;
                        "error"
                    }
                };
                sqlx::query(
                    "UPDATE sync_queue SET status = ?, synced_at = ?
                     WHERE record_id = ? AND status = 'syncing'",
                )
                .bind(new_status)
                .bind(ts)
                .bind(record_id)
                .execute(&db)
                .await
                .ok();
            }

            Ok(PushResult {
                synced,
                conflicts,
                errors,
            })
        }
    }
}

// ── sync_pull ─────────────────────────────────────────────────────────────────
// Le serveur retourne { delta: { produits: [...], clients: [...], ... }, server_ts }
// Chaque record : { uuid, sync_version, updated_at_ts, deleted_at, payload: {...} }

#[tauri::command]
async fn sync_pull(
    app: AppHandle,
    api_url: String,
    api_token: String,
    device_id: String,
    last_sync_ts: i64,
) -> Result<i64, String> {
    let db = get_db(&app).await?;

    let client = reqwest::Client::new();
    let response = client
        .get(format!("{}/api/sync/pull", api_url))
        .header("Authorization", format!("Bearer {}", api_token))
        .header("Accept", "application/json")
        .query(&[
            ("since", last_sync_ts.to_string()),
            ("device_id", device_id),
        ])
        .timeout(std::time::Duration::from_secs(30))
        .send()
        .await
        .map_err(|e| format!("Réseau indisponible: {}", e))?;

    let json: serde_json::Value = response.json().await.map_err(|e| e.to_string())?;

    let delta = match json["delta"].as_object() {
        Some(obj) => obj.clone(),
        None => return Ok(0),
    };

    let mut count = 0i64;

    for (entity, records) in &delta {
        let records_arr = match records.as_array() {
            Some(a) => a,
            None => continue,
        };
        for record in records_arr {
            let uuid = record["uuid"].as_str().unwrap_or("");
            if uuid.is_empty() {
                continue;
            }
            let sync_version = record["sync_version"].as_i64().unwrap_or(0);
            let updated_at = record["updated_at_ts"].as_i64().unwrap_or(0);
            let deleted_at = record["deleted_at"].as_i64();
            let p = &record["payload"];

            let ok = upsert_entity(&db, entity, uuid, sync_version, updated_at, deleted_at, p).await;
            if ok {
                count += 1;
            }
        }

        // Mémoriser le timestamp du dernier pull par entité
        sqlx::query(
            "INSERT INTO sync_meta (entity, last_pull_ts) VALUES (?, ?)
             ON CONFLICT(entity) DO UPDATE SET last_pull_ts = excluded.last_pull_ts",
        )
        .bind(entity)
        .bind(now_ts())
        .execute(&db)
        .await
        .ok();
    }

    Ok(count)
}

/// Upsert un record dans la table locale correspondant à son entité.
/// Retourne true si l'upsert a réussi, false si entité inconnue ou erreur.
async fn upsert_entity(
    db: &Pool<Sqlite>,
    entity: &str,
    uuid: &str,
    sync_version: i64,
    updated_at: i64,
    deleted_at: Option<i64>,
    p: &serde_json::Value,
) -> bool {
    let result = match entity {
        "produits" => {
            let json = serde_json::json!({
                "uuid": uuid, "sync_version": sync_version,
                "updated_at": updated_at, "deleted_at": deleted_at,
                "nom_produit": p["nom_produit"], "prix_vente": p["prix_vente"],
                "prix_achat": p["prix_achat"], "quantite": p["quantite"],
                "categorie": p["categorie"],
            });
            sqlx::query(
                "INSERT INTO produits_local
                    (uuid, nom_produit, prix_vente, prix_achat, quantite, categorie,
                     sync_version, updated_at, deleted_at, json_data)
                 VALUES (?,?,?,?,?,?,?,?,?,?)
                 ON CONFLICT(uuid) DO UPDATE SET
                    nom_produit=excluded.nom_produit, prix_vente=excluded.prix_vente,
                    prix_achat=excluded.prix_achat, quantite=excluded.quantite,
                    categorie=excluded.categorie, sync_version=excluded.sync_version,
                    updated_at=excluded.updated_at, deleted_at=excluded.deleted_at,
                    json_data=excluded.json_data
                 WHERE excluded.sync_version >= produits_local.sync_version",
            )
            .bind(uuid)
            .bind(str_val(&p["nom_produit"]))
            .bind(f64_val(&p["prix_vente"]))
            .bind(f64_val(&p["prix_achat"]))
            .bind(f64_val(&p["quantite"]))
            .bind(str_val(&p["categorie"]))
            .bind(sync_version)
            .bind(updated_at)
            .bind(deleted_at)
            .bind(json.to_string())
            .execute(db)
            .await
        }

        "clients" => {
            let json = serde_json::json!({
                "uuid": uuid, "sync_version": sync_version,
                "updated_at": updated_at, "deleted_at": deleted_at,
                "nom_client": p["nom_client"], "numero_telephone": p["numero_telephone"],
                "adresse": p["adresse"],
            });
            sqlx::query(
                "INSERT INTO clients_local
                    (uuid, nom_client, numero_telephone, adresse,
                     sync_version, updated_at, deleted_at, json_data)
                 VALUES (?,?,?,?,?,?,?,?)
                 ON CONFLICT(uuid) DO UPDATE SET
                    nom_client=excluded.nom_client,
                    numero_telephone=excluded.numero_telephone,
                    adresse=excluded.adresse, sync_version=excluded.sync_version,
                    updated_at=excluded.updated_at, deleted_at=excluded.deleted_at,
                    json_data=excluded.json_data
                 WHERE excluded.sync_version >= clients_local.sync_version",
            )
            .bind(uuid)
            .bind(str_val(&p["nom_client"]))
            .bind(str_val(&p["numero_telephone"]))
            .bind(str_val(&p["adresse"]))
            .bind(sync_version)
            .bind(updated_at)
            .bind(deleted_at)
            .bind(json.to_string())
            .execute(db)
            .await
        }

        "fournisseurs" => {
            let json = serde_json::json!({
                "uuid": uuid, "sync_version": sync_version,
                "updated_at": updated_at, "deleted_at": deleted_at,
                "nom_entreprise_fournisseur": p["nom_entreprise_fournisseur"],
                "adresse": p["adresse"],
                "reduction_pourcentage": p["reduction_pourcentage"],
            });
            sqlx::query(
                "INSERT INTO fournisseurs_local
                    (uuid, nom_entreprise_fournisseur, adresse, reduction_pourcentage,
                     sync_version, updated_at, deleted_at, json_data)
                 VALUES (?,?,?,?,?,?,?,?)
                 ON CONFLICT(uuid) DO UPDATE SET
                    nom_entreprise_fournisseur=excluded.nom_entreprise_fournisseur,
                    adresse=excluded.adresse,
                    reduction_pourcentage=excluded.reduction_pourcentage,
                    sync_version=excluded.sync_version,
                    updated_at=excluded.updated_at, deleted_at=excluded.deleted_at,
                    json_data=excluded.json_data
                 WHERE excluded.sync_version >= fournisseurs_local.sync_version",
            )
            .bind(uuid)
            .bind(str_val(&p["nom_entreprise_fournisseur"]))
            .bind(str_val(&p["adresse"]))
            .bind(f64_val(&p["reduction_pourcentage"]))
            .bind(sync_version)
            .bind(updated_at)
            .bind(deleted_at)
            .bind(json.to_string())
            .execute(db)
            .await
        }

        "factures" => {
            let json = serde_json::json!({
                "uuid": uuid, "sync_version": sync_version,
                "updated_at": updated_at, "deleted_at": deleted_at,
                "total_ht": p["total_ht"], "total_tva": p["total_tva"],
                "total_ttc": p["total_ttc"], "montant_paye": p["montant_paye"],
                "statut": p["statut"], "client_id": p["client_id"],
            });
            sqlx::query(
                "INSERT INTO factures_local
                    (uuid, total_ht, total_tva, total_ttc, montant_paye, statut, client_id,
                     sync_version, updated_at, deleted_at, json_data)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)
                 ON CONFLICT(uuid) DO UPDATE SET
                    total_ht=excluded.total_ht, total_tva=excluded.total_tva,
                    total_ttc=excluded.total_ttc, montant_paye=excluded.montant_paye,
                    statut=excluded.statut, client_id=excluded.client_id,
                    sync_version=excluded.sync_version,
                    updated_at=excluded.updated_at, deleted_at=excluded.deleted_at,
                    json_data=excluded.json_data
                 WHERE excluded.sync_version >= factures_local.sync_version",
            )
            .bind(uuid)
            .bind(f64_val(&p["total_ht"]))
            .bind(f64_val(&p["total_tva"]))
            .bind(f64_val(&p["total_ttc"]))
            .bind(f64_val(&p["montant_paye"]))
            .bind(str_val(&p["statut"]).unwrap_or("en_attente"))
            .bind(p["client_id"].as_i64())
            .bind(sync_version)
            .bind(updated_at)
            .bind(deleted_at)
            .bind(json.to_string())
            .execute(db)
            .await
        }

        "mouvement_stocks" => {
            let json = serde_json::json!({
                "uuid": uuid, "sync_version": sync_version,
                "updated_at": updated_at, "deleted_at": deleted_at,
                "type": p["type"], "quantite": p["quantite"],
                "prix_unitaire": p["prix_unitaire"], "prix_total": p["prix_total"],
                "commentaire": p["commentaire"], "produit_id": p["produit_id"],
            });
            sqlx::query(
                "INSERT INTO mouvement_stocks_local
                    (uuid, type_mvt, quantite, prix_unitaire, prix_total, commentaire, produit_id,
                     sync_version, updated_at, deleted_at, json_data)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)
                 ON CONFLICT(uuid) DO UPDATE SET
                    type_mvt=excluded.type_mvt, quantite=excluded.quantite,
                    prix_unitaire=excluded.prix_unitaire, prix_total=excluded.prix_total,
                    commentaire=excluded.commentaire, produit_id=excluded.produit_id,
                    sync_version=excluded.sync_version,
                    updated_at=excluded.updated_at, deleted_at=excluded.deleted_at,
                    json_data=excluded.json_data
                 WHERE excluded.sync_version >= mouvement_stocks_local.sync_version",
            )
            .bind(uuid)
            .bind(str_val(&p["type"]))
            .bind(f64_val(&p["quantite"]))
            .bind(p["prix_unitaire"].as_f64())
            .bind(p["prix_total"].as_f64())
            .bind(str_val(&p["commentaire"]))
            .bind(p["produit_id"].as_i64())
            .bind(sync_version)
            .bind(updated_at)
            .bind(deleted_at)
            .bind(json.to_string())
            .execute(db)
            .await
        }

        "journals" => {
            let json = serde_json::json!({
                "uuid": uuid, "sync_version": sync_version,
                "updated_at": updated_at, "deleted_at": deleted_at,
                "type": p["type"], "description": p["description"],
                "montant": p["montant"], "dateHeure_operation": p["dateHeure_operation"],
                "produit_id": p["produit_id"],
            });
            sqlx::query(
                "INSERT INTO journals_local
                    (uuid, type_journal, description, montant, date_heure_operation, produit_id,
                     sync_version, updated_at, deleted_at, json_data)
                 VALUES (?,?,?,?,?,?,?,?,?,?)
                 ON CONFLICT(uuid) DO UPDATE SET
                    type_journal=excluded.type_journal, description=excluded.description,
                    montant=excluded.montant,
                    date_heure_operation=excluded.date_heure_operation,
                    produit_id=excluded.produit_id,
                    sync_version=excluded.sync_version,
                    updated_at=excluded.updated_at, deleted_at=excluded.deleted_at,
                    json_data=excluded.json_data
                 WHERE excluded.sync_version >= journals_local.sync_version",
            )
            .bind(uuid)
            .bind(str_val(&p["type"]))
            .bind(str_val(&p["description"]))
            .bind(f64_val(&p["montant"]))
            .bind(str_val(&p["dateHeure_operation"]))
            .bind(p["produit_id"].as_i64())
            .bind(sync_version)
            .bind(updated_at)
            .bind(deleted_at)
            .bind(json.to_string())
            .execute(db)
            .await
        }

        "employes" => {
            let nom = str_val(&p["nom"]).unwrap_or("");
            let prenom = str_val(&p["prenom"]).unwrap_or("");
            let search_text = format!("{} {}", nom, prenom);
            let json = serde_json::json!({
                "uuid": uuid, "sync_version": sync_version,
                "updated_at": updated_at, "deleted_at": deleted_at,
                "nom": p["nom"], "prenom": p["prenom"], "poste": p["poste"],
                "salaire_base": p["salaire_base"], "telephone": p["telephone"],
                "email": p["email"], "date_embauche": p["date_embauche"],
                "statut": p["statut"],
            });
            sqlx::query(
                "INSERT INTO employes_local
                    (uuid, nom, prenom, poste, salaire_base, telephone, email,
                     date_embauche, statut, search_text,
                     sync_version, updated_at, deleted_at, json_data)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                 ON CONFLICT(uuid) DO UPDATE SET
                    nom=excluded.nom, prenom=excluded.prenom, poste=excluded.poste,
                    salaire_base=excluded.salaire_base, telephone=excluded.telephone,
                    email=excluded.email, date_embauche=excluded.date_embauche,
                    statut=excluded.statut, search_text=excluded.search_text,
                    sync_version=excluded.sync_version,
                    updated_at=excluded.updated_at, deleted_at=excluded.deleted_at,
                    json_data=excluded.json_data
                 WHERE excluded.sync_version >= employes_local.sync_version",
            )
            .bind(uuid)
            .bind(str_val(&p["nom"]))
            .bind(str_val(&p["prenom"]))
            .bind(str_val(&p["poste"]))
            .bind(f64_val(&p["salaire_base"]))
            .bind(str_val(&p["telephone"]))
            .bind(str_val(&p["email"]))
            .bind(str_val(&p["date_embauche"]))
            .bind(str_val(&p["statut"]).unwrap_or("actif"))
            .bind(search_text)
            .bind(sync_version)
            .bind(updated_at)
            .bind(deleted_at)
            .bind(json.to_string())
            .execute(db)
            .await
        }

        "transferts" => {
            let json = serde_json::json!({
                "uuid": uuid, "sync_version": sync_version,
                "updated_at": updated_at, "deleted_at": deleted_at,
                "produit_id": p["produit_id"], "quantite": p["quantite"],
                "statut": p["statut"],
                "from_succursale_id": p["from_succursale_id"],
                "to_succursale_id": p["to_succursale_id"],
            });
            sqlx::query(
                "INSERT INTO transferts_local
                    (uuid, produit_id, quantite, statut,
                     from_succursale_id, to_succursale_id,
                     sync_version, updated_at, deleted_at, json_data)
                 VALUES (?,?,?,?,?,?,?,?,?,?)
                 ON CONFLICT(uuid) DO UPDATE SET
                    produit_id=excluded.produit_id, quantite=excluded.quantite,
                    statut=excluded.statut,
                    from_succursale_id=excluded.from_succursale_id,
                    to_succursale_id=excluded.to_succursale_id,
                    sync_version=excluded.sync_version,
                    updated_at=excluded.updated_at, deleted_at=excluded.deleted_at,
                    json_data=excluded.json_data
                 WHERE excluded.sync_version >= transferts_local.sync_version",
            )
            .bind(uuid)
            .bind(p["produit_id"].as_i64())
            .bind(f64_val(&p["quantite"]))
            .bind(str_val(&p["statut"]))
            .bind(p["from_succursale_id"].as_i64())
            .bind(p["to_succursale_id"].as_i64())
            .bind(sync_version)
            .bind(updated_at)
            .bind(deleted_at)
            .bind(json.to_string())
            .execute(db)
            .await
        }

        "bon_entrees" => {
            let json = serde_json::json!({
                "uuid": uuid, "sync_version": sync_version,
                "updated_at": updated_at, "deleted_at": deleted_at,
                "fournisseur_id": p["fournisseur_id"], "total_ht": p["total_ht"],
                "statut": p["statut"], "payment_type": p["payment_type"],
            });
            sqlx::query(
                "INSERT INTO bon_entrees_local
                    (uuid, fournisseur_id, total_ht, statut, payment_type,
                     sync_version, updated_at, deleted_at, json_data)
                 VALUES (?,?,?,?,?,?,?,?,?)
                 ON CONFLICT(uuid) DO UPDATE SET
                    fournisseur_id=excluded.fournisseur_id, total_ht=excluded.total_ht,
                    statut=excluded.statut, payment_type=excluded.payment_type,
                    sync_version=excluded.sync_version,
                    updated_at=excluded.updated_at, deleted_at=excluded.deleted_at,
                    json_data=excluded.json_data
                 WHERE excluded.sync_version >= bon_entrees_local.sync_version",
            )
            .bind(uuid)
            .bind(p["fournisseur_id"].as_i64())
            .bind(f64_val(&p["total_ht"]))
            .bind(str_val(&p["statut"]))
            .bind(str_val(&p["payment_type"]))
            .bind(sync_version)
            .bind(updated_at)
            .bind(deleted_at)
            .bind(json.to_string())
            .execute(db)
            .await
        }

        "caisses" => {
            let json = serde_json::json!({
                "uuid": uuid, "sync_version": sync_version,
                "updated_at": updated_at, "deleted_at": deleted_at,
                "date_operation": p["date_operation"], "description": p["description"],
                "entree": p["entree"], "sortie": p["sortie"], "solde": p["solde"],
                "type_operation": p["type_operation"], "succursale_id": p["succursale_id"],
            });
            sqlx::query(
                "INSERT INTO caisses_local
                    (uuid, date_operation, description, entree, sortie, solde,
                     type_operation, succursale_id, sync_version, updated_at, deleted_at, json_data)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                 ON CONFLICT(uuid) DO UPDATE SET
                    date_operation=excluded.date_operation, description=excluded.description,
                    entree=excluded.entree, sortie=excluded.sortie, solde=excluded.solde,
                    type_operation=excluded.type_operation, succursale_id=excluded.succursale_id,
                    sync_version=excluded.sync_version,
                    updated_at=excluded.updated_at, deleted_at=excluded.deleted_at,
                    json_data=excluded.json_data
                 WHERE excluded.sync_version >= caisses_local.sync_version",
            )
            .bind(uuid).bind(str_val(&p["date_operation"])).bind(str_val(&p["description"]))
            .bind(f64_val(&p["entree"])).bind(f64_val(&p["sortie"]))
            .bind(p["solde"].as_f64()).bind(str_val(&p["type_operation"]))
            .bind(p["succursale_id"].as_i64())
            .bind(sync_version).bind(updated_at).bind(deleted_at).bind(json.to_string())
            .execute(db).await
        }

        "succursales" => {
            let json = serde_json::json!({
                "uuid": uuid, "sync_version": sync_version,
                "updated_at": updated_at, "deleted_at": deleted_at,
                "nom": p["nom"], "adresse": p["adresse"],
                "manager_user_id": p["manager_user_id"], "active": p["active"],
            });
            sqlx::query(
                "INSERT INTO succursales_local
                    (uuid, nom, adresse, manager_user_id, active,
                     sync_version, updated_at, deleted_at, json_data)
                 VALUES (?,?,?,?,?,?,?,?,?)
                 ON CONFLICT(uuid) DO UPDATE SET
                    nom=excluded.nom, adresse=excluded.adresse,
                    manager_user_id=excluded.manager_user_id, active=excluded.active,
                    sync_version=excluded.sync_version,
                    updated_at=excluded.updated_at, deleted_at=excluded.deleted_at,
                    json_data=excluded.json_data
                 WHERE excluded.sync_version >= succursales_local.sync_version",
            )
            .bind(uuid).bind(str_val(&p["nom"])).bind(str_val(&p["adresse"]))
            .bind(p["manager_user_id"].as_i64())
            .bind(p["active"].as_bool().map(|b| b as i64).unwrap_or(1))
            .bind(sync_version).bind(updated_at).bind(deleted_at).bind(json.to_string())
            .execute(db).await
        }

        "parametres" => {
            let json = serde_json::json!({
                "uuid": uuid, "sync_version": sync_version,
                "updated_at": updated_at, "deleted_at": deleted_at,
                "nom_entreprise": p["nom_entreprise"], "adresse": p["adresse"],
                "email": p["email"], "telephone": p["telephone"],
                "devise": p["devise"], "langue": p["langue"],
                "tva": p["tva"], "reduction_accordee": p["reduction_accordee"],
                "theme": p["theme"], "multi_succursales": p["multi_succursales"],
                "seuil_alerte": p["seuil_alerte"], "logo_path": p["logo_path"],
            });
            sqlx::query(
                "INSERT INTO parametres_local
                    (uuid, nom_entreprise, adresse, email, telephone, devise, langue,
                     tva, reduction_accordee, theme, multi_succursales, seuil_alerte, logo_path,
                     sync_version, updated_at, deleted_at, json_data)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                 ON CONFLICT(uuid) DO UPDATE SET
                    nom_entreprise=excluded.nom_entreprise, adresse=excluded.adresse,
                    email=excluded.email, telephone=excluded.telephone,
                    devise=excluded.devise, langue=excluded.langue,
                    tva=excluded.tva, reduction_accordee=excluded.reduction_accordee,
                    theme=excluded.theme, multi_succursales=excluded.multi_succursales,
                    seuil_alerte=excluded.seuil_alerte, logo_path=excluded.logo_path,
                    sync_version=excluded.sync_version,
                    updated_at=excluded.updated_at, deleted_at=excluded.deleted_at,
                    json_data=excluded.json_data
                 WHERE excluded.sync_version >= parametres_local.sync_version",
            )
            .bind(uuid).bind(str_val(&p["nom_entreprise"])).bind(str_val(&p["adresse"]))
            .bind(str_val(&p["email"])).bind(str_val(&p["telephone"]))
            .bind(str_val(&p["devise"])).bind(str_val(&p["langue"]))
            .bind(f64_val(&p["tva"])).bind(f64_val(&p["reduction_accordee"]))
            .bind(str_val(&p["theme"]))
            .bind(p["multi_succursales"].as_bool().map(|b| b as i64).unwrap_or(0))
            .bind(p["seuil_alerte"].as_f64()).bind(str_val(&p["logo_path"]))
            .bind(sync_version).bind(updated_at).bind(deleted_at).bind(json.to_string())
            .execute(db).await
        }

        _ => return false, // entité inconnue → ignorer
    };

    result.is_ok()
}

// ── query_local ───────────────────────────────────────────────────────────────
// Lecture des données locales depuis les pages Vue.
// Retourne un tableau JSON (Vec<String> de json_data).

#[tauri::command]
async fn query_local(
    app: AppHandle,
    entity: String,
    search: Option<String>,
    limit: Option<i64>,
    offset: Option<i64>,
    include_deleted: Option<bool>,
) -> Result<Vec<String>, String> {
    let db = get_db(&app).await?;
    let limit = limit.unwrap_or(50).min(500);
    let offset = offset.unwrap_or(0);
    let include_deleted = include_deleted.unwrap_or(false);

    // Résout (table, colonne de recherche) depuis le nom d'entité
    let (table, search_col) = match entity.as_str() {
        "produits"        => ("produits_local", "nom_produit"),
        "clients"         => ("clients_local", "nom_client"),
        "fournisseurs"    => ("fournisseurs_local", "nom_entreprise_fournisseur"),
        "factures"        => ("factures_local", "statut"),
        "mouvement_stocks" => ("mouvement_stocks_local", "commentaire"),
        "journals"        => ("journals_local", "description"),
        "employes"        => ("employes_local", "search_text"),
        "transferts"      => ("transferts_local", "statut"),
        "bon_entrees"     => ("bon_entrees_local", "statut"),
        "caisses"         => ("caisses_local", "description"),
        "succursales"     => ("succursales_local", "nom"),
        "parametres"      => ("parametres_local", "nom_entreprise"),
        _ => return Err(format!("Entité inconnue: {}", entity)),
    };

    let deleted_filter = if include_deleted { "" } else { "AND deleted_at IS NULL" };

    let rows: Vec<(String,)> = if let Some(s) = search {
        let sql = format!(
            "SELECT json_data FROM {} WHERE {} LIKE ? {} ORDER BY updated_at DESC LIMIT ? OFFSET ?",
            table, search_col, deleted_filter
        );
        sqlx::query_as(&sql)
            .bind(format!("%{}%", s))
            .bind(limit)
            .bind(offset)
            .fetch_all(&db)
            .await
    } else {
        let sql = format!(
            "SELECT json_data FROM {} WHERE 1=1 {} ORDER BY updated_at DESC LIMIT ? OFFSET ?",
            table, deleted_filter
        );
        sqlx::query_as(&sql)
            .bind(limit)
            .bind(offset)
            .fetch_all(&db)
            .await
    }
    .map_err(|e| e.to_string())?;

    Ok(rows.into_iter().map(|(j,)| j).collect())
}

// ── query_local_aggregate ─────────────────────────────────────────────────────
// Agrégats pour le dashboard offline : totaux, sommes, comptes.

#[tauri::command]
async fn query_local_aggregate(
    app: AppHandle,
    entity: String,
    aggregate: String, // "count" | "sum:colonne" | "sum_by_type" (journals/mvt_stocks)
) -> Result<serde_json::Value, String> {
    let db = get_db(&app).await?;

    let result = match (entity.as_str(), aggregate.as_str()) {
        ("produits", "count") => {
            let (n,): (i64,) = sqlx::query_as(
                "SELECT COUNT(*) FROM produits_local WHERE deleted_at IS NULL",
            )
            .fetch_one(&db)
            .await
            .map_err(|e| e.to_string())?;
            serde_json::json!({ "count": n })
        }

        ("produits", "valeur_stock") => {
            let (val,): (f64,) = sqlx::query_as(
                "SELECT COALESCE(SUM(quantite * prix_achat), 0) FROM produits_local WHERE deleted_at IS NULL",
            )
            .fetch_one(&db)
            .await
            .map_err(|e| e.to_string())?;
            serde_json::json!({ "valeur_stock": val })
        }

        ("factures", "totaux") => {
            let row: (f64, f64, i64) = sqlx::query_as(
                "SELECT COALESCE(SUM(total_ttc),0), COALESCE(SUM(montant_paye),0), COUNT(*)
                 FROM factures_local WHERE deleted_at IS NULL",
            )
            .fetch_one(&db)
            .await
            .map_err(|e| e.to_string())?;
            serde_json::json!({ "total_ttc": row.0, "montant_paye": row.1, "count": row.2 })
        }

        ("journals", "sum_by_type") => {
            let rows: Vec<(String, f64)> = sqlx::query_as(
                "SELECT type_journal, COALESCE(SUM(montant), 0)
                 FROM journals_local WHERE deleted_at IS NULL GROUP BY type_journal",
            )
            .fetch_all(&db)
            .await
            .map_err(|e| e.to_string())?;
            let map: serde_json::Map<String, serde_json::Value> = rows
                .into_iter()
                .map(|(t, s)| (t, serde_json::json!(s)))
                .collect();
            serde_json::Value::Object(map)
        }

        ("mouvement_stocks", "sum_by_type") => {
            let rows: Vec<(String, f64)> = sqlx::query_as(
                "SELECT type_mvt, COALESCE(SUM(prix_total), 0)
                 FROM mouvement_stocks_local WHERE deleted_at IS NULL GROUP BY type_mvt",
            )
            .fetch_all(&db)
            .await
            .map_err(|e| e.to_string())?;
            let map: serde_json::Map<String, serde_json::Value> = rows
                .into_iter()
                .map(|(t, s)| (t, serde_json::json!(s)))
                .collect();
            serde_json::Value::Object(map)
        }

        ("clients", "count") => {
            let (n,): (i64,) =
                sqlx::query_as("SELECT COUNT(*) FROM clients_local WHERE deleted_at IS NULL")
                    .fetch_one(&db)
                    .await
                    .map_err(|e| e.to_string())?;
            serde_json::json!({ "count": n })
        }

        ("employes", "count") => {
            let (n,): (i64,) = sqlx::query_as(
                "SELECT COUNT(*) FROM employes_local WHERE deleted_at IS NULL AND statut = 'actif'",
            )
            .fetch_one(&db)
            .await
            .map_err(|e| e.to_string())?;
            serde_json::json!({ "count": n })
        }

        _ => return Err(format!("Agrégat inconnu: {} / {}", entity, aggregate)),
    };

    Ok(result)
}

// ── MIGRATIONS ────────────────────────────────────────────────────────────────

const MIGRATIONS: &str = "
    -- File d'attente des mutations offline → serveur
    CREATE TABLE IF NOT EXISTS sync_queue (
        id                  INTEGER PRIMARY KEY AUTOINCREMENT,
        table_name          TEXT NOT NULL,
        record_id           TEXT NOT NULL,
        operation           TEXT NOT NULL CHECK(operation IN ('create','update','delete')),
        payload             TEXT NOT NULL,
        status              TEXT NOT NULL DEFAULT 'pending'
                                 CHECK(status IN ('pending','syncing','synced','conflict','error')),
        retry_count         INTEGER NOT NULL DEFAULT 0,
        created_at          INTEGER NOT NULL,
        synced_at           INTEGER,
        client_sync_version INTEGER NOT NULL DEFAULT 0
    );
    CREATE INDEX IF NOT EXISTS idx_sync_queue_status ON sync_queue(status, created_at);

    -- Métadonnées de synchronisation par entité
    CREATE TABLE IF NOT EXISTS sync_meta (
        entity       TEXT PRIMARY KEY,
        last_pull_ts INTEGER NOT NULL DEFAULT 0
    );

    -- Produits
    CREATE TABLE IF NOT EXISTS produits_local (
        uuid         TEXT PRIMARY KEY,
        nom_produit  TEXT,
        prix_vente   REAL NOT NULL DEFAULT 0,
        prix_achat   REAL NOT NULL DEFAULT 0,
        quantite     REAL NOT NULL DEFAULT 0,
        categorie    TEXT,
        sync_version INTEGER NOT NULL DEFAULT 0,
        updated_at   INTEGER NOT NULL,
        deleted_at   INTEGER,
        json_data    TEXT NOT NULL DEFAULT '{}'
    );
    CREATE INDEX IF NOT EXISTS idx_produits_local_nom ON produits_local(nom_produit, deleted_at);
    CREATE INDEX IF NOT EXISTS idx_produits_local_categorie ON produits_local(categorie, deleted_at);

    -- Clients
    CREATE TABLE IF NOT EXISTS clients_local (
        uuid              TEXT PRIMARY KEY,
        nom_client        TEXT,
        numero_telephone  TEXT,
        adresse           TEXT,
        sync_version      INTEGER NOT NULL DEFAULT 0,
        updated_at        INTEGER NOT NULL,
        deleted_at        INTEGER,
        json_data         TEXT NOT NULL DEFAULT '{}'
    );
    CREATE INDEX IF NOT EXISTS idx_clients_local_nom ON clients_local(nom_client, deleted_at);

    -- Fournisseurs
    CREATE TABLE IF NOT EXISTS fournisseurs_local (
        uuid                        TEXT PRIMARY KEY,
        nom_entreprise_fournisseur  TEXT,
        adresse                     TEXT,
        reduction_pourcentage       REAL NOT NULL DEFAULT 0,
        sync_version                INTEGER NOT NULL DEFAULT 0,
        updated_at                  INTEGER NOT NULL,
        deleted_at                  INTEGER,
        json_data                   TEXT NOT NULL DEFAULT '{}'
    );
    CREATE INDEX IF NOT EXISTS idx_fournisseurs_local_nom ON fournisseurs_local(nom_entreprise_fournisseur, deleted_at);

    -- Factures
    CREATE TABLE IF NOT EXISTS factures_local (
        uuid          TEXT PRIMARY KEY,
        total_ht      REAL NOT NULL DEFAULT 0,
        total_tva     REAL NOT NULL DEFAULT 0,
        total_ttc     REAL NOT NULL DEFAULT 0,
        montant_paye  REAL NOT NULL DEFAULT 0,
        statut        TEXT NOT NULL DEFAULT 'en_attente',
        client_id     INTEGER,
        sync_version  INTEGER NOT NULL DEFAULT 0,
        updated_at    INTEGER NOT NULL,
        deleted_at    INTEGER,
        json_data     TEXT NOT NULL DEFAULT '{}'
    );
    CREATE INDEX IF NOT EXISTS idx_factures_local_statut ON factures_local(statut, deleted_at);
    CREATE INDEX IF NOT EXISTS idx_factures_local_client ON factures_local(client_id, deleted_at);

    -- Mouvements de stock
    CREATE TABLE IF NOT EXISTS mouvement_stocks_local (
        uuid          TEXT PRIMARY KEY,
        type_mvt      TEXT,
        quantite      REAL NOT NULL DEFAULT 0,
        prix_unitaire REAL,
        prix_total    REAL,
        commentaire   TEXT,
        produit_id    INTEGER,
        sync_version  INTEGER NOT NULL DEFAULT 0,
        updated_at    INTEGER NOT NULL,
        deleted_at    INTEGER,
        json_data     TEXT NOT NULL DEFAULT '{}'
    );
    CREATE INDEX IF NOT EXISTS idx_mvt_stocks_local_type ON mouvement_stocks_local(type_mvt, deleted_at);
    CREATE INDEX IF NOT EXISTS idx_mvt_stocks_local_produit ON mouvement_stocks_local(produit_id, deleted_at);

    -- Journal comptable
    CREATE TABLE IF NOT EXISTS journals_local (
        uuid                 TEXT PRIMARY KEY,
        type_journal         TEXT,
        description          TEXT,
        montant              REAL NOT NULL DEFAULT 0,
        date_heure_operation TEXT,
        produit_id           INTEGER,
        sync_version         INTEGER NOT NULL DEFAULT 0,
        updated_at           INTEGER NOT NULL,
        deleted_at           INTEGER,
        json_data            TEXT NOT NULL DEFAULT '{}'
    );
    CREATE INDEX IF NOT EXISTS idx_journals_local_type ON journals_local(type_journal, deleted_at);
    CREATE INDEX IF NOT EXISTS idx_journals_local_date ON journals_local(date_heure_operation);

    -- Employés
    CREATE TABLE IF NOT EXISTS employes_local (
        uuid          TEXT PRIMARY KEY,
        nom           TEXT,
        prenom        TEXT,
        poste         TEXT,
        salaire_base  REAL NOT NULL DEFAULT 0,
        telephone     TEXT,
        email         TEXT,
        date_embauche TEXT,
        statut        TEXT NOT NULL DEFAULT 'actif',
        search_text   TEXT,
        sync_version  INTEGER NOT NULL DEFAULT 0,
        updated_at    INTEGER NOT NULL,
        deleted_at    INTEGER,
        json_data     TEXT NOT NULL DEFAULT '{}'
    );
    CREATE INDEX IF NOT EXISTS idx_employes_local_search ON employes_local(search_text, deleted_at);
    CREATE INDEX IF NOT EXISTS idx_employes_local_statut ON employes_local(statut, deleted_at);

    -- Transferts inter-succursales
    CREATE TABLE IF NOT EXISTS transferts_local (
        uuid               TEXT PRIMARY KEY,
        produit_id         INTEGER,
        quantite           REAL NOT NULL DEFAULT 0,
        statut             TEXT,
        from_succursale_id INTEGER,
        to_succursale_id   INTEGER,
        sync_version       INTEGER NOT NULL DEFAULT 0,
        updated_at         INTEGER NOT NULL,
        deleted_at         INTEGER,
        json_data          TEXT NOT NULL DEFAULT '{}'
    );
    CREATE INDEX IF NOT EXISTS idx_transferts_local_statut ON transferts_local(statut, deleted_at);

    -- Bons d'entrée (achats fournisseurs)
    CREATE TABLE IF NOT EXISTS bon_entrees_local (
        uuid           TEXT PRIMARY KEY,
        fournisseur_id INTEGER,
        total_ht       REAL NOT NULL DEFAULT 0,
        statut         TEXT,
        payment_type   TEXT,
        sync_version   INTEGER NOT NULL DEFAULT 0,
        updated_at     INTEGER NOT NULL,
        deleted_at     INTEGER,
        json_data      TEXT NOT NULL DEFAULT '{}'
    );
    CREATE INDEX IF NOT EXISTS idx_bon_entrees_local_statut ON bon_entrees_local(statut, deleted_at);
";

const MIGRATIONS_V3: &str = "
    -- Caisses (livre de caisse local)
    CREATE TABLE IF NOT EXISTS caisses_local (
        uuid           TEXT PRIMARY KEY,
        date_operation TEXT,
        description    TEXT,
        entree         REAL NOT NULL DEFAULT 0,
        sortie         REAL NOT NULL DEFAULT 0,
        solde          REAL,
        type_operation TEXT,
        succursale_id  INTEGER,
        sync_version   INTEGER NOT NULL DEFAULT 0,
        updated_at     INTEGER NOT NULL,
        deleted_at     INTEGER,
        json_data      TEXT NOT NULL DEFAULT '{}'
    );
    CREATE INDEX IF NOT EXISTS idx_caisses_local_date ON caisses_local(date_operation, deleted_at);
    CREATE INDEX IF NOT EXISTS idx_caisses_local_succursale ON caisses_local(succursale_id, deleted_at);

    -- Succursales (établissements)
    CREATE TABLE IF NOT EXISTS succursales_local (
        uuid             TEXT PRIMARY KEY,
        nom              TEXT,
        adresse          TEXT,
        manager_user_id  INTEGER,
        active           INTEGER NOT NULL DEFAULT 1,
        sync_version     INTEGER NOT NULL DEFAULT 0,
        updated_at       INTEGER NOT NULL,
        deleted_at       INTEGER,
        json_data        TEXT NOT NULL DEFAULT '{}'
    );
    CREATE INDEX IF NOT EXISTS idx_succursales_local_nom ON succursales_local(nom, deleted_at);

    -- Paramètres entreprise
    CREATE TABLE IF NOT EXISTS parametres_local (
        uuid                 TEXT PRIMARY KEY,
        nom_entreprise       TEXT,
        adresse              TEXT,
        email                TEXT,
        telephone            TEXT,
        devise               TEXT,
        langue               TEXT,
        tva                  REAL NOT NULL DEFAULT 0,
        reduction_accordee   REAL NOT NULL DEFAULT 0,
        theme                TEXT,
        multi_succursales    INTEGER NOT NULL DEFAULT 0,
        seuil_alerte         REAL,
        logo_path            TEXT,
        sync_version         INTEGER NOT NULL DEFAULT 0,
        updated_at           INTEGER NOT NULL,
        deleted_at           INTEGER,
        json_data            TEXT NOT NULL DEFAULT '{}'
    );
";

// ── run ───────────────────────────────────────────────────────────────────────

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    let migrations_plugin = vec![
        Migration {
            version: 1,
            description: "create_tables",
            sql: MIGRATIONS,
            kind: MigrationKind::Up,
        },
        Migration {
            version: 2,
            description: "add_client_sync_version",
            sql: "ALTER TABLE sync_queue ADD COLUMN client_sync_version INTEGER NOT NULL DEFAULT 0;",
            kind: MigrationKind::Up,
        },
        Migration {
            version: 3,
            description: "add_caisses_succursales_parametres_local",
            sql: MIGRATIONS_V3,
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
            let app_dir = app
                .path()
                .app_config_dir()
                .map_err(|e| format!("Dossier config introuvable: {}", e))?;
            std::fs::create_dir_all(&app_dir)
                .map_err(|e| format!("Impossible de créer le dossier config: {}", e))?;
            let db_path = format!(
                "sqlite:{}/primegest.db",
                app_dir.to_str().ok_or("Chemin DB non-UTF8")?
            );
            let pool = tauri::async_runtime::block_on(SqlitePool::connect(&db_path))
                .map_err(|e| format!("Impossible d'ouvrir la base de données: {}", e))?;

            tauri::async_runtime::block_on(sqlx::query(MIGRATIONS).execute(&pool)).ok();
            // Colonne ajoutée en v2 — .ok() absorbe l'erreur si elle existe déjà
            tauri::async_runtime::block_on(
                sqlx::query("ALTER TABLE sync_queue ADD COLUMN client_sync_version INTEGER NOT NULL DEFAULT 0")
                    .execute(&pool)
            ).ok();
            // Tables ajoutées en v3 — .ok() absorbe l'erreur si elles existent déjà
            tauri::async_runtime::block_on(sqlx::query(MIGRATIONS_V3).execute(&pool)).ok();

            app.manage(AppDb(pool));
            Ok(())
        })
        .invoke_handler(tauri::generate_handler![
            queue_operation,
            get_sync_status,
            sync_push,
            sync_pull,
            query_local,
            query_local_aggregate,
        ])
        .run(tauri::generate_context!())
        .unwrap_or_else(|e| eprintln!("Erreur au démarrage de Tauri: {:?}", e));
}

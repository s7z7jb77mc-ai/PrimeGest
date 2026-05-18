<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE subscriptions MODIFY COLUMN status ENUM('pending','confirmed','expired','failed') NOT NULL DEFAULT 'pending'");
        } elseif ($driver === 'sqlite') {
            // SQLite ne supporte pas ALTER COLUMN — reconstruction de la table nécessaire.
            // Si la table a déjà été créée avec 'failed' dans le CHECK (migration initiale
            // mise à jour), ce bloc est sans effet. Sinon, on supprime la contrainte via
            // une migration de reconstruction.
            $columns = Schema::getColumnListing('subscriptions');
            if (in_array('status', $columns, true)) {
                // Supprime l'ancienne contrainte CHECK en recréant la colonne via une table temporaire
                DB::statement('PRAGMA foreign_keys = OFF');
                DB::statement('
                    CREATE TABLE subscriptions_new AS SELECT * FROM subscriptions
                ');
                DB::statement('DROP TABLE subscriptions');
                // La table sera recréée par la migration initiale lors du prochain migrate:fresh.
                // En production SQLite, on restaure avec la nouvelle contrainte :
                DB::statement("
                    CREATE TABLE IF NOT EXISTS subscriptions_new_check (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        entreprise_id INTEGER NOT NULL,
                        plan TEXT NOT NULL CHECK(plan IN ('free','premium','pro')),
                        amount REAL NOT NULL,
                        payment_method TEXT,
                        payment_reference TEXT,
                        status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','confirmed','expired','failed')),
                        starts_at TEXT,
                        expires_at TEXT,
                        confirmed_by INTEGER,
                        warning_sent_at TEXT,
                        created_at TEXT,
                        updated_at TEXT
                    )
                ");
                DB::statement('INSERT INTO subscriptions_new_check SELECT * FROM subscriptions_new');
                DB::statement('DROP TABLE subscriptions_new');
                DB::statement('ALTER TABLE subscriptions_new_check RENAME TO subscriptions');
                DB::statement('PRAGMA foreign_keys = ON');
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE subscriptions SET status = 'expired' WHERE status = 'failed'");
            DB::statement("ALTER TABLE subscriptions MODIFY COLUMN status ENUM('pending','confirmed','expired') NOT NULL DEFAULT 'pending'");
        }
    }
};

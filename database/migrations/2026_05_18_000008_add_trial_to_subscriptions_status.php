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
            DB::statement("ALTER TABLE subscriptions MODIFY COLUMN status ENUM('pending','confirmed','expired','failed','trial') NOT NULL DEFAULT 'pending'");
        } elseif ($driver === 'sqlite') {
            // SQLite : reconstruction de la table pour modifier la contrainte CHECK
            $columns = Schema::getColumnListing('subscriptions');
            if (in_array('status', $columns, true)) {
                DB::statement('PRAGMA foreign_keys = OFF');

                // Dynamically check if warning_sent_at column exists
                $hasWarningColumn = in_array('warning_sent_at', $columns, true);
                $warningColDefinition = $hasWarningColumn ? ",\n                        warning_sent_at TEXT" : '';

                DB::statement("
                    CREATE TABLE subscriptions_trial_tmp (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        entreprise_id INTEGER NOT NULL,
                        plan TEXT NOT NULL CHECK(plan IN ('free','premium','pro')),
                        amount REAL NOT NULL,
                        payment_method TEXT,
                        payment_reference TEXT,
                        status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','confirmed','expired','failed','trial')),
                        starts_at TEXT,
                        expires_at TEXT,
                        confirmed_by INTEGER{$warningColDefinition},
                        created_at TEXT,
                        updated_at TEXT
                    )
                ");

                // Build the SELECT statement dynamically based on existing columns
                $selectColumns = 'id, entreprise_id, plan, amount, payment_method, payment_reference, status, starts_at, expires_at, confirmed_by';
                if ($hasWarningColumn) {
                    $selectColumns .= ', warning_sent_at';
                }
                $selectColumns .= ', created_at, updated_at';

                DB::statement("INSERT INTO subscriptions_trial_tmp SELECT {$selectColumns} FROM subscriptions");
                DB::statement('DROP TABLE subscriptions');
                DB::statement('ALTER TABLE subscriptions_trial_tmp RENAME TO subscriptions');

                DB::statement('PRAGMA foreign_keys = ON');
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE subscriptions SET status = 'expired' WHERE status = 'trial'");
            DB::statement("ALTER TABLE subscriptions MODIFY COLUMN status ENUM('pending','confirmed','expired','failed') NOT NULL DEFAULT 'pending'");
        }
    }
};

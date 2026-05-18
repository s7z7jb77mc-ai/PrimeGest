<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite doesn't enforce ENUM, but we document the new status value
        // In production (MySQL), use: ALTER TABLE subscriptions MODIFY COLUMN status ENUM('pending','confirmed','expired','failed')
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE subscriptions MODIFY COLUMN status ENUM('pending','confirmed','expired','failed') NOT NULL DEFAULT 'pending'");
        }
        // For SQLite, the column already accepts any string value
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE subscriptions MODIFY COLUMN status ENUM('pending','confirmed','expired') NOT NULL DEFAULT 'pending'");
        }
    }
};

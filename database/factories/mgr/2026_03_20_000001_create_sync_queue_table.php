<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Migration legacy neutralisée.
        // La vraie structure offline-first de sync_queue est définie
        // dans 2026_05_06_000001_recreate_sync_queue_table.php.
    }

    public function down(): void
    {
        //
    }
};

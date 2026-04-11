<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('transferts') || !Schema::hasColumn('transferts', 'from_succursale_id')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `transferts` MODIFY `from_succursale_id` BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        // No-op: reverting this migration safely would require rewriting existing central transfers.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (Schema::hasTable('mouvement_stocks') && Schema::hasColumn('mouvement_stocks', 'type')) {
            DB::statement("UPDATE mouvement_stocks SET type = 'entree' WHERE type = 'entrée'");

            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE mouvement_stocks MODIFY type VARCHAR(20) NOT NULL");
            }
        }

        if (Schema::hasTable('archives') && Schema::hasColumn('archives', 'type') && $driver === 'mysql') {
            DB::statement("ALTER TABLE archives MODIFY type VARCHAR(50) NOT NULL");
        }
    }

    public function down(): void
    {
        // no-op
    }
};

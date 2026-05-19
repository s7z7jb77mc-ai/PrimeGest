<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('archives')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `archives` MODIFY `type` ENUM('facture','mouvement_stock','journal','bon_entree') NOT NULL");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('archives')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `archives` MODIFY `type` ENUM('facture','mouvement_stock','journal') NOT NULL");
        }
    }
};

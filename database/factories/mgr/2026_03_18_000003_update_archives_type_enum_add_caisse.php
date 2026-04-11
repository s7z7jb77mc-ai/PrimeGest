<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('archives')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `archives` MODIFY `type` ENUM('facture','mouvement_stock','journal','bon_entree','caisse') NOT NULL");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('archives')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `archives` MODIFY `type` ENUM('facture','mouvement_stock','journal','bon_entree') NOT NULL");
        }
    }
};

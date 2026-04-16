<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('fiches_de_paie') && Schema::hasColumn('fiches_de_paie', 'date_paiement')) {
            DB::statement('ALTER TABLE fiches_de_paie MODIFY date_paiement DATETIME NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('fiches_de_paie') && Schema::hasColumn('fiches_de_paie', 'date_paiement')) {
            DB::statement('ALTER TABLE fiches_de_paie MODIFY date_paiement DATE NULL');
        }
    }
};

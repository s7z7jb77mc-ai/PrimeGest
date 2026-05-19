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

        Schema::table('fiches_de_paie', function (Blueprint $table) {
            // Add default value for salaire_base column
            if (Schema::hasColumn('fiches_de_paie', 'salaire_base')) {
                DB::statement('ALTER TABLE fiches_de_paie MODIFY salaire_base DECIMAL(12, 2) DEFAULT 0 AFTER annee');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('fiches_de_paie', function (Blueprint $table) {
            if (Schema::hasColumn('fiches_de_paie', 'salaire_base')) {
                DB::statement('ALTER TABLE fiches_de_paie MODIFY salaire_base DECIMAL(12, 2) NULL');
            }
        });
    }
};

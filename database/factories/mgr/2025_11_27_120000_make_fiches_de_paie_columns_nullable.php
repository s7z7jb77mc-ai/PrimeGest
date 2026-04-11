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
            // Make mois nullable with default value
            if (Schema::hasColumn('fiches_de_paie', 'mois')) {
                DB::statement('ALTER TABLE fiches_de_paie MODIFY mois VARCHAR(255) NULL DEFAULT NULL');
            }

            // Make salaire_brut nullable with default 0
            if (Schema::hasColumn('fiches_de_paie', 'salaire_brut')) {
                DB::statement('ALTER TABLE fiches_de_paie MODIFY salaire_brut DECIMAL(10, 2) DEFAULT 0');
            }

            // Make salaire_net nullable with default 0
            if (Schema::hasColumn('fiches_de_paie', 'salaire_net')) {
                DB::statement('ALTER TABLE fiches_de_paie MODIFY salaire_net DECIMAL(10, 2) DEFAULT 0');
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
            if (Schema::hasColumn('fiches_de_paie', 'mois')) {
                DB::statement('ALTER TABLE fiches_de_paie MODIFY mois VARCHAR(255) NOT NULL');
            }

            if (Schema::hasColumn('fiches_de_paie', 'salaire_brut')) {
                DB::statement('ALTER TABLE fiches_de_paie MODIFY salaire_brut DECIMAL(10, 2) NULL');
            }

            if (Schema::hasColumn('fiches_de_paie', 'salaire_net')) {
                DB::statement('ALTER TABLE fiches_de_paie MODIFY salaire_net DECIMAL(10, 2) NULL');
            }
        });
    }
};

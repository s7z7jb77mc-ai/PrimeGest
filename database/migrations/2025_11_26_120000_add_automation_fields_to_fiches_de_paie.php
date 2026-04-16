<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fiches_de_paie', function (Blueprint $table) {
            // Ajouter le champ statut_paiement s'il n'existe pas
            if (!Schema::hasColumn('fiches_de_paie', 'statut_paiement')) {
                $table->enum('statut_paiement', ['en_attente', 'payee'])->default('en_attente')->after('statut');
            }

            // Ajouter d'autres champs si manquants
            if (!Schema::hasColumn('fiches_de_paie', 'salaire_brut')) {
                $table->decimal('salaire_brut', 10, 2)->nullable()->after('salaire_base');
            }

            if (!Schema::hasColumn('fiches_de_paie', 'salaire_net')) {
                $table->decimal('salaire_net', 10, 2)->nullable()->after('retenues');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fiches_de_paie', function (Blueprint $table) {
            if (Schema::hasColumn('fiches_de_paie', 'statut_paiement')) {
                $table->dropColumn('statut_paiement');
            }

            if (Schema::hasColumn('fiches_de_paie', 'salaire_brut')) {
                $table->dropColumn('salaire_brut');
            }

            if (Schema::hasColumn('fiches_de_paie', 'salaire_net')) {
                $table->dropColumn('salaire_net');
            }
        });
    }
};

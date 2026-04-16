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
        Schema::table('factures', function (Blueprint $table) {
            // Ajouter user_id s'il n'existe pas
            if (!Schema::hasColumn('factures', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('entreprise_id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            }

            // Ajouter les colonnes pour l'automatisation s'elles n'existent pas
            if (!Schema::hasColumn('factures', 'total_ht')) {
                $table->decimal('total_ht', 12, 2)->default(0)->after('numero');
            }

            if (!Schema::hasColumn('factures', 'total_tva')) {
                $table->decimal('total_tva', 12, 2)->default(0)->after('total_ht');
            }

            if (!Schema::hasColumn('factures', 'total_ttc')) {
                $table->decimal('total_ttc', 12, 2)->default(0)->after('total_tva');
            }

            if (!Schema::hasColumn('factures', 'montant_paye')) {
                $table->decimal('montant_paye', 12, 2)->default(0)->after('total_ttc');
            }

            if (!Schema::hasColumn('factures', 'statut')) {
                $table->enum('statut', ['en_attente', 'payee', 'annulee'])->default('en_attente')->after('montant_paye');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            if (Schema::hasColumn('factures', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }

            if (Schema::hasColumn('factures', 'total_ht')) {
                $table->dropColumn('total_ht');
            }

            if (Schema::hasColumn('factures', 'total_tva')) {
                $table->dropColumn('total_tva');
            }

            if (Schema::hasColumn('factures', 'total_ttc')) {
                $table->dropColumn('total_ttc');
            }

            if (Schema::hasColumn('factures', 'montant_paye')) {
                $table->dropColumn('montant_paye');
            }

            if (Schema::hasColumn('factures', 'statut')) {
                $table->dropColumn('statut');
            }
        });
    }
};

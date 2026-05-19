<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'clients',
            'fournisseurs',
            'caisses',
            'journals',
            'mouvement_stocks',
            'stocks',
            'factures',
            'facture_lignes',
            'bon_entrees',
            'bon_entree_lignes',
            'creances',
            'dettes',
            'report_logs',
            'archives',
            'reduction_usages',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'succursale_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->unsignedBigInteger('succursale_id')->nullable()->after('id');
                    $table->index('succursale_id');
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'clients',
            'fournisseurs',
            'caisses',
            'journals',
            'mouvement_stocks',
            'stocks',
            'factures',
            'facture_lignes',
            'bon_entrees',
            'bon_entree_lignes',
            'creances',
            'dettes',
            'report_logs',
            'archives',
            'reduction_usages',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'succursale_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropIndex(['succursale_id']);
                    $table->dropColumn('succursale_id');
                });
            }
        }
    }
};

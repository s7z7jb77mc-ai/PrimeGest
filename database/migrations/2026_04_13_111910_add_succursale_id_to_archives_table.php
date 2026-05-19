<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSuccursaleIdToArchivesTable extends Migration
{
     public function up(): void
{
    Schema::table('archives', function (Blueprint $table) {
        if (!Schema::hasColumn('archives', 'succursale_id')) {
            $table->unsignedBigInteger('succursale_id')->nullable()->after('id');
        }
        if (!Schema::hasIndex('archives', 'archives_entreprise_id_succursale_id_type_index')) {
            $table->index(['entreprise_id', 'succursale_id', 'type']);
        }
    });
}
        // ✅ Après entreprise_id, nullable car les archives
            // centrales n'ont pas de succursale
           

            // Index pour accélérer les filtres
            // ✅ Après entreprise_id, nullable car les archives
            // centrales n'ont pas de succursale
            
            // Index pour ac

    public function down(): void
    {
        Schema::table('archives', function (Blueprint $table) {
            $table->dropIndex(['entreprise_id', 'succursale_id', 'type']);
            $table->dropColumn('succursale_id');
        });
    }
}

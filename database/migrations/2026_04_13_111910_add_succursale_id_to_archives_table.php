<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSuccursaleIdToArchivesTable extends Migration
{
    public function up(): void
    {
        Schema::table('archives', function (Blueprint $table) {
            // ✅ Après entreprise_id, nullable car les archives
            // centrales n'ont pas de succursale
            $table->unsignedBigInteger('succursale_id')->nullable()->after('entreprise_id');

            // Index pour accélérer les filtres
            $table->index(['entreprise_id', 'succursale_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('archives', function (Blueprint $table) {
            $table->dropIndex(['entreprise_id', 'succursale_id', 'type']);
            $table->dropColumn('succursale_id');
        });
    }
}
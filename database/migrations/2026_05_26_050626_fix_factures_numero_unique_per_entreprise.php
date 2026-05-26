<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            // La contrainte portait uniquement sur `numero`, ce qui empêchait
            // deux entreprises différentes d'avoir le même numéro (ex: FAC-0001).
            // On la remplace par une contrainte composite (entreprise_id, numero).
            $table->dropUnique('factures_numero_unique');
            $table->unique(['entreprise_id', 'numero'], 'factures_entreprise_numero_unique');
        });
    }

    public function down(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->dropUnique('factures_entreprise_numero_unique');
            $table->unique('numero', 'factures_numero_unique');
        });
    }
};

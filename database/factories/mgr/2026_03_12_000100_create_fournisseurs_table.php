<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fournisseurs')) {
            Schema::create('fournisseurs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('entreprise_id');
                $table->string('nom_entreprise_fournisseur');
                $table->string('adresse')->nullable();
                $table->decimal('dette', 12, 2)->default(0);
                $table->decimal('reduction_pourcentage', 12, 2)->default(0);
                $table->decimal('achat_mensuel', 12, 2)->default(0);
                $table->decimal('reduction_obtenue', 12, 2)->default(0);
                $table->timestamps();

                $table->foreign('entreprise_id')->references('id')->on('entreprises')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fournisseurs');
    }
};

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
        Schema::create('rapports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id');
            $table->enum('type', ['journalier', 'hebdomadaire', 'mensuel']);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->longText('contenu'); // JSON avec les données du rapport
            $table->text('resume')->nullable(); // Résumé rapide
            $table->dateTime('date_generation'); // Date de génération
            $table->timestamps();

            // Foreign keys
            $table->foreign('entreprise_id')->references('id')->on('entreprises')->onDelete('cascade');

            // Index pour les recherches rapides
            $table->index('entreprise_id');
            $table->index('type');
            $table->index('date_debut');
            $table->index(['entreprise_id', 'type', 'date_debut']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rapports');
    }
};

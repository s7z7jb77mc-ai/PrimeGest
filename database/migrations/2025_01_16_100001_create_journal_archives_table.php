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
        Schema::create('journal_archives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id');
            $table->unsignedBigInteger('produit_id')->nullable();
            $table->unsignedBigInteger('journal_id')->nullable(); // Référence à l'original
            $table->dateTime('dateHeure_operation');
            $table->string('type', 50);
            $table->text('description')->nullable();
            $table->decimal('montant', 15, 2);
            $table->date('date_archive'); // Date d'archivage
            $table->timestamps();

            // Foreign keys
            $table->foreign('entreprise_id')->references('id')->on('entreprises')->onDelete('cascade');
            $table->foreign('produit_id')->references('id')->on('produits')->onDelete('set null');
            $table->foreign('journal_id')->references('id')->on('journals')->onDelete('set null');

            // Index pour les recherches rapides
            $table->index('entreprise_id');
            $table->index('date_archive');
            $table->index(['entreprise_id', 'date_archive']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_archives');
    }
};

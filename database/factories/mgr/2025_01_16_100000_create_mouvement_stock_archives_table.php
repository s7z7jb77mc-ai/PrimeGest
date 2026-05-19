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
        Schema::create('mouvement_stock_archives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id');
            $table->unsignedBigInteger('produit_id');
            $table->unsignedBigInteger('mouvement_stock_id')->nullable(); // Référence à l'original
            $table->enum('type', ['entree', 'sortie']);
            $table->integer('quantite');
            $table->decimal('prix_unitaire', 10, 2)->nullable();
            $table->decimal('prix_total', 10, 2)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('commentaire')->nullable();
            $table->date('date_archive'); // Date d'archivage
            $table->timestamps();

            // Foreign keys
            $table->foreign('entreprise_id')->references('id')->on('entreprises')->onDelete('cascade');
            $table->foreign('produit_id')->references('id')->on('produits')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('mouvement_stock_id')->references('id')->on('mouvement_stocks')->onDelete('set null');

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
        Schema::dropIfExists('mouvement_stock_archives');
    }
};

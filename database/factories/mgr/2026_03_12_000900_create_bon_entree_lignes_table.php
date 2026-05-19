<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bon_entree_lignes')) {
            Schema::create('bon_entree_lignes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('bon_entree_id');
                $table->unsignedBigInteger('produit_id');
                $table->integer('quantite');
                $table->decimal('prix_unitaire', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->timestamps();

                $table->foreign('bon_entree_id')->references('id')->on('bon_entrees')->onDelete('cascade');
                $table->foreign('produit_id')->references('id')->on('produits')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bon_entree_lignes');
    }
};

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
        Schema::create('mouvement_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained()->onDelete('cascade');
            $table->foreignId('produit_id')->constrained()->onDelete('cascade');
            $table->string('nom_produit');
            $table->string('type', 20);
            $table->integer('quantite');
            $table->decimal('prix_unitaire', 10, 2)->nullable();
            $table->decimal('prix_total', 10, 2)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->text('commentaire')->nullable();
            $table->timestamp('date')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mouvement_stocks');
    }
};

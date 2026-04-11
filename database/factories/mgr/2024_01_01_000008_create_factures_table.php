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
        Schema::create('factures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('numero')->unique();
            $table->decimal('total_montant', 10, 2)->nullable();
            $table->decimal('prix_hors_tva', 10, 2)->nullable();
            $table->decimal('total_ht', 10, 2)->nullable();
            $table->decimal('total_tva', 10, 2)->nullable();
            $table->decimal('total_ttc', 10, 2)->nullable();
            $table->decimal('tva', 5, 2)->default(0);
            $table->boolean('cash')->default(false);
            $table->decimal('montant_paye', 10, 2)->default(0);
            $table->decimal('echange', 10, 2)->default(0);
            $table->enum('statut', ['en_attente', 'payee', 'annulee'])->default('en_attente');
            $table->timestamp('date_facture')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('factures');
    }
};

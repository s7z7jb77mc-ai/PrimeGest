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
        if (!Schema::hasTable('factures')) {
            Schema::create('factures', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('entreprise_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->decimal('total_ht', 12, 2)->default(0);
                $table->decimal('total_tva', 12, 2)->default(0);
                $table->decimal('total_ttc', 12, 2)->default(0);
                $table->decimal('montant_paye', 12, 2)->default(0);
                $table->enum('statut', ['en_attente', 'payee', 'annulee'])->default('en_attente');
                $table->timestamps();

                // Foreign keys
                $table->foreign('entreprise_id')->references('id')->on('entreprises')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('factures');
    }
};

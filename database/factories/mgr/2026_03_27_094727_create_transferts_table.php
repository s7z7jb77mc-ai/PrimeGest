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
        Schema::create('transferts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id');
            $table->unsignedBigInteger('from_succursale_id')->nullable();
            $table->unsignedBigInteger('to_succursale_id');
            $table->unsignedBigInteger('produit_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('type', 20); // caisse | stock
            $table->decimal('montant', 15, 2)->nullable();
            $table->decimal('quantite', 15, 2)->nullable();
            $table->string('status', 20)->default('validated'); // pending | validated | rejected
            $table->dateTime('date_operation')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['entreprise_id', 'from_succursale_id']);
            $table->index(['entreprise_id', 'to_succursale_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transferts');
    }
};

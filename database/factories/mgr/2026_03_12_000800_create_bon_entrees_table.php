<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bon_entrees')) {
            Schema::create('bon_entrees', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('entreprise_id');
                $table->unsignedBigInteger('fournisseur_id');
                $table->string('numero')->nullable();
                $table->decimal('total_montant', 12, 2)->default(0);
                $table->dateTime('date_bon')->nullable();
                $table->timestamps();

                $table->foreign('entreprise_id')->references('id')->on('entreprises')->onDelete('cascade');
                $table->foreign('fournisseur_id')->references('id')->on('fournisseurs')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bon_entrees');
    }
};

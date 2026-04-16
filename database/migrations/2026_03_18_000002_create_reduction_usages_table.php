<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reduction_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id');
            $table->string('entity_type'); // client|fournisseur
            $table->unsignedBigInteger('entity_id');
            $table->decimal('montant_utilise', 15, 2)->default(0);
            $table->decimal('reste_apres', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['entreprise_id', 'entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reduction_usages');
    }
};

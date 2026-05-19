<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('clients')) {
            Schema::create('clients', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('entreprise_id');
                $table->string('nom_client');
                $table->string('numero_telephone');
                $table->string('adresse')->nullable();
                $table->decimal('creance', 12, 2)->default(0);
                $table->decimal('achat_mensuel', 12, 2)->default(0);
                $table->decimal('reduction_accordee', 12, 2)->default(0);
                $table->timestamps();

                $table->foreign('entreprise_id')->references('id')->on('entreprises')->onDelete('cascade');
                $table->unique(['entreprise_id', 'numero_telephone']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};

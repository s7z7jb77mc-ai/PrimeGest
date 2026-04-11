<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fiches_de_paie')) {
            Schema::create('fiches_de_paie', function (Blueprint $table) {
                $table->id();
                $table->foreignId('entreprise_id')->constrained('entreprises')->onDelete('cascade');
                $table->foreignId('employe_id')->constrained('employes')->onDelete('cascade');
                $table->string('mois');
                $table->integer('annee');
                $table->decimal('salaire_base', 12, 2);
                $table->decimal('primes', 12, 2)->default(0);
                $table->decimal('retenues', 12, 2)->default(0);
                $table->decimal('net_a_payer', 12, 2);
                $table->enum('statut', ['en_attente', 'paye'])->default('en_attente');
                $table->date('date_paiement')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fiches_de_paie');
    }
};

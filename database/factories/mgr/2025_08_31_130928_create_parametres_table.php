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
        if (!Schema::hasTable('parametres')) {
            Schema::create('parametres', function (Blueprint $table) {
                $table->id();
                $table->foreignId('entreprise_id')->constrained()->onDelete('cascade');
                $table->string('adresse')->nullable();
                $table->string('email')->nullable();
                $table->string('telephone')->nullable();
                $table->enum('devise', ['USD', 'CDF', 'RWF'])->default('CDF');
                $table->string('theme')->default('clair');
                $table->string('rccm')->nullable();
                $table->string('identifiant_national')->nullable();
                $table->string('numero_impot')->nullable();
                $table->boolean('sauvegarde_auto')->default(false);
                $table->string('logo')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parametres');
    }
};

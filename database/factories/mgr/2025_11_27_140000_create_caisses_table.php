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
        if (!Schema::hasTable('caisses')) {
            Schema::create('caisses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('entreprise_id')->nullable()->constrained('entreprises')->onDelete('cascade');
                $table->datetime('date_operation')->nullable();
                $table->string('description')->nullable();
                $table->decimal('entree', 12, 2)->default(0);
                $table->decimal('sortie', 12, 2)->default(0);
                $table->decimal('solde', 12, 2)->nullable();
                $table->timestamps();
                
                $table->index('entreprise_id');
                $table->index('date_operation');
            });
        } else {
            // Si la table existe, ajouter les colonnes manquantes
            Schema::table('caisses', function (Blueprint $table) {
                if (!Schema::hasColumn('caisses', 'date_operation')) {
                    $table->datetime('date_operation')->nullable();
                }
                if (!Schema::hasColumn('caisses', 'entreprise_id')) {
                    $table->foreignId('entreprise_id')->nullable()->after('id')->constrained('entreprises')->onDelete('cascade');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caisses');
    }
};

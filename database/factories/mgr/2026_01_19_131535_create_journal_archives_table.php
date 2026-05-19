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
        if (!Schema::hasTable('journal_archives')) {
            Schema::create('journal_archives', function (Blueprint $table) {
                $table->id();
                $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
                $table->foreignId('produit_id')->nullable()->constrained('produits')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->dateTime('dateHeure_operation');
                $table->date('date_archive')->index();
                $table->string('type'); // 'entree' ou 'sortie'
                $table->text('description')->nullable();
                $table->decimal('montant', 12, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_archives');
    }
};

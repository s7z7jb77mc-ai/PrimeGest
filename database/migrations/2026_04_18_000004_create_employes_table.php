<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('employes')) {
            Schema::create('employes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('entreprise_id');
                $table->unsignedBigInteger('succursale_id')->nullable();
                $table->string('nom');
                $table->string('prenom')->nullable();
                $table->string('poste')->nullable();
                $table->decimal('salaire_base', 15, 2)->default(0);
                $table->string('telephone')->nullable();
                $table->string('email')->nullable();
                $table->date('date_embauche')->nullable();
                $table->timestamps();
            });
        }
    }
    public function down(): void {
        Schema::dropIfExists('employes');
    }
};

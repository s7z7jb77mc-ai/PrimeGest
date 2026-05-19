<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('creances')) {
            Schema::create('creances', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('entreprise_id');
                $table->unsignedBigInteger('client_id');
                $table->decimal('montant_paye', 12, 2)->default(0);
                $table->unsignedBigInteger('caisse_id')->nullable();
                $table->timestamps();

                $table->foreign('entreprise_id')->references('id')->on('entreprises')->onDelete('cascade');
                $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
                $table->foreign('caisse_id')->references('id')->on('caisses')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('creances');
    }
};

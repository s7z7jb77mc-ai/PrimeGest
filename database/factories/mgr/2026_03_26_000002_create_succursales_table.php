<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('succursales')) {
            return;
        }

        Schema::create('succursales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id');
            $table->string('nom');
            $table->string('adresse')->nullable();
            $table->unsignedBigInteger('manager_user_id')->nullable();
            $table->timestamps();

            $table->index('entreprise_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('succursales');
    }
};

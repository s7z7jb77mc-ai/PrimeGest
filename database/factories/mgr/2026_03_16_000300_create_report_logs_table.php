<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('report_logs')) {
            Schema::create('report_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('entreprise_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action', 20);
                $table->string('report_type', 20);
                $table->string('report_date', 50)->nullable();
                $table->timestamps();

                $table->foreign('entreprise_id')->references('id')->on('entreprises')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
                $table->index(['entreprise_id', 'report_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('report_logs');
    }
};

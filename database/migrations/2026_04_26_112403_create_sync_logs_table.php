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
        if (Schema::hasTable('sync_logs')) return;

        Schema::create('sync_logs', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('entreprise_id');
        $table->string('device_id')->nullable();
        $table->enum('direction', ['push', 'pull']);
        $table->unsignedInteger('operations_count')->default(0);
        $table->unsignedInteger('conflicts_count')->default(0);
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};


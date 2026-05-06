<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old sync_queue structure if it exists (wrong schema)
        Schema::dropIfExists('sync_queue');

        Schema::create('sync_queue', function (Blueprint $table) {
            $table->id();
            $table->string('device_id');
            $table->string('table_name');
            $table->string('record_uuid');
            $table->string('succursale_uuid')->nullable();
            $table->string('entreprise_uuid')->nullable();
            $table->enum('operation', ['insert', 'update', 'delete']);
            $table->json('payload');
            $table->string('checksum');
            $table->enum('status', ['pending', 'syncing', 'done', 'conflict'])->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error_message')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['status', 'attempts']);
            $table->index('device_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_queue');
    }
};

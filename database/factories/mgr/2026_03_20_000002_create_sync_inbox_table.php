<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sync_inbox')) {
            return;
        }

        Schema::create('sync_inbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('record_uuid')->unique();
            $table->string('device_id')->nullable();
            $table->string('table_name');
            $table->string('operation');
            $table->json('payload');
            $table->string('checksum')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('entreprise_id')->nullable();
            $table->string('status')->default('received');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_inbox');
    }
};

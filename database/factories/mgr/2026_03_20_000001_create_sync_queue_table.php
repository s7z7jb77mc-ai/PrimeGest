<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'local';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('sync_queue')) {
            return;
        }

        Schema::connection($this->connection)->create('sync_queue', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('entity'); // ex: ventes, clients, fournisseurs, caisse
            $table->string('operation'); // create, update, delete
            $table->json('payload');
            $table->string('status')->default('pending'); // pending, processing, synced, failed
            $table->unsignedInteger('attempts')->default(0);
            $table->string('device_id')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('sync_queue');
    }
};

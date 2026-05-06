<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conflict_log', function (Blueprint $table) {
            $table->id();
            $table->string('table_name');
            $table->string('record_uuid');
            $table->json('local_payload');
            $table->json('cloud_payload');
            $table->timestamp('created_at')->useCurrent();

            $table->index('record_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conflict_log');
    }
};

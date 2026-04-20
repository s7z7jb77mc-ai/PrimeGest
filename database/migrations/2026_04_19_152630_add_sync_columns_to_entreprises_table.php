<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {
            $table->unsignedBigInteger('sync_version')->default(0)->after('updated_at');
            $table->softDeletes()->after('sync_version');
            $table->index(['updated_at', 'sync_version'], 'idx_entreprises_sync');
        });

        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->string('device_id', 64);
            $table->enum('direction', ['push', 'pull']);
            $table->unsignedSmallInteger('operations_count')->default(0);
            $table->unsignedSmallInteger('conflicts_count')->default(0);
            $table->timestamp('synced_at')->useCurrent();
            $table->index(['entreprise_id', 'synced_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
        Schema::table('entreprises', function (Blueprint $table) {
            $table->dropIndex('idx_entreprises_sync');
            $table->dropSoftDeletes();
            $table->dropColumn('sync_version');
        });
    }
};

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
    Schema::table('transferts', function (Blueprint $table) {
        if (!Schema::hasColumn('transferts', 'uuid')) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        }
        if (!Schema::hasColumn('transferts', 'sync_version')) {
            $table->unsignedInteger('sync_version')->default(0)->after('uuid');
        }
    });

    DB::table('transferts')->whereNull('uuid')->orderBy('id')->each(function ($row) {
        DB::table('transferts')->where('id', $row->id)->update([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
        ]);
    });
}

public function down(): void
{
    Schema::table('transferts', function (Blueprint $table) {
        $table->dropColumn(['uuid', 'sync_version']);
    });
}
};

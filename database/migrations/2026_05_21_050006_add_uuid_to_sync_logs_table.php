<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sync_logs', 'uuid')) {
            Schema::table('sync_logs', function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->after('id');
            });
        }

        DB::table('sync_logs')->orderBy('id')->each(function ($row) {
            if (empty($row->uuid)) {
                DB::table('sync_logs')->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
            }
        });

        Schema::table('sync_logs', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sync_logs', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};

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
        foreach (['fiches_de_paie', 'dettes', 'creances'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (!Schema::hasColumn($table, 'uuid')) {
                    $t->uuid('uuid')->nullable()->after('id');
                }
                if (!Schema::hasColumn($table, 'sync_version')) {
                    $t->unsignedBigInteger('sync_version')->default(0)->after('uuid');
                }
                if (!Schema::hasColumn($table, 'deleted_at')) {
                    $t->softDeletes();
                }
            });

            DB::table($table)
                ->whereNull('uuid')
                ->orderBy('id')
                ->get(['id'])
                ->each(function ($row) use ($table) {
                    DB::table($table)->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
                });
        }
    }

    public function down(): void
    {
        foreach (['fiches_de_paie', 'dettes', 'creances'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $cols = [];
            foreach (['uuid', 'sync_version', 'deleted_at'] as $col) {
                if (Schema::hasColumn($table, $col)) {
                    $cols[] = $col;
                }
            }
            if ($cols) {
                Schema::table($table, fn(Blueprint $t) => $t->dropColumn($cols));
            }
        }
    }
};

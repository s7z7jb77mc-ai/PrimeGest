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
        $this->addSyncFields('parametres');
        $this->addSyncFields('succursales');
        $this->addSyncFields('caisses');

        $this->backfillUuid('parametres');
        $this->backfillUuid('succursales');
        $this->backfillUuid('caisses');
    }

    public function down(): void
    {
        foreach (['parametres', 'succursales', 'caisses'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $columns = [];

                if (Schema::hasColumn($table, 'uuid')) {
                    $columns[] = 'uuid';
                }
                if (Schema::hasColumn($table, 'sync_version')) {
                    $columns[] = 'sync_version';
                }
                if (Schema::hasColumn($table, 'deleted_at')) {
                    $columns[] = 'deleted_at';
                }

                if ($columns !== []) {
                    $blueprint->dropColumn($columns);
                }
            });
        }
    }

    private function addSyncFields(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            if (! Schema::hasColumn($table, 'uuid')) {
                $blueprint->uuid('uuid')->nullable()->after('id');
            }

            if (! Schema::hasColumn($table, 'sync_version')) {
                $blueprint->unsignedBigInteger('sync_version')->default(0)->after('uuid');
            }

            if (! Schema::hasColumn($table, 'deleted_at')) {
                $blueprint->softDeletes();
            }
        });
    }

    private function backfillUuid(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'uuid')) {
            return;
        }

        DB::table($table)
            ->whereNull('uuid')
            ->orderBy('id')
            ->get(['id'])
            ->each(function ($row) use ($table) {
                DB::table($table)
                    ->where('id', $row->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            });
    }
};

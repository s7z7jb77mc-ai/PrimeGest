<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\Schema\Blueprint as SchemaBP;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('entreprises', 'uuid')) {
            Schema::table('entreprises', function (SchemaBP $table) {
                $table->uuid('uuid')->nullable()->after('id');
            });
        }

        foreach (DB::table('entreprises')->whereNull('uuid')->orWhere('uuid', '')->orderBy('id')->pluck('id') as $id) {
            DB::table('entreprises')->where('id', $id)->update(['uuid' => (string) Str::uuid()]);
        }

        $hasUnique = collect(Schema::getIndexes('entreprises'))
            ->contains(fn ($i) => ($i['unique'] ?? false) && in_array('uuid', $i['columns'] ?? [], true));

        if (! $hasUnique) {
            Schema::table('entreprises', function (SchemaBP $table) {
                $table->unique('uuid');
            });
        }
    }

    public function down(): void
    {
        Schema::table('entreprises', function (SchemaBP $table) {
            $table->dropColumn('uuid');
        });
    }
};

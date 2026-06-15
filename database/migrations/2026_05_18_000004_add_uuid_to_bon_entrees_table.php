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
        if (! Schema::hasColumn('bon_entrees', 'uuid')) {
            Schema::table('bon_entrees', function (SchemaBP $table) {
                $table->uuid('uuid')->nullable()->after('id');
            });
        }

        foreach (DB::table('bon_entrees')->whereNull('uuid')->orWhere('uuid', '')->orderBy('id')->pluck('id') as $id) {
            DB::table('bon_entrees')->where('id', $id)->update(['uuid' => (string) Str::uuid()]);
        }

        $hasUnique = collect(Schema::getIndexes('bon_entrees'))
            ->contains(fn ($i) => ($i['unique'] ?? false) && in_array('uuid', $i['columns'] ?? [], true));

        if (! $hasUnique) {
            Schema::table('bon_entrees', function (SchemaBP $table) {
                $table->unique('uuid');
            });
        }
    }

    public function down(): void
    {
        Schema::table('bon_entrees', function (SchemaBP $table) {
            $table->dropColumn('uuid');
        });
    }
};

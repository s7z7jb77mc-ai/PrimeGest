<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        DB::table('entreprises')->whereNull('uuid')->orWhere('uuid', '')->update(['uuid' => DB::raw('UUID()')]);

        $hasUnique = collect(DB::select("SHOW INDEX FROM `entreprises`"))
            ->contains(fn ($row) => $row->Key_name === 'entreprises_uuid_unique');

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

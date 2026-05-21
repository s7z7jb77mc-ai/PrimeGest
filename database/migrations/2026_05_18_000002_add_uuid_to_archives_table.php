<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add column if it doesn't exist yet
        if (! Schema::hasColumn('archives', 'uuid')) {
            Schema::table('archives', function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->after('id');
            });
        }

        // Fill any NULL / empty UUIDs
        DB::table('archives')->whereNull('uuid')->orWhere('uuid', '')->update(['uuid' => DB::raw('UUID()')]);

        // Add unique constraint if not already present
        $hasUnique = collect(DB::select("SHOW INDEX FROM `archives`"))
            ->contains(fn ($row) => $row->Key_name === 'archives_uuid_unique');

        if (! $hasUnique) {
            Schema::table('archives', function (Blueprint $table) {
                $table->unique('uuid');
            });
        }
    }

    public function down(): void
    {
        Schema::table('archives', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};

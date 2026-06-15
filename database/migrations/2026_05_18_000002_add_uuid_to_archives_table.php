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
        // Add column if it doesn't exist yet
        if (! Schema::hasColumn('archives', 'uuid')) {
            Schema::table('archives', function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->after('id');
            });
        }

        // Remplissage portable des UUID manquants — UUID() est une fonction
        // MySQL inexistante sur SQLite (utilisé par la CI et le mode offline).
        foreach (DB::table('archives')->whereNull('uuid')->orWhere('uuid', '')->orderBy('id')->pluck('id') as $id) {
            DB::table('archives')->where('id', $id)->update(['uuid' => (string) Str::uuid()]);
        }

        // Index unique s'il n'existe pas déjà (vérification portable)
        $hasUnique = collect(Schema::getIndexes('archives'))
            ->contains(fn ($i) => ($i['unique'] ?? false) && in_array('uuid', $i['columns'] ?? [], true));

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

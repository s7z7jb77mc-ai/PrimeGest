<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('caisses', 'type_operation')) {
            Schema::table('caisses', function (Blueprint $table) {
                $table->string('type_operation')->default('auto')->after('solde');
                $table->index(['entreprise_id', 'type_operation']);
            });

            DB::table('caisses')
                ->whereNull('type_operation')
                ->update(['type_operation' => 'auto']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('caisses', 'type_operation')) {
            Schema::table('caisses', function (Blueprint $table) {
                $table->dropIndex(['entreprise_id', 'type_operation']);
                $table->dropColumn('type_operation');
            });
        }
    }
};

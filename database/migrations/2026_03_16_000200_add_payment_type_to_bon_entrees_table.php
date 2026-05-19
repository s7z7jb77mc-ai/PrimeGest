<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bon_entrees') && !Schema::hasColumn('bon_entrees', 'payment_type')) {
            Schema::table('bon_entrees', function (Blueprint $table) {
                $table->string('payment_type', 10)->default('cash')->after('date_bon');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bon_entrees') && Schema::hasColumn('bon_entrees', 'payment_type')) {
            Schema::table('bon_entrees', function (Blueprint $table) {
                $table->dropColumn('payment_type');
            });
        }
    }
};

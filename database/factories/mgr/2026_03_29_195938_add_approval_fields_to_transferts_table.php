<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transferts', function (Blueprint $table) {
            $table->unsignedBigInteger('approved_by')->nullable()->after('user_id');
            $table->dateTime('approved_at')->nullable()->after('approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transferts', function (Blueprint $table) {
            if (Schema::hasColumn('transferts', 'approved_by')) {
                $table->dropColumn('approved_by');
            }
            if (Schema::hasColumn('transferts', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
        });
    }
};

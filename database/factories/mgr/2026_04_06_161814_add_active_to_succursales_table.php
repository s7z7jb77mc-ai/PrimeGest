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
        if (!Schema::hasTable('succursales')) {
            return;
        }

        Schema::table('succursales', function (Blueprint $table) {
            if (!Schema::hasColumn('succursales', 'active')) {
                $table->boolean('active')->default(true)->after('manager_user_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('succursales')) {
            return;
        }

        Schema::table('succursales', function (Blueprint $table) {
            if (Schema::hasColumn('succursales', 'active')) {
                $table->dropColumn('active');
            }
        });
    }
};

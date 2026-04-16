<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employes')) {
            return;
        }

        Schema::table('employes', function (Blueprint $table) {
            if (!Schema::hasColumn('employes', 'succursale_id')) {
                $table->unsignedBigInteger('succursale_id')->nullable()->after('entreprise_id');
                $table->index('succursale_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('employes')) {
            return;
        }

        Schema::table('employes', function (Blueprint $table) {
            if (Schema::hasColumn('employes', 'succursale_id')) {
                $table->dropIndex(['succursale_id']);
                $table->dropColumn('succursale_id');
            }
        });
    }
};

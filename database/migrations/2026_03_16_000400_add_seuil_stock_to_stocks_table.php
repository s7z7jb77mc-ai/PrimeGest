<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stocks') && !Schema::hasColumn('stocks', 'seuil_stock')) {
            Schema::table('stocks', function (Blueprint $table) {
                $table->integer('seuil_stock')->default(0)->after('quantite');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stocks') && Schema::hasColumn('stocks', 'seuil_stock')) {
            Schema::table('stocks', function (Blueprint $table) {
                $table->dropColumn('seuil_stock');
            });
        }
    }
};

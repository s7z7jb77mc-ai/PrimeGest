<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mouvement_stocks') && !Schema::hasColumn('mouvement_stocks', 'payment_type')) {
            Schema::table('mouvement_stocks', function (Blueprint $table) {
                $table->string('payment_type', 10)->default('cash')->after('commentaire');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mouvement_stocks') && Schema::hasColumn('mouvement_stocks', 'payment_type')) {
            Schema::table('mouvement_stocks', function (Blueprint $table) {
                $table->dropColumn('payment_type');
            });
        }
    }
};

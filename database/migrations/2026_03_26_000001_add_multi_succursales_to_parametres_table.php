<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            if (!Schema::hasColumn('parametres', 'multi_succursales')) {
                $table->boolean('multi_succursales')->default(false)->after('message_remerciement');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            if (Schema::hasColumn('parametres', 'multi_succursales')) {
                $table->dropColumn('multi_succursales');
            }
        });
    }
};

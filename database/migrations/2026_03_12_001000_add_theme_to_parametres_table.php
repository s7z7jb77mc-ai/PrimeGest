<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            if (!Schema::hasColumn('parametres', 'theme')) {
                $table->string('theme')->default('light')->after('reduction_accordee');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            if (Schema::hasColumn('parametres', 'theme')) {
                $table->dropColumn('theme');
            }
        });
    }
};

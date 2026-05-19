<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            if (!Schema::hasColumn('parametres', 'reduction_accordee')) {
                $table->decimal('reduction_accordee', 5, 2)->default(0)->after('tva');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            if (Schema::hasColumn('parametres', 'reduction_accordee')) {
                $table->dropColumn('reduction_accordee');
            }
        });
    }
};

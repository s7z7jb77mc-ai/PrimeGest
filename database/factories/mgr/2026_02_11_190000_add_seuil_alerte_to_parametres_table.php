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
        Schema::table('parametres', function (Blueprint $table) {
            if (!Schema::hasColumn('parametres', 'seuil_alerte')) {
                $table->integer('seuil_alerte')->default(0)->after('message_remerciement');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            if (Schema::hasColumn('parametres', 'seuil_alerte')) {
                $table->dropColumn('seuil_alerte');
            }
        });
    }
};

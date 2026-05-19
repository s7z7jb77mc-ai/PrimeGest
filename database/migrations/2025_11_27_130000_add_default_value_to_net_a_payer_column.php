<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('fiches_de_paie', function (Blueprint $table) {
            // Add default value for net_a_payer column
            if (Schema::hasColumn('fiches_de_paie', 'net_a_payer')) {
                DB::statement('ALTER TABLE fiches_de_paie MODIFY net_a_payer DECIMAL(12, 2) DEFAULT 0');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('fiches_de_paie', function (Blueprint $table) {
            if (Schema::hasColumn('fiches_de_paie', 'net_a_payer')) {
                DB::statement('ALTER TABLE fiches_de_paie MODIFY net_a_payer DECIMAL(12, 2) NULL');
            }
        });
    }
};

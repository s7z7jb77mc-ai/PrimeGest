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
        Schema::table('fiches_de_paie', function (Blueprint $table) {
            // Add date_paie column if it doesn't exist
            if (!Schema::hasColumn('fiches_de_paie', 'date_paie')) {
                $table->date('date_paie')->nullable()->after('date_paiement');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fiches_de_paie', function (Blueprint $table) {
            if (Schema::hasColumn('fiches_de_paie', 'date_paie')) {
                $table->dropColumn('date_paie');
            }
        });
    }
};

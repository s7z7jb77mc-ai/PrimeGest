<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('caisses', function (Blueprint $table) {
            $table->decimal('entree', 15, 2)->default(0)->change();
            $table->decimal('sortie', 15, 2)->default(0)->change();
            $table->decimal('solde', 15, 2)->default(0)->change();
        });
    }
    public function down(): void {
        Schema::table('caisses', function (Blueprint $table) {
            $table->decimal('entree', 10, 2)->default(0)->change();
            $table->decimal('sortie', 10, 2)->default(0)->change();
            $table->decimal('solde', 10, 2)->default(0)->change();
        });
    }
};

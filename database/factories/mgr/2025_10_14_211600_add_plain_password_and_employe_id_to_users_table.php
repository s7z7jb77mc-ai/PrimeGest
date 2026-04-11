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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'plain_password')) {
                $table->string('plain_password')->nullable();
            }

            if (!Schema::hasColumn('users', 'employe_id')) {
                $table->unsignedBigInteger('employe_id')->nullable();
                $table->foreign('employe_id')->references('id')->on('employes')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'employe_id')) {
                $table->dropForeign(['employe_id']);
                $table->dropColumn('employe_id');
            }

            if (Schema::hasColumn('users', 'plain_password')) {
                $table->dropColumn('plain_password');
            }
        });
    }
};

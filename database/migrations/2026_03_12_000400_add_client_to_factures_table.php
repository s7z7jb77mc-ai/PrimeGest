<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            if (!Schema::hasColumn('factures', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->after('user_id');
                $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
            }
            if (!Schema::hasColumn('factures', 'client_nom')) {
                $table->string('client_nom')->nullable()->after('client_id');
            }
            if (!Schema::hasColumn('factures', 'client_telephone')) {
                $table->string('client_telephone')->nullable()->after('client_nom');
            }
        });
    }

    public function down(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            if (Schema::hasColumn('factures', 'client_telephone')) {
                $table->dropColumn('client_telephone');
            }
            if (Schema::hasColumn('factures', 'client_nom')) {
                $table->dropColumn('client_nom');
            }
            if (Schema::hasColumn('factures', 'client_id')) {
                $table->dropForeign(['client_id']);
                $table->dropColumn('client_id');
            }
        });
    }
};

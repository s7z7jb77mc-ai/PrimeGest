<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            if (!Schema::hasColumn('parametres', 'logo_path')) {
                $table->string('logo_path')->nullable()->after('message_remerciement');
            }
            if (!Schema::hasColumn('parametres', 'logo')) {
                $table->string('logo')->nullable()->after('logo_path');
            }
            if (!Schema::hasColumn('parametres', 'logo_position')) {
                $table->enum('logo_position', ['left', 'center', 'right'])->default('left')->after('logo_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            if (Schema::hasColumn('parametres', 'logo_position')) {
                $table->dropColumn('logo_position');
            }
            if (Schema::hasColumn('parametres', 'logo')) {
                $table->dropColumn('logo');
            }
            if (Schema::hasColumn('parametres', 'logo_path')) {
                $table->dropColumn('logo_path');
            }
        });
    }
};

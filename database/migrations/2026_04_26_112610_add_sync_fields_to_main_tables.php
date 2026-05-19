<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        $tables = ['produits','clients','fournisseurs','employes','mouvement_stocks','journals','factures'];
        
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) continue;
            
            Schema::table($table, function (Blueprint $t) {
                if (!Schema::hasColumn($t->getTable(), 'uuid'))
                    $t->uuid('uuid')->nullable()->unique()->after('id');
                if (!Schema::hasColumn($t->getTable(), 'sync_version'))
                    $t->unsignedBigInteger('sync_version')->default(0)->after('uuid');
                if (!Schema::hasColumn($t->getTable(), 'deleted_at'))
                    $t->softDeletes();
            });
        }
    }

    public function down(): void
    {
        $tables = ['produits','clients','fournisseurs','employes','mouvement_stocks','journals','factures'];
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) continue;
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['uuid','sync_version','deleted_at']);
            });
        }
    }
};

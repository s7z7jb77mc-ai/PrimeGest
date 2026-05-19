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
        if (!Schema::hasTable('users') || !Schema::hasTable('entreprises') || !Schema::hasTable('employes')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (!$this->foreignKeyExists('users_entreprise_id_foreign')) {
                $table->foreign('entreprise_id')->references('id')->on('entreprises')->onDelete('cascade');
            }
            if (!$this->foreignKeyExists('users_employe_id_foreign')) {
                $table->foreign('employe_id')->references('id')->on('employes')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if ($this->foreignKeyExists('users_entreprise_id_foreign')) {
                $table->dropForeign(['entreprise_id']);
            }
            if ($this->foreignKeyExists('users_employe_id_foreign')) {
                $table->dropForeign(['employe_id']);
            }
        });
    }

    private function foreignKeyExists(string $foreignKey): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return false;
        }

        $dbName = DB::getDatabaseName();
        $row = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $dbName)
            ->where('TABLE_NAME', 'users')
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->where('CONSTRAINT_NAME', $foreignKey)
            ->first();
        return (bool) $row;
    }
};

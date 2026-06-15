<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Correctif critique : si la FK entreprises.user_id → users.id existait avec
// onDelete('cascade'), supprimer un user supprimait toute son entreprise.
// On passe à SET NULL. La migration vérifie d'abord si la FK existe.
return new class extends Migration
{
    public function up(): void
    {
        // L'altération de FK (DROP FOREIGN KEY, information_schema) est
        // spécifique MySQL. SQLite (offline/CI) ne la supporte pas et gère
        // déjà les FK différemment → migration sans effet hors MySQL.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Supprimer la FK si elle existe (peu importe son nom)
        $this->dropFkIfExists('entreprises', 'user_id');

        Schema::table('entreprises', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->dropFkIfExists('entreprises', 'user_id');

        Schema::table('entreprises', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }

    private function dropFkIfExists(string $table, string $column): void
    {
        $fks = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [$table, $column]);

        foreach ($fks as $fk) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
        }
    }
};

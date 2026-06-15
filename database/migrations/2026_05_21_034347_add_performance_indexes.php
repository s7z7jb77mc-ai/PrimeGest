<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // mouvement_stocks: dashboard monthly/daily GROUP BY queries
        if (Schema::hasTable('mouvement_stocks')) {
            Schema::table('mouvement_stocks', function (Blueprint $table) {
                if (! $this->hasIndex('mouvement_stocks', 'ms_eid_type_created')) {
                    $table->index(['entreprise_id', 'type', 'created_at'], 'ms_eid_type_created');
                }
                if (! $this->hasIndex('mouvement_stocks', 'ms_eid_succursale_type')) {
                    $table->index(['entreprise_id', 'succursale_id', 'type'], 'ms_eid_succursale_type');
                }
            });
        }

        // caisses: SUM sortie/entree by entreprise+succursale
        if (Schema::hasTable('caisses')) {
            Schema::table('caisses', function (Blueprint $table) {
                if (! $this->hasIndex('caisses', 'caisses_eid_succursale')) {
                    $table->index(['entreprise_id', 'succursale_id'], 'caisses_eid_succursale');
                }
            });
        }

        // journals: recent activities ORDER BY dateHeure_operation
        if (Schema::hasTable('journals')) {
            Schema::table('journals', function (Blueprint $table) {
                if (! $this->hasIndex('journals', 'journals_eid_date')) {
                    $table->index(['entreprise_id', 'dateHeure_operation'], 'journals_eid_date');
                }
            });
        }

        // factures: pending count by statut
        if (Schema::hasTable('factures')) {
            Schema::table('factures', function (Blueprint $table) {
                if (! $this->hasIndex('factures', 'factures_eid_statut')) {
                    $table->index(['entreprise_id', 'statut'], 'factures_eid_statut');
                }
            });
        }

        // users: entreprise_id lookup (used in many scopes)
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! $this->hasIndex('users', 'users_entreprise_id_index')) {
                    $table->index(['entreprise_id'], 'users_entreprise_id_index');
                }
            });
        }

        // stocks: value SUM by entreprise+succursale
        if (Schema::hasTable('stocks')) {
            Schema::table('stocks', function (Blueprint $table) {
                if (! $this->hasIndex('stocks', 'stocks_eid_succursale')) {
                    $table->index(['entreprise_id', 'succursale_id'], 'stocks_eid_succursale');
                }
            });
        }
    }

    public function down(): void
    {
        $drops = [
            'mouvement_stocks' => ['ms_eid_type_created', 'ms_eid_succursale_type'],
            'caisses'          => ['caisses_eid_succursale'],
            'journals'         => ['journals_eid_date'],
            'factures'         => ['factures_eid_statut'],
            'users'            => ['users_entreprise_id_index'],
            'stocks'           => ['stocks_eid_succursale'],
        ];

        foreach ($drops as $table => $indexes) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $bp) use ($indexes) {
                    foreach ($indexes as $idx) {
                        if ($this->hasIndex($bp->getTable(), $idx)) {
                            $bp->dropIndex($idx);
                        }
                    }
                });
            }
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        // Portable MySQL/SQLite (SHOW INDEX est spécifique MySQL).
        return collect(Schema::getIndexes($table))
            ->contains(fn ($i) => ($i['name'] ?? '') === $name);
    }
};

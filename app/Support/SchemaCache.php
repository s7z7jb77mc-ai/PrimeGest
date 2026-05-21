<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Cache les résultats de Schema::hasColumn() pour éviter les requêtes
 * répétées sur information_schema.COLUMNS à chaque requête HTTP.
 *
 * Deux niveaux : tableau statique (durée de vie du process) + Redis (24h).
 * Invalider avec : php artisan cache:forget schema_col.*  ou cache:clear
 */
class SchemaCache
{
    private static array $memory = [];

    public static function hasColumn(string $table, string $column): bool
    {
        $key = "{$table}.{$column}";

        if (isset(static::$memory[$key])) {
            return static::$memory[$key];
        }

        $result = Cache::remember(
            "schema_col.{$key}",
            now()->addHours(24),
            static fn () => Schema::hasColumn($table, $column)
        );

        static::$memory[$key] = (bool) $result;

        return static::$memory[$key];
    }

    public static function hasTable(string $table): bool
    {
        $key = "table.{$table}";

        if (isset(static::$memory[$key])) {
            return static::$memory[$key];
        }

        $result = Cache::remember(
            "schema_col.{$key}",
            now()->addHours(24),
            static fn () => Schema::hasTable($table)
        );

        static::$memory[$key] = (bool) $result;

        return static::$memory[$key];
    }

    public static function flush(): void
    {
        static::$memory = [];
    }
}

<?php

declare(strict_types=1);

use App\Support\SchemaCache;

if (! function_exists('schema_has_column')) {
    function schema_has_column(string $table, string $column): bool
    {
        return SchemaCache::hasColumn($table, $column);
    }
}

if (! function_exists('schema_has_table')) {
    function schema_has_table(string $table): bool
    {
        return SchemaCache::hasTable($table);
    }
}

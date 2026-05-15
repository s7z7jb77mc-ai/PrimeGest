<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SuccursaleContext
{
    public static function currentId(): ?int
    {
        $value = session('succursale_id');

        return $value ? (int) $value : null;
    }

    public static function hasColumn(string $table): bool
    {
        static $cache = [];

        if (! array_key_exists($table, $cache)) {
            try {
                $cache[$table] = Schema::hasTable($table) && Schema::hasColumn($table, 'succursale_id');
            } catch (\Throwable) {
                $cache[$table] = false;
            }
        }

        return $cache[$table];
    }

    public static function shouldApplyTo(string $table): bool
    {
        return self::currentId() !== null && self::hasColumn($table);
    }

    public static function applyToQuery(Builder $builder, ?int $succursaleId = null): Builder
    {
        $model = $builder->getModel();
        $table = $model->getTable();
        $effectiveSuccursaleId = $succursaleId ?? self::currentId();

        if ($effectiveSuccursaleId === null || ! self::hasColumn($table)) {
            return $builder;
        }

        return $builder->where($table.'.succursale_id', $effectiveSuccursaleId);
    }

    public static function forWrite(Model $model, ?int $explicitSuccursaleId = null): ?int
    {
        if (! self::hasColumn($model->getTable())) {
            return null;
        }

        if ($explicitSuccursaleId !== null) {
            return $explicitSuccursaleId;
        }

        return self::currentId();
    }

    public static function withoutScope(string $modelClass): Builder
    {
        /** @var Model $model */
        $model = new $modelClass;

        return $model->newQueryWithoutScopes();
    }
}

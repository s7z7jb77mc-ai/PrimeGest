<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use App\Support\SuccursaleContext;

trait HasSuccursaleScope
{
    protected static function bootHasSuccursaleScope(): void
    {
        static::addGlobalScope('succursale', function (Builder $builder) {
            $model = $builder->getModel();
            if (!SuccursaleContext::shouldApplyTo($model->getTable())) {
                return;
            }

            SuccursaleContext::applyToQuery($builder);
        });

        static::creating(function ($model) {
            $succursaleId = SuccursaleContext::forWrite($model);

            if ($succursaleId !== null && empty($model->succursale_id)) {
                $model->succursale_id = $succursaleId;
            }
        });
    }

    protected static function hasSuccursaleColumn(string $table): bool
    {
        return SuccursaleContext::hasColumn($table);
    }
}

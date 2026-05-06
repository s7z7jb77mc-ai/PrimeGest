<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\SyncQueue;

trait SyncObservable
{
    protected static function bootSyncObservable(): void
    {
        static::created(function ($model) {
            static::addToSyncQueue($model, 'insert');
        });

        static::updated(function ($model) {
            // Ne pas re-synchroniser si seul le champ 'synced' a changé
            if ($model->wasChanged(['synced'])) {
                return;
            }
            static::addToSyncQueue($model, 'update');
        });

        static::deleted(function ($model) {
            static::addToSyncQueue($model, 'delete');
        });
    }

    protected static function addToSyncQueue($model, string $operation): void
    {
        $payload = $operation === 'delete'
            ? ['uuid' => $model->uuid]
            : $model->toArray();

        unset($payload['id']);

        SyncQueue::create([
            'device_id' => config('app.device_id', 'unknown_device'),
            'table_name' => $model->getTable(),
            'record_uuid' => $model->uuid,
            'succursale_uuid' => $model->succursale?->uuid ?? null,
            'entreprise_uuid' => $model->entreprise?->uuid ?? null,
            'operation' => $operation,
            'payload' => $payload,
            'checksum' => hash('sha256', json_encode($payload)),
            'status' => 'pending',
        ]);
    }
}

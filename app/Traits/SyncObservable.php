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
        try {
            $payload = $operation === 'delete'
                ? ['uuid' => $model->uuid]
                : $model->toArray();

            unset($payload['id']);

            $succursaleUuid = null;
            $entrepriseUuid = null;

            try {
                $succursaleUuid = $model->getRelationValue('succursale')?->uuid
                    ?? ($model->succursale_id ? \App\Models\Succursale::find($model->succursale_id)?->uuid : null);
            } catch (\Throwable) {
            }

            try {
                $entrepriseUuid = $model->getRelationValue('entreprise')?->uuid
                    ?? ($model->entreprise_id ? \App\Models\Entreprise::find($model->entreprise_id)?->uuid : null);
            } catch (\Throwable) {
            }

            SyncQueue::create([
                'device_id' => config('app.device_id', 'unknown_device'),
                'table_name' => $model->getTable(),
                'record_uuid' => $model->uuid,
                'succursale_uuid' => $succursaleUuid,
                'entreprise_uuid' => $entrepriseUuid,
                'operation' => $operation,
                'payload' => $payload,
                'checksum' => hash('sha256', json_encode($payload)),
                'status' => 'pending',
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('SyncObservable: échec enqueue', [
                'table' => $model->getTable(),
                'uuid' => $model->uuid ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

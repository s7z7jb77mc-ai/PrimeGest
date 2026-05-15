<?php

namespace App\Services;

use App\Models\SyncQueue;
use Illuminate\Support\Str;

class SyncQueueService
{
    public static function enqueue(
        string $tableName,
        string $operation,
        array $payload,
        ?string $recordUuid = null,
        ?string $deviceId = null,
        ?string $succursaleUuid = null,
        ?string $entrepriseUuid = null
    ): SyncQueue {
        unset($payload['id']);

        $recordUuid ??= $payload['uuid'] ?? (string) Str::uuid();

        return SyncQueue::create([
            'device_id' => $deviceId ?? config('app.device_id', 'unknown_device'),
            'table_name' => $tableName,
            'record_uuid' => $recordUuid,
            'succursale_uuid' => $succursaleUuid,
            'entreprise_uuid' => $entrepriseUuid,
            'operation' => $operation,
            'payload' => $payload,
            'checksum' => hash('sha256', json_encode($payload)),
            'status' => 'pending',
            'attempts' => 0,
        ]);
    }
}

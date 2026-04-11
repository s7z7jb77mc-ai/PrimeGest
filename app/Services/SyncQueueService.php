<?php

namespace App\Services;

use App\Models\SyncQueue;
use Illuminate\Support\Str;

class SyncQueueService
{
    public static function enqueue(string $entity, string $operation, array $payload, ?string $deviceId = null): SyncQueue
    {
        return SyncQueue::create([
            'uuid' => (string) Str::uuid(),
            'entity' => $entity,
            'operation' => $operation,
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
            'device_id' => $deviceId,
        ]);
    }
}

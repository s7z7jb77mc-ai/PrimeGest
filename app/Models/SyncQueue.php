<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncQueue extends Model
{
    protected $connection = 'local';
    protected $table = 'sync_queue';

    protected $fillable = [
        'uuid',
        'entity',
        'operation',
        'payload',
        'status',
        'attempts',
        'device_id',
        'last_error',
        'synced_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'synced_at' => 'datetime',
    ];
}

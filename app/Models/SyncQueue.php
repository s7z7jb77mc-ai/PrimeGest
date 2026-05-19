<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// SyncQueue ne doit PAS utiliser SyncObservable — cela créerait une boucle infinie.
class SyncQueue extends Model
{
    public $timestamps = false;

    protected $table = 'sync_queue';

    protected $fillable = [
        'device_id',
        'table_name',
        'record_uuid',
        'succursale_uuid',
        'entreprise_uuid',
        'operation',
        'payload',
        'checksum',
        'status',
        'attempts',
        'error_message',
        'synced_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'synced_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}

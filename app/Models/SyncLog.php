<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    use HasUuid;

    protected $table = 'sync_logs';

    protected $fillable = [
        'uuid',
        'entreprise_id', 'device_id', 'direction',
        'operations_count', 'conflicts_count',
    ];
}

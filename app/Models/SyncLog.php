<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $table = 'sync_logs';
    protected $fillable = [
        'entreprise_id','device_id','direction',
        'operations_count','conflicts_count'
    ];
}

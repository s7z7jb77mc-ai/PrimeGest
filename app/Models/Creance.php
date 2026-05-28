<?php

namespace App\Models;

use App\Models\Concerns\HasSuccursaleScope;
use App\Traits\HasUuid;
use App\Traits\SyncObservable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Creance extends Model
{
    use HasFactory, HasUuid, SyncObservable;
    use HasSuccursaleScope;

    protected $fillable = [
        'uuid',
        'entreprise_id',
        'succursale_id',
        'client_id',
        'montant_paye',
        'caisse_id',
        'sync_version',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}

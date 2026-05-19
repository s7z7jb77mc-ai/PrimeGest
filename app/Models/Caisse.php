<?php

namespace App\Models;

use App\Models\Concerns\HasSuccursaleScope;
use App\Traits\HasUuid;
use App\Traits\SyncObservable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Caisse extends Model
{
    use HasFactory, HasUuid, SyncObservable;
    use HasSuccursaleScope;

    protected $table = 'caisses';

    protected $fillable = [
        'entreprise_id',
        'succursale_id',
        'uuid',
        'date_operation',
        'description',
        'entree',
        'sortie',
        'solde',
        'type_operation',
        'sync_version',
    ];

    protected $casts = [
        'date_operation' => 'datetime',
        'sync_version' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }
}

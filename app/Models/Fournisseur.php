<?php

namespace App\Models;

use App\Models\Concerns\HasSuccursaleScope;
use App\Traits\HasUuid;
use App\Traits\SyncObservable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fournisseur extends Model
{
    use HasFactory, HasUuid, SyncObservable;
    use HasSuccursaleScope;

    protected $fillable = [
        'entreprise_id',
        'succursale_id',
        'nom_entreprise_fournisseur',
        'adresse',
        'dette',
        'reduction_pourcentage',
        'achat_mensuel',
        'reduction_obtenue',
    ];

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasSuccursaleScope;
use App\Traits\HasUuid;
use App\Traits\SyncObservable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BonEntreeLigne extends Model
{
    use HasFactory, HasUuid, SyncObservable;
    use HasSuccursaleScope;

    protected $fillable = [
        'bon_entree_id',
        'succursale_id',
        'produit_id',
        'quantite',
        'prix_unitaire',
        'total',
    ];

    public function bonEntree()
    {
        return $this->belongsTo(BonEntree::class, 'bon_entree_id');
    }

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }
}

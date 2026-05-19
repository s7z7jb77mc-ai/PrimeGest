<?php

namespace App\Models;

use App\Models\Concerns\HasSuccursaleScope;
use App\Traits\HasUuid;
use App\Traits\SyncObservable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    use HasFactory, HasUuid, SyncObservable;
    use HasSuccursaleScope;

    protected $fillable = [
        'entreprise_id',
        'succursale_id',
        'produit_id',
        'quantite',
        'prix_achat',
        'prix_vente',
        'total_achat',
        'total_vente',
        'seuil_stock',
    ];

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }

    // ✅ Relation manquante — nécessaire pour les alertes stock au dashboard central
    public function succursale()
    {
        return $this->belongsTo(Succursale::class);
    }
}

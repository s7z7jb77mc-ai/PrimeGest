<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasSuccursaleScope;

class Stock extends Model
{
    use HasFactory;
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
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasSuccursaleScope;

class BonEntreeLigne extends Model
{
    use HasFactory;
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

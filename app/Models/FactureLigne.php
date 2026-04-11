<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasSuccursaleScope;

class FactureLigne extends Model
{
    use HasFactory;
    use HasSuccursaleScope;

    protected $fillable = [
        'facture_id',
        'succursale_id',
        'produit_id',
        'designation',
        'quantite',
        'prix_ttc',
        'total',
    ];

    public function facture()
    {
        return $this->belongsTo(Facture::class);
    }

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }
    protected static function boot()
{
    parent::boot();

    static::creating(function ($ligne) {
        // Calcul automatique du total si non défini
        if (empty($ligne->total)) {
            $ligne->total = $ligne->quantite * $ligne->prix_ttc;
        }
    });
}

}

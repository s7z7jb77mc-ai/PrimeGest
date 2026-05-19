<?php

namespace App\Models;

use App\Models\Concerns\HasSuccursaleScope;
use App\Traits\HasUuid;
use App\Traits\SyncObservable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FactureLigne extends Model
{
    use HasFactory, HasUuid, SyncObservable;
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

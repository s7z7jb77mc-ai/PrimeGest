<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\SyncObservable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produit extends Model
{
    use HasFactory, HasUuid, SyncObservable;

    protected $fillable = [
        'entreprise_id',
        'nom',
        'prix_achat',
        'prix_vente',
    ];

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function stock()
    {
        return $this->hasOne(Stock::class, 'produit_id');
    }
}

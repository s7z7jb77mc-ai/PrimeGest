<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produit extends Model
{
    use HasFactory;

    protected $fillable = [
        'entreprise_id',
        'nom',
        'prix_achat',
        'prix_vente',
    ];

    /**
     * Un produit appartient à une entreprise.
     */
    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function stock()
    {
        return $this->hasOne(Stock::class, 'produit_id');
    }
}

<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasSuccursaleScope;

class MouvementStock extends Model
{
    use HasFactory;
    use HasSuccursaleScope;

    protected $fillable = [
        'entreprise_id',
        'succursale_id',
        'produit_id',
        'nom_produit',
        'type',
        'quantite',
        'prix_unitaire',
        'prix_total',
        'user_id',
        'commentaire',
        'payment_type',
        'date',
    ];

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}

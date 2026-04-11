<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasSuccursaleScope;

class Journal extends Model
{
    use HasFactory;
    use HasSuccursaleScope;

    protected $table = 'journals';

    protected $fillable = [
        'entreprise_id',
        'succursale_id',
        'produit_id',
        'dateHeure_operation',
        'type', // 'entree' ou 'sortie'
        'description',
        'montant',
        'user_id',
    ];

    // Relation avec l'entreprise
    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }

    // Relation avec le produit
    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }

    // Relation avec l'utilisateur
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

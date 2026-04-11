<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasSuccursaleScope;

class Fournisseur extends Model
{
    use HasFactory;
    use HasSuccursaleScope;

    protected $fillable = [
        'entreprise_id',
        'succursale_id',
        'nom_entreprise_fournisseur',
        'adresse',
        'dette',
        'reduction_pourcentage',
        'achat_mensuel',
        'reduction_obtenue',
    ];

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }
}

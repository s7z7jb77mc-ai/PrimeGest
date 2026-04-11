<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasSuccursaleScope;

class Client extends Model
{
    use HasFactory;
    use HasSuccursaleScope;

    protected $fillable = [
        'entreprise_id',
        'succursale_id',
        'nom_client',
        'numero_telephone',
        'adresse',
        'creance',
        'achat_mensuel',
        'reduction_accordee',
    ];

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }
}

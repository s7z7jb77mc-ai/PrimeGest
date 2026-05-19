<?php

namespace App\Models;

use App\Models\Concerns\HasSuccursaleScope;
use App\Traits\HasUuid;
use App\Traits\SyncObservable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employe extends Model
{
    use HasFactory, HasSuccursaleScope, HasUuid, SyncObservable;

    protected $fillable = [
        'entreprise_id',
        'succursale_id',
        'nom',
        'prenom',
        'email',
        'telephone',
        'poste',
        'salaire_base',
        'date_embauche',
        'statut',
    ];

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function succursale()
    {
        return $this->belongsTo(Succursale::class);
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function fiches_de_paie()
    {
        return $this->hasMany(FicheDePaie::class);
    }
}

<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\SyncObservable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FicheDePaie extends Model
{
    use HasFactory, HasUuid, SyncObservable;

    // 🔹 Indiquer explicitement le nom de la table
    protected $table = 'fiches_de_paie';

    protected $fillable = [
        'uuid',
        'entreprise_id',
        'employe_id',
        'mois',
        'annee',
        'salaire_brut',
        'salaire_base',
        'primes',
        'retenues',
        'salaire_net',
        'net_a_payer',
        'statut',
        'statut_paiement',
        'date_paiement',
        'date_paie',
        'sync_version',
    ];

    protected $casts = [
        'date_paiement' => 'datetime',
        'date_paie' => 'date',
    ];

    public function employe()
    {
        return $this->belongsTo(Employe::class);
    }

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }
}

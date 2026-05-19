<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rapport extends Model
{
    use HasFactory;

    protected $fillable = [
        'entreprise_id',
        'type', // 'journalier', 'hebdomadaire', 'mensuel'
        'date_debut',
        'date_fin',
        'contenu', // JSON avec les données du rapport
        'resume', // Résumé rapide
        'date_generation',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'date_generation' => 'datetime',
        'contenu' => 'array',
    ];

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByDateRange($query, $dateDebut, $dateFin)
    {
        return $query->whereBetween('date_debut', [$dateDebut, $dateFin]);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasSuccursaleScope;

class Facture extends Model
{
    use HasFactory;
    use HasSuccursaleScope;

    protected $fillable = [
        'entreprise_id',
        'succursale_id',
        'user_id',
        'client_id',
        'client_nom',
        'client_telephone',
        'numero',
        'total_montant',
        'prix_hors_tva',
        'total_ht',
        'total_tva',
        'total_ttc',
        'tva',
        'cash',
        'montant_paye',
        'echange',
        'statut',
        'date_facture',
    ];

    protected $casts = [
        'date_facture' => 'datetime',
    ];

    // ----------------------------------------------------------------
    // Relations
    // ----------------------------------------------------------------

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function succursale()
    {
        return $this->belongsTo(Succursale::class);
    }

    public function lignes()
    {
        return $this->hasMany(\App\Models\FactureLigne::class, 'facture_id');
    }

    // ----------------------------------------------------------------
    // Génération du numéro — TOUJOURS appeler dans une DB::transaction()
    // ----------------------------------------------------------------

    /**
     * Génère le prochain numéro de facture pour une entreprise donnée.
     *
     * ⚠️  Cette méthode DOIT être appelée à l'intérieur d'une transaction
     *     DB::transaction() active. Le lockForUpdate() garantit qu'aucune
     *     autre requête concurrente ne peut lire la même dernière facture
     *     et donc générer le même numéro.
     *
     * withoutGlobalScopes() est indispensable : il désactive HasSuccursaleScope
     * qui sinon cacherait les factures des autres succursales et ferait
     * repartir le compteur à FAC-0001 pour chaque nouvelle succursale.
     *
     * Usage :
     *   DB::transaction(function () use ($entrepriseId) {
     *       $numero  = Facture::genererNumero($entrepriseId);
     *       $facture = Facture::create([..., 'numero' => $numero]);
     *   });
     */
    public static function genererNumero(int $entrepriseId): string
    {
        // withoutGlobalScopes() ignore HasSuccursaleScope et tout autre scope
        // global → on voit TOUTES les factures de l'entreprise, toutes
        // succursales confondues, ce qui garantit une séquence unique.
        $last = self::withoutGlobalScopes()
            ->where('entreprise_id', $entrepriseId)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        $nextNumber = 1;

        if ($last && $last->numero && preg_match('/(\d+)$/', $last->numero, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        }

        return 'FAC-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
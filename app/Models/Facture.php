<?php

namespace App\Models;

use App\Models\Concerns\HasSuccursaleScope;
use App\Traits\HasUuid;
use App\Traits\SyncObservable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Facture extends Model
{
    use HasFactory, HasUuid, SyncObservable;
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
        // Verrouiller la ligne entreprise (toujours existante) pour sérialiser
        // les inserts concurrents — lockForUpdate() sur la dernière facture ne
        // protège pas le cas "table vide" (pas de ligne à verrouiller).
        DB::table('entreprises')->where('id', $entrepriseId)->lockForUpdate()->first();

        $last = self::withoutGlobalScopes()
            ->where('entreprise_id', $entrepriseId)
            ->orderByDesc('id')
            ->first();

        $nextNumber = 1;

        if ($last && $last->numero && preg_match('/(\d+)$/', $last->numero, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        }

        return 'FAC-'.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}

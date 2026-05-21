<?php

namespace App\Services;

use App\Models\MouvementStock;
use App\Models\Produit;
use App\Models\Stock;
use App\Support\SuccursaleContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MouvementStockService
{
    public static function normalizeType(string $type): string
    {
        return $type === 'entrée' ? 'entree' : $type;
    }

    public function record(
        int $entrepriseId,
        Produit $produit,
        string $type,
        int $quantite,
        ?float $prixUnitaire,
        ?string $commentaire = null,
        ?int $userId = null,
        ?string $paymentType = 'cash',
        ?int $succursaleId = null
    ): MouvementStock {
        $prixUnitaireFinal = $prixUnitaire ?? ($type === 'entree' ? $produit->prix_achat : $produit->prix_vente);
        $prixTotal = $quantite * $prixUnitaireFinal;
        $effectiveSuccursaleId = $succursaleId ?? SuccursaleContext::currentId();
        $hasMouvements = schema_has_column('mouvement_stocks', 'succursale_id');
        $hasStocks = schema_has_column('stocks', 'succursale_id');

        $payload = [
            'entreprise_id' => $entrepriseId,
            'produit_id' => $produit->id,
            'type' => $this->resolveMouvementType($type),
            'quantite' => $quantite,
            'prix_unitaire' => $prixUnitaireFinal,
            'prix_total' => $prixTotal,
            'user_id' => $userId ?? auth()->id(),
            'commentaire' => $commentaire,
        ];

        if ($hasMouvements) {
            $payload['succursale_id'] = $effectiveSuccursaleId;
        }

        if (schema_has_column('mouvement_stocks', 'nom_produit')) {
            $payload['nom_produit'] = $produit->nom ?? $produit->designation ?? 'Produit';
        }

        if (schema_has_column('mouvement_stocks', 'payment_type')) {
            $payload['payment_type'] = $paymentType ?: 'cash';
        }

        return DB::transaction(function () use ($entrepriseId, $produit, $type, $quantite, $prixUnitaire, $payload, $hasStocks, $effectiveSuccursaleId) {
            $mouvement = SuccursaleContext::withoutScope(MouvementStock::class)->create($payload);

            $stockKey = [
                'entreprise_id' => $entrepriseId,
                'produit_id' => $produit->id,
            ];

            if ($hasStocks) {
                $stockKey['succursale_id'] = $effectiveSuccursaleId;
            }

            $stock = SuccursaleContext::withoutScope(Stock::class)->firstOrCreate(
                $stockKey,
                [
                    'quantite' => 0,
                    'prix_achat' => $produit->prix_achat,
                    'prix_vente' => $produit->prix_vente,
                    'total_achat' => 0,
                    'total_vente' => 0,
                ]
            );

            if ($type === 'entree') {
                $stock->quantite += $quantite;
                if ($prixUnitaire !== null) {
                    $stock->prix_achat = $prixUnitaire;
                }
            } else {
                if ((float) $stock->quantite < $quantite) {
                    throw ValidationException::withMessages([
                        'quantite' => 'Quantité en stock insuffisante pour ce produit.',
                    ]);
                }

                $stock->quantite -= $quantite;
            }

            $stock->prix_vente = $produit->prix_vente;
            $stock->total_achat = $stock->quantite * ($stock->prix_achat ?? 0);
            $stock->total_vente = $stock->quantite * ($stock->prix_vente ?? 0);
            $stock->save();

            return $mouvement;
        });
    }

    private function resolveMouvementType(string $type): string
    {
        return self::normalizeType($type);
    }
}

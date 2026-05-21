<?php

namespace App\Services;

use App\Models\Archive;
use App\Models\Journal;
use App\Models\MouvementStock;

class MouvementStockWorkflowService
{
    public function handleCreated(MouvementStock $mouvement): void
    {
        $type = MouvementStockService::normalizeType((string) $mouvement->type);

        if (($mouvement->payment_type ?? null) === 'transfer') {
            $this->archive($mouvement);

            return;
        }

        if ($type === 'entree') {
            $this->handleEntree($mouvement);
        } elseif ($type === 'sortie') {
            $this->handleSortie($mouvement);
        }

        $this->archive($mouvement);
    }

    private function handleEntree(MouvementStock $mouvement): void
    {
        $produit = $mouvement->produit;
        $montantTotal = $mouvement->quantite * $mouvement->prix_unitaire;
        $isCredit = ($mouvement->payment_type ?? 'cash') === 'credit';
        $isReduction = ($mouvement->payment_type ?? 'cash') === 'reduction';

        $label = $isReduction ? 'Achat (réduction)' : ($isCredit ? 'Achat à crédit' : 'Achat');
        $journalData = [
            'entreprise_id' => $mouvement->entreprise_id,
            'produit_id' => $mouvement->produit_id,
            'user_id' => $mouvement->user_id,
            'dateHeure_operation' => now()->toDateTimeString(),
            'type' => 'sortie',
            'description' => $label . " : {$produit->nom} - Quantité: {$mouvement->quantite}",
            'montant' => $montantTotal,
        ];
        if (schema_has_column('journals', 'succursale_id')) {
            $journalData['succursale_id'] = $mouvement->succursale_id;
        }
        Journal::create($journalData);

        if ($isCredit || $isReduction) {
            return;
        }

        $data = [
            'entreprise_id' => $mouvement->entreprise_id,
            'description' => "Achat produit : {$produit->nom}",
            'date_operation' => now(),
            'entree' => 0,
            'sortie' => $montantTotal,
        ];

        if (schema_has_column('caisses', 'type_operation')) {
            $data['type_operation'] = 'auto';
        }
        if (schema_has_column('caisses', 'succursale_id')) {
            $data['succursale_id'] = $mouvement->succursale_id;
        }

        CaisseService::createOperation($data);
    }

    private function handleSortie(MouvementStock $mouvement): void
    {
        $produit = $mouvement->produit;
        $montantTotal = $mouvement->quantite * $mouvement->prix_unitaire;
        $isCredit = ($mouvement->payment_type ?? 'cash') === 'credit';
        $isReduction = ($mouvement->payment_type ?? 'cash') === 'reduction';

        $label = $isReduction ? 'Vente (réduction)' : ($isCredit ? 'Vente à crédit' : 'Vente');
        $journalData = [
            'entreprise_id' => $mouvement->entreprise_id,
            'produit_id' => $mouvement->produit_id,
            'user_id' => $mouvement->user_id,
            'dateHeure_operation' => now()->toDateTimeString(),
            'type' => 'entree',
            'description' => $label . " : {$produit->nom} - Quantité: {$mouvement->quantite}",
            'montant' => $montantTotal,
        ];
        if (schema_has_column('journals', 'succursale_id')) {
            $journalData['succursale_id'] = $mouvement->succursale_id;
        }
        Journal::create($journalData);

        if ($isCredit || $isReduction) {
            return;
        }

        $data = [
            'entreprise_id' => $mouvement->entreprise_id,
            'description' => "Vente produit : {$produit->nom}",
            'date_operation' => now(),
            'entree' => $montantTotal,
            'sortie' => 0,
        ];

        if (schema_has_column('caisses', 'type_operation')) {
            $data['type_operation'] = 'auto';
        }
        if (schema_has_column('caisses', 'succursale_id')) {
            $data['succursale_id'] = $mouvement->succursale_id;
        }

        CaisseService::createOperation($data);
    }

    private function archive(MouvementStock $mouvement): void
    {
        $archiveWhere = [
            'entreprise_id' => $mouvement->entreprise_id,
            'type' => 'mouvement_stock',
            'reference_id' => (string) $mouvement->id,
        ];
        if (schema_has_column('archives', 'succursale_id')) {
            $archiveWhere['succursale_id'] = $mouvement->succursale_id;
        }

        Archive::firstOrCreate(
            $archiveWhere,
            [
                'date_archive' => $mouvement->created_at->toDateString(),
                'payload' => [
                    'created_at' => $mouvement->created_at,
                    'type' => MouvementStockService::normalizeType((string) $mouvement->type),
                    'produit_id' => $mouvement->produit_id,
                    'quantite' => $mouvement->quantite,
                    'prix_unitaire' => $mouvement->prix_unitaire,
                    'prix_total' => $mouvement->prix_total,
                    'commentaire' => $mouvement->commentaire,
                    'user_id' => $mouvement->user_id,
                ],
            ]
        );
    }
}

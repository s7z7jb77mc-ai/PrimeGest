<?php

namespace App\Observers;

use App\Models\FicheDePaie;
use App\Models\Journal;
use App\Models\Caisse;
use App\Services\CaisseService;

class FicheDePaieObserver
{
    /**
     * Handle the FicheDePaie "updated" event.
     * Lorsque la fiche de paye est confirmée (statut_paiement = 'payee'),
     * déclenche les opérations dans Journal et Caisse
     */
    public function updated(FicheDePaie $ficheDePaie): void
    {
        // Vérifier si le statut a changé à 'payee'
        if ($ficheDePaie->isDirty('statut_paiement') && $ficheDePaie->statut_paiement === 'payee') {
            $this->enregistrerPaiement($ficheDePaie);
        }
    }

    /**
     * Enregistrer le paiement dans Journal et Caisse
     */
    private function enregistrerPaiement(FicheDePaie $ficheDePaie): void
    {
        $employe = $ficheDePaie->employe;
        $entrepriseId = $ficheDePaie->entreprise_id ?? $employe->entreprise_id;
        $montantPaiement = $ficheDePaie->net_a_payer ?? $ficheDePaie->salaire_net ?? 0;

        // Vérifier que la transaction n'a pas déjà été enregistrée
        $existant = Caisse::where('entreprise_id', $entrepriseId)
            ->where('description', 'LIKE', '%Paiement salaire employé : ' . $employe->nom . '%')
            ->where('sortie', $montantPaiement)
            ->whereDate('created_at', now()->toDateString())
            ->first();

        if ($existant) {
            return; // Éviter les doublons
        }

        // Opération dans la Caisse : diminution du solde (entrée = 0, sortie = montant)
        $data = [
            'entreprise_id' => $entrepriseId,
            'date_operation' => $ficheDePaie->date_paiement ?? now(),
            'description' => "Paiement salaire employé : {$employe->nom} ({$ficheDePaie->mois}) - Fiche #" . $ficheDePaie->id,
            'entree' => 0,
            'sortie' => $montantPaiement,
        ];

        if (schema_has_column('caisses', 'type_operation')) {
            $data['type_operation'] = 'auto';
        }

        CaisseService::createOperation($data);

        // Opération dans le Journal : paiement salaire
        Journal::create([
            'entreprise_id' => $entrepriseId,
            'type' => 'sortie',
            'description' => "Paiement salaire : {$employe->nom} ({$ficheDePaie->mois}) - Fiche #" . $ficheDePaie->id,
            'montant' => $montantPaiement,
        ]);
    }

    /**
     * Handle the FicheDePaie "created" event.
     */
    public function created(FicheDePaie $ficheDePaie): void
    {
        //
    }

    /**
     * Handle the FicheDePaie "deleted" event.
     */
    public function deleted(FicheDePaie $ficheDePaie): void
    {
        //
    }

    /**
     * Handle the FicheDePaie "restored" event.
     */
    public function restored(FicheDePaie $ficheDePaie): void
    {
        //
    }

    /**
     * Handle the FicheDePaie "force deleted" event.
     */
    public function forceDeleted(FicheDePaie $ficheDePaie): void
    {
        //
    }
}

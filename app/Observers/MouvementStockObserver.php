<?php

namespace App\Observers;

use App\Models\MouvementStock;
use App\Services\MouvementStockWorkflowService;

class MouvementStockObserver
{
    public function __construct(private MouvementStockWorkflowService $workflow)
    {
    }

    /**
     * Handle the MouvementStock "created" event.
     * Déclenche les opérations automatiques selon le type (entrée ou sortie)
     */
    public function created(MouvementStock $mouvement): void
    {
        $this->workflow->handleCreated($mouvement);
    }

    /**
     * Handle the MouvementStock "updated" event.
     */
    public function updated(MouvementStock $mouvement): void
    {
        //
    }

    /**
     * Handle the MouvementStock "deleted" event.
     */
    public function deleted(MouvementStock $mouvement): void
    {
        //
    }

    /**
     * Handle the MouvementStock "restored" event.
     */
    public function restored(MouvementStock $mouvement): void
    {
        //
    }

    /**
     * Handle the MouvementStock "force deleted" event.
     */
    public function forceDeleted(MouvementStock $mouvement): void
    {
        //
    }
}

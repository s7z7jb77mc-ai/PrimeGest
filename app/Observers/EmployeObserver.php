<?php

namespace App\Observers;

use App\Models\Employe;

class EmployeObserver
{
    /**
     * Handle the Employe "created" event.
     * Désactivé: la fiche de paie est créée manuellement.
     */
    public function created(Employe $employe): void
    {
        //
    }

    /**
     * Handle the Employe "updated" event.
     */
    public function updated(Employe $employe): void
    {
        //
    }

    /**
     * Handle the Employe "deleted" event.
     */
    public function deleted(Employe $employe): void
    {
        //
    }

    /**
     * Handle the Employe "restored" event.
     */
    public function restored(Employe $employe): void
    {
        //
    }

    /**
     * Handle the Employe "force deleted" event.
     */
    public function forceDeleted(Employe $employe): void
    {
        //
    }
}

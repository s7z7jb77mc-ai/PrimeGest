<?php

namespace App\Policies;

use App\Models\Fournisseur;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FournisseurPolicy
{
    use HandlesAuthorization;

    public function update(User $user, Fournisseur $fournisseur): bool
    {
        if ($user->entreprise_id !== $fournisseur->entreprise_id) {
            return false;
        }
        return $user->isSuperAdmin() || $user->isManagerOfCurrentSuccursale();
    }

    public function delete(User $user, Fournisseur $fournisseur): bool
    {
        return $this->update($user, $fournisseur);
    }
}

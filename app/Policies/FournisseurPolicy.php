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
        return $user->isSuperAdmin() && $user->entreprise_id === $fournisseur->entreprise_id;
    }

    public function delete(User $user, Fournisseur $fournisseur): bool
    {
        return $this->update($user, $fournisseur);
    }
}

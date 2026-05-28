<?php

namespace App\Policies;

use App\Models\Produit;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProduitPolicy
{
    use HandlesAuthorization;

    public function update(User $user, Produit $produit): bool
    {
        if ($user->entreprise_id !== $produit->entreprise_id) {
            return false;
        }
        return $user->isSuperAdmin() || $user->isManagerOfCurrentSuccursale();
    }

    public function delete(User $user, Produit $produit): bool
    {
        return $this->update($user, $produit);
    }
}

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
        return $user->isSuperAdmin() && $user->entreprise_id === $produit->entreprise_id;
    }

    public function delete(User $user, Produit $produit): bool
    {
        return $this->update($user, $produit);
    }
}

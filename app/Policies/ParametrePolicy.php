<?php

namespace App\Policies;

use App\Models\Parametre;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ParametrePolicy
{
    use HandlesAuthorization;

    public function update(User $user, Parametre $parametre): bool
    {
        return $user->isSuperAdmin() && $user->entreprise_id === $parametre->entreprise_id;
    }
}

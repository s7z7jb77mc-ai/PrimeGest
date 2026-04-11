<?php

namespace App\Policies;

use App\Models\Employe;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployePolicy
{
    use HandlesAuthorization;

    public function update(User $user, Employe $employe): bool
    {
        return $user->isSuperAdmin() && $user->entreprise_id === $employe->entreprise_id;
    }

    public function delete(User $user, Employe $employe): bool
    {
        return $this->update($user, $employe);
    }
}

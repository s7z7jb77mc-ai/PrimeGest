<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function update(User $authUser, User $user): bool
    {
        if ($authUser->entreprise_id !== $user->entreprise_id) {
            return false;
        }
        return $authUser->isSuperAdmin() || $authUser->isManagerOfCurrentSuccursale();
    }

    public function delete(User $authUser, User $user): bool
    {
        return $this->update($authUser, $user);
    }
}

<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function update(User $authUser, User $user): bool
    {
        return $authUser->isSuperAdmin() && $authUser->entreprise_id === $user->entreprise_id;
    }

    public function delete(User $authUser, User $user): bool
    {
        return $this->update($authUser, $user);
    }
}

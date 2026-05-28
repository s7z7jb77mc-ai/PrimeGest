<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientPolicy
{
    use HandlesAuthorization;

    public function update(User $user, Client $client): bool
    {
        if ($user->entreprise_id !== $client->entreprise_id) {
            return false;
        }
        return $user->isSuperAdmin() || $user->isManagerOfCurrentSuccursale();
    }

    public function delete(User $user, Client $client): bool
    {
        return $this->update($user, $client);
    }
}

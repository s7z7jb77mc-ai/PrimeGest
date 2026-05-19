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
        return $user->isSuperAdmin() && $user->entreprise_id === $client->entreprise_id;
    }

    public function delete(User $user, Client $client): bool
    {
        return $this->update($user, $client);
    }
}

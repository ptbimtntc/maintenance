<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\ShiftComm;
use App\Models\User;

class ShiftCommPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->hasRole(RoleName::Guest->value);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /** Anyone can read an entry; only its author or an Administrator can change it. */
    public function update(User $user, ShiftComm $comm): bool
    {
        return $this->viewAny($user)
            && ($user->id === $comm->created_by || $user->hasRole(RoleName::Administrator->value));
    }

    public function delete(User $user, ShiftComm $comm): bool
    {
        return $this->update($user, $comm);
    }
}

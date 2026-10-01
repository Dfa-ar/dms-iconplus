<?php

namespace App\Policies;

use App\Models\Officer;
use App\Models\User;

class OfficerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role?->name === 'admin';
    }

    public function view(User $user, Officer $officer): bool
    {
        return $user->role?->name === 'admin';
    }

    public function create(User $user): bool
    {
        return $user->role?->name === 'admin';
    }

    public function update(User $user, Officer $officer): bool
    {
        return $user->role?->name === 'admin';
    }

    public function delete(User $user, Officer $officer): bool
    {
        return $user->role?->name === 'admin';
    }
}

<?php

namespace App\Policies;

use App\Models\User;

class MasterDataPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('manage master data');
    }

    public function view(User $user, $model): bool
    {
        return $user->hasPermissionTo('manage master data');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage master data');
    }

    public function update(User $user, $model): bool
    {
        return $user->hasPermissionTo('manage master data');
    }

    public function delete(User $user, $model): bool
    {
        return $user->hasPermissionTo('manage master data');
    }
}

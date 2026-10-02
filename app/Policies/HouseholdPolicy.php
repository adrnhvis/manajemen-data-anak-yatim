<?php

namespace App\Policies;

use App\Models\Household;
use App\Models\Child;
use App\Models\User;

class HouseholdPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view children')
            && ($user->hasRole('super_admin') || (bool) $user->organization_unit_id);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Household $household): bool
    {
        return $user->hasPermissionTo('view children')
            && Household::query()->forUser($user)->whereKey($household->getKey())->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Child $child): bool
    {
        return $user->hasPermissionTo('create children') && $user->canAccessChild($child);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Household $household): bool
    {
        return $user->hasPermissionTo('update children')
            && Household::query()->forUser($user)->whereKey($household->getKey())->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Household $household): bool
    {
        return $user->hasPermissionTo('delete children')
            && Household::query()->forUser($user)->whereKey($household->getKey())->exists();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Household $household): bool
    {
        return $user->hasPermissionTo('delete children')
            && Household::query()->forUser($user)->whereKey($household->getKey())->exists();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Household $household): bool
    {
        return $user->hasPermissionTo('delete children')
            && Household::query()->forUser($user)->whereKey($household->getKey())->exists();
    }
}

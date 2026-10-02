<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Child;
use App\Models\User;

class PersonPolicy
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
    public function view(User $user, Person $person): bool
    {
        return $user->hasPermissionTo('view children')
            && Person::query()->forUser($user)->whereKey($person->getKey())->exists();
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
    public function update(User $user, Person $person): bool
    {
        return $user->hasPermissionTo('update children')
            && Person::query()->forUser($user)->whereKey($person->getKey())->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Person $person): bool
    {
        return $user->hasPermissionTo('delete children')
            && Person::query()->forUser($user)->whereKey($person->getKey())->exists();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Person $person): bool
    {
        return $user->hasPermissionTo('delete children')
            && Person::query()->forUser($user)->whereKey($person->getKey())->exists();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Person $person): bool
    {
        return $user->hasPermissionTo('delete children')
            && Person::query()->forUser($user)->whereKey($person->getKey())->exists();
    }
}

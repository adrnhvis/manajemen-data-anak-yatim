<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\User;

class AddressPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view children')
            && ($user->hasRole('super_admin') || (bool) $user->organization_unit_id);
    }

    public function view(User $user, Address $address): bool
    {
        return $user->hasPermissionTo('view children')
            && Address::query()->forUser($user)->whereKey($address->getKey())->exists();
    }

    public function create(User $user): bool
    {
        $villageId = request()->input('village_id');

        return $user->hasPermissionTo('create children')
            && $villageId !== null
            && $user->isInOrganizationScope($villageId);
    }

    public function update(User $user, Address $address): bool
    {
        $villageId = request()->input('village_id', $address->village_id);

        return $user->hasPermissionTo('update children')
            && Address::query()->forUser($user)->whereKey($address->getKey())->exists()
            && $user->isInOrganizationScope($villageId);
    }

    public function delete(User $user, Address $address): bool
    {
        return $user->hasPermissionTo('delete children')
            && Address::query()->forUser($user)->whereKey($address->getKey())->exists();
    }
}

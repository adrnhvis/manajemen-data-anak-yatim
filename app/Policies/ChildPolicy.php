<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\Child;
use App\Models\User;

class ChildPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view children');
    }

    public function view(User $user, Child $child): bool
    {
        return $user->hasPermissionTo('view children') && $user->canAccessChild($child);
    }

    public function create(User $user): bool
    {
        if (!$user->hasPermissionTo('create children')) {
            return false;
        }

        $addressId = request()->input('address_id');
        if ($addressId) {
            $address = Address::find($addressId);
            if (!$address || !$user->isInOrganizationScope($address->village_id)) {
                return false;
            }
        }

        return true;
    }

    public function update(User $user, Child $child): bool
    {
        if (!$user->hasPermissionTo('update children') || !$user->canAccessChild($child)) {
            return false;
        }

        $newAddressId = request()->input('address_id');
        if ($newAddressId && $newAddressId !== $child->address_id) {
            $newAddress = Address::find($newAddressId);
            if (!$newAddress || !$user->isInOrganizationScope($newAddress->village_id)) {
                return false;
            }
        }

        return true;
    }

    public function delete(User $user, Child $child): bool
    {
        return $user->hasPermissionTo('delete children') && $user->canAccessChild($child);
    }

    public function restore(User $user, Child $child): bool
    {
        return $user->hasPermissionTo('delete children') && $user->canAccessChild($child);
    }

    public function forceDelete(User $user, Child $child): bool
    {
        return $user->hasPermissionTo('delete children') && $user->canAccessChild($child);
    }
}

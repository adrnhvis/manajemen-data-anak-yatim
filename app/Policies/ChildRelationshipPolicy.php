<?php

namespace App\Policies;

use App\Models\Child;
use App\Models\ChildRelationship;
use App\Models\Person;
use App\Models\User;

class ChildRelationshipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view children')
            && ($user->hasRole('super_admin') || (bool) $user->organization_unit_id);
    }

    public function view(User $user, ChildRelationship $relationship): bool
    {
        return $user->hasPermissionTo('view children')
            && ChildRelationship::query()->forUser($user)->whereKey($relationship->getKey())->exists();
    }

    public function create(User $user, Child $child, Person $person): bool
    {
        return $user->hasPermissionTo('create children')
            && $user->canAccessChild($child)
            && $this->personCanBeLinkedWithinScope($user, $person);
    }

    public function update(User $user, ChildRelationship $relationship): bool
    {
        $childId = request()->input('child_id', $relationship->child_id);
        $personId = request()->input('person_id', $relationship->person_id);
        $child = Child::find($childId);
        $person = Person::find($personId);

        return $user->hasPermissionTo('update children')
            && ChildRelationship::query()->forUser($user)->whereKey($relationship->getKey())->exists()
            && $child !== null
            && $user->canAccessChild($child)
            && $person !== null
            && $this->personCanBeLinkedWithinScope($user, $person);
    }

    public function delete(User $user, ChildRelationship $relationship): bool
    {
        return $user->hasPermissionTo('delete children')
            && ChildRelationship::query()->forUser($user)->whereKey($relationship->getKey())->exists();
    }

    private function personCanBeLinkedWithinScope(User $user, Person $person): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if (!$user->organization_unit_id) {
            return false;
        }

        $accessibleChildIds = Child::query()->forUser($user)->select('children.id');

        return Person::query()
            ->whereKey($person->getKey())
            ->whereDoesntHave('childRelationships', fn ($relationships) => $relationships->whereNotIn('child_id', $accessibleChildIds))
            ->exists();
    }
}

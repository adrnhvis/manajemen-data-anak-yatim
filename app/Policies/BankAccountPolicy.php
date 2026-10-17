<?php

namespace App\Policies;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\BankAccount;
use App\Models\User;

class BankAccountPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view applications')
            && ($user->hasRole('super_admin') || (bool) $user->organization_unit_id);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BankAccount $bankAccount): bool
    {
        return $user->hasPermissionTo('view applications')
            && BankAccount::query()->forUser($user)->whereKey($bankAccount->getKey())->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create applications')
            && ($user->hasRole('super_admin') || (bool) $user->organization_unit_id);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BankAccount $bankAccount): bool
    {
        return $user->hasPermissionTo('update applications')
            && BankAccount::query()->forUser($user)->whereKey($bankAccount->getKey())->exists()
            && $this->applicationIsEditable($bankAccount->application);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BankAccount $bankAccount): bool
    {
        return $user->hasPermissionTo('update applications')
            && BankAccount::query()->forUser($user)->whereKey($bankAccount->getKey())->exists()
            && $this->applicationIsEditable($bankAccount->application);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, BankAccount $bankAccount): bool
    {
        return $this->delete($user, $bankAccount);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, BankAccount $bankAccount): bool
    {
        return $this->delete($user, $bankAccount);
    }

    private function applicationIsEditable(Application $application): bool
    {
        return in_array($application->status, [
            ApplicationStatus::DRAFT,
            ApplicationStatus::REVISION_REQUIRED,
        ], true);
    }
}

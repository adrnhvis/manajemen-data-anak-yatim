<?php

namespace App\Policies;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Child;
use App\Models\User;

class ApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view applications');
    }

    public function view(User $user, Application $application): bool
    {
        return $user->hasPermissionTo('view applications') && $user->canAccessApplication($application);
    }

    public function upload(User $user, Application $application): bool
    {
        return $user->hasPermissionTo('upload documents')
            && $user->canAccessApplication($application)
            && in_array($application->status?->value, ['draft', 'revision_required'], true);
    }

    public function create(User $user): bool
    {
        if (!$user->hasPermissionTo('create applications')) {
            return false;
        }

        $childId = request()->input('child_id');
        if ($childId) {
            $child = Child::find($childId);
            if (!$child || !$user->canAccessChild($child)) {
                return false;
            }
        }

        return true;
    }

    public function update(User $user, Application $application): bool
    {
        if (!$user->hasPermissionTo('update applications') || !$user->canAccessApplication($application)) {
            return false;
        }

        $status = $application->status instanceof ApplicationStatus ? $application->status->value : $application->status;
        return in_array($status, [ApplicationStatus::DRAFT->value, ApplicationStatus::REVISION_REQUIRED->value]);
    }

    public function delete(User $user, Application $application): bool
    {
        if (!$user->hasPermissionTo('update applications') || !$user->canAccessApplication($application)) {
            return false;
        }

        $status = $application->status instanceof ApplicationStatus ? $application->status->value : $application->status;
        return $status === ApplicationStatus::DRAFT->value;
    }

    public function submit(User $user, Application $application): bool
    {
        if (!$user->hasPermissionTo('submit applications') || !$user->canAccessApplication($application)) {
            return false;
        }

        $status = $application->status instanceof ApplicationStatus ? $application->status->value : $application->status;
        return in_array($status, [ApplicationStatus::DRAFT->value, ApplicationStatus::REVISION_REQUIRED->value]);
    }

    public function review(User $user, Application $application): bool
    {
        if (!$user->hasPermissionTo('review applications') || !$user->canAccessApplication($application)) {
            return false;
        }

        $status = $application->status instanceof ApplicationStatus ? $application->status->value : $application->status;
        return $status === ApplicationStatus::SUBMITTED->value;
    }

    public function validate(User $user, Application $application): bool
    {
        if (!$user->hasPermissionTo('validate applications') || !$user->canAccessApplication($application)) {
            return false;
        }

        $status = $application->status instanceof ApplicationStatus ? $application->status->value : $application->status;
        return $status === ApplicationStatus::SUBMITTED->value;
    }

    public function requestRevision(User $user, Application $application): bool
    {
        if (!$user->hasPermissionTo('request revision') || !$user->canAccessApplication($application)) {
            return false;
        }

        $status = $application->status instanceof ApplicationStatus ? $application->status->value : $application->status;
        return $status === ApplicationStatus::SUBMITTED->value;
    }

    public function verify(User $user, Application $application): bool
    {
        if (!$user->hasPermissionTo('verify applications') || !$user->canAccessApplication($application)) {
            return false;
        }

        $status = $application->status instanceof ApplicationStatus ? $application->status->value : $application->status;
        return $status === ApplicationStatus::VALIDATED->value;
    }

    public function reject(User $user, Application $application): bool
    {
        if (!$user->hasPermissionTo('reject applications') || !$user->canAccessApplication($application)) {
            return false;
        }

        $status = $application->status instanceof ApplicationStatus ? $application->status->value : $application->status;
        return in_array($status, [ApplicationStatus::SUBMITTED->value, ApplicationStatus::VALIDATED->value]);
    }
}

<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view documents');
    }

    public function view(User $user, Document $document): bool
    {
        return $user->hasPermissionTo('view documents') && $user->canAccessApplication($document->application);
    }

    public function upload(User $user, Application $application): bool
    {
        return $user->hasPermissionTo('upload documents')
            && $user->canAccessApplication($application)
            && in_array($application->status?->value, ['draft', 'revision_required'], true);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('upload documents');
    }

    public function replace(User $user, Document $document): bool
    {
        return $user->hasPermissionTo('update documents')
            && $user->canAccessApplication($document->application)
            && in_array($document->application->status?->value, ['draft', 'revision_required'], true);
    }

    public function update(User $user, Document $document): bool
    {
        return $this->replace($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->hasPermissionTo('delete documents')
            && $user->canAccessApplication($document->application)
            && in_array($document->application->status?->value, ['draft', 'revision_required'], true);
    }
}

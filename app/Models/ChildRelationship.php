<?php

namespace App\Models;

use App\Enums\RelationshipType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['child_id', 'person_id', 'relationship_type', 'is_primary'])]
class ChildRelationship extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'relationship_type' => RelationshipType::class,
            'is_primary' => 'boolean',
        ];
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function scopeForUser($query, User $user)
    {
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        if (! $user->organization_unit_id) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('child', fn ($children) => $children->forUser($user));
    }
}

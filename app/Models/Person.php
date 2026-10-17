<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\PersonStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'gender', 'occupation', 'status'])]
class Person extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'persons';

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => PersonStatus::class,
        ];
    }

    public function childRelationships(): HasMany
    {
        return $this->hasMany(ChildRelationship::class);
    }

    public function scopeForUser($query, User $user)
    {
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        if (! $user->organization_unit_id) {
            return $query->whereRaw('1 = 0');
        }

        $accessibleChildIds = Child::query()->forUser($user)->select('children.id');

        return $query
            ->whereHas('childRelationships', fn ($relationships) => $relationships->whereIn('child_id', $accessibleChildIds))
            ->whereDoesntHave('childRelationships', fn ($relationships) => $relationships->whereNotIn('child_id', $accessibleChildIds));
    }
}

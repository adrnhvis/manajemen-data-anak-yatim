<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['kk_number'])]
class Household extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    public function children(): HasMany
    {
        return $this->hasMany(Child::class);
    }

    public function scopeForUser($query, User $user)
    {
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        if (!$user->organization_unit_id) {
            return $query->whereRaw('1 = 0');
        }

        $accessibleChildIds = Child::query()->forUser($user)->select('children.id');

        return $query
            ->whereHas('children', fn ($children) => $children->whereIn('children.id', $accessibleChildIds))
            ->whereDoesntHave('children', fn ($children) => $children->whereNotIn('children.id', $accessibleChildIds));
    }
}

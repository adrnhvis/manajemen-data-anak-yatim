<?php

namespace App\Models;

use App\Enums\Gender;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'household_id',
    'address_id',
    'name',
    'nik',
    'birth_place',
    'birth_date',
    'gender',
])]
class Child extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'gender' => Gender::class,
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(ChildRelationship::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function scopeForUser($query, User $user)
    {
        if ($user->hasRole('super_admin') || !$user->organization_unit_id) {
            return $query;
        }

        $org = $user->organizationUnit;
        if (!$org) {
            return $query->whereRaw('1 = 0');
        }

        if ($org->type === \App\Enums\OrganizationUnitType::VILLAGE) {
            return $query->whereHas('address', function ($q) use ($org) {
                $q->where('village_id', $org->village_id);
            });
        }

        if ($org->type === \App\Enums\OrganizationUnitType::DISTRICT) {
            return $query->whereHas('address.village', function ($q) use ($org) {
                $q->where('district_id', $org->district_id);
            });
        }

        if ($org->type === \App\Enums\OrganizationUnitType::REGENCY) {
            return $query->whereHas('address.village.district', function ($q) use ($org) {
                $q->where('regency_id', $org->regency_id);
            });
        }

        return $query->whereRaw('1 = 0');
    }
}

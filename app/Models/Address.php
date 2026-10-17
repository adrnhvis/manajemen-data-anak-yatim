<?php

namespace App\Models;

use App\Enums\LivingWith;
use App\Enums\OrganizationUnitType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['village_id', 'rt', 'rw', 'address_detail', 'living_with'])]
class Address extends Model
{
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'living_with' => LivingWith::class,
        ];
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(Child::class);
    }

    public function scopeForUser($query, User $user)
    {
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        if (! $user->organization_unit_id) {
            return $query->whereRaw('1 = 0');
        }

        $organizationUnit = $user->organizationUnit;
        if (! $organizationUnit) {
            return $query->whereRaw('1 = 0');
        }

        if ($organizationUnit->type === OrganizationUnitType::VILLAGE) {
            return $query->where('village_id', $organizationUnit->village_id);
        }

        if ($organizationUnit->type === OrganizationUnitType::DISTRICT) {
            return $query->whereHas('village', fn ($villages) => $villages->where('district_id', $organizationUnit->district_id));
        }

        if ($organizationUnit->type === OrganizationUnitType::REGENCY) {
            return $query->whereHas('village.district', fn ($districts) => $districts->where('regency_id', $organizationUnit->regency_id));
        }

        return $query->whereRaw('1 = 0');
    }
}

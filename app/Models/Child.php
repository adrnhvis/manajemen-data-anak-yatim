<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\OrganizationUnitType;
use App\Enums\PersonStatus;
use App\Enums\RelationshipType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    /** Permohonan dengan periode terbaru (untuk kolom status di tabel). */
    public function latestApplication(): HasOne
    {
        return $this->hasOne(Application::class)->ofMany('period_year', 'max');
    }

    public function getParentStatusAttribute(): string
    {
        $this->loadMissing('relationships.person');

        $statusOf = fn (RelationshipType $type) => $this->relationships
            ->firstWhere('relationship_type', $type)?->person?->status;

        $fatherDead = $statusOf(RelationshipType::FATHER) === PersonStatus::DECEASED;
        $motherDead = $statusOf(RelationshipType::MOTHER) === PersonStatus::DECEASED;

        if ($fatherDead && $motherDead) {
            return 'Yatim Piatu';
        }

        if ($fatherDead) {
            return 'Yatim';
        }

        if ($motherDead) {
            return 'Piatu';
        }

        return 'Lengkap';
    }

    /** Padanan SQL dari parent_status: Yatim | Piatu | Yatim Piatu | Lengkap. */
    public function scopeParentStatus($query, string $status)
    {
        $deceased = fn (RelationshipType $type) => fn ($relationship) => $relationship
            ->where('relationship_type', $type->value)
            ->whereHas('person', fn ($person) => $person->where('status', PersonStatus::DECEASED->value));

        $fatherDead = $deceased(RelationshipType::FATHER);
        $motherDead = $deceased(RelationshipType::MOTHER);

        return match ($status) {
            'Yatim Piatu' => $query->whereHas('relationships', $fatherDead)->whereHas('relationships', $motherDead),
            'Yatim' => $query->whereHas('relationships', $fatherDead)->whereDoesntHave('relationships', $motherDead),
            'Piatu' => $query->whereHas('relationships', $motherDead)->whereDoesntHave('relationships', $fatherDead),
            'Lengkap' => $query->whereDoesntHave('relationships', $fatherDead)->whereDoesntHave('relationships', $motherDead),
            default => $query,
        };
    }

    /** Anak yang usianya antara $min dan $max tahun (inklusif). */
    public function scopeAgeBetween($query, int $min, int $max)
    {
        return $query
            ->whereDate('birth_date', '<=', now()->subYears($min))
            ->whereDate('birth_date', '>', now()->subYears($max + 1));
    }

    public function scopeForUser($query, User $user)
    {
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        if (! $user->organization_unit_id) {
            return $query->whereRaw('1 = 0');
        }

        $org = $user->organizationUnit;
        if (! $org) {
            return $query->whereRaw('1 = 0');
        }

        if ($org->type === OrganizationUnitType::VILLAGE) {
            return $query->whereHas('address', function ($q) use ($org) {
                $q->where('village_id', $org->village_id);
            });
        }

        if ($org->type === OrganizationUnitType::DISTRICT) {
            return $query->whereHas('address.village', function ($q) use ($org) {
                $q->where('district_id', $org->district_id);
            });
        }

        if ($org->type === OrganizationUnitType::REGENCY) {
            return $query->whereHas('address.village.district', function ($q) use ($org) {
                $q->where('regency_id', $org->regency_id);
            });
        }

        return $query->whereRaw('1 = 0');
    }
}

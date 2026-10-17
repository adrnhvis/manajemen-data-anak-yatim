<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\OrganizationUnitType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'organization_unit_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUlids, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'created_by');
    }

    public function uploadedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'uploaded_by');
    }

    public function workflowHistories(): HasMany
    {
        return $this->hasMany(WorkflowHistory::class, 'acted_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isInOrganizationScope(string|int|null $villageId): bool
    {
        if (! $villageId) {
            return false;
        }

        if ($this->hasRole('super_admin')) {
            return true;
        }

        if (! $this->organization_unit_id) {
            return false;
        }

        $org = $this->organizationUnit;
        if (! $org) {
            return false;
        }

        if ($org->type === OrganizationUnitType::VILLAGE) {
            return $org->village_id === $villageId;
        }

        if ($org->type === OrganizationUnitType::DISTRICT) {
            $village = Village::find($villageId);

            return $village && $village->district_id === $org->district_id;
        }

        if ($org->type === OrganizationUnitType::REGENCY) {
            $village = Village::with('district')->find($villageId);

            return $village && $village->district && $village->district->regency_id === $org->regency_id;
        }

        return false;
    }

    public function canAccessChild(Child $child): bool
    {
        $child->loadMissing('address.village.district');
        $villageId = $child->address?->village_id;

        return $this->isInOrganizationScope($villageId);
    }

    public function canAccessApplication(Application $application): bool
    {
        $application->loadMissing('child.address.village.district');
        if (! $application->child) {
            return false;
        }

        return $this->canAccessChild($application->child);
    }
}

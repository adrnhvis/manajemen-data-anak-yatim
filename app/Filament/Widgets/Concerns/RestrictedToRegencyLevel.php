<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\OrganizationUnitType;
use App\Models\ClusterRun;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Hasil pengelompokan membandingkan seluruh wilayah, jadi hanya
 * tampil untuk super admin dan admin tingkat kabupaten.
 */
trait RestrictedToRegencyLevel
{
    // protected static bool $isDiscovered = false; // Konflik dengan Filament Widget v3

    public static function canView(): bool
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return false;
        }

        $allowed = $user->hasRole('super_admin')
            || ($user->hasPermissionTo('view reports')
                && $user->organizationUnit?->type === OrganizationUnitType::REGENCY);

        return $allowed && ClusterRun::query()->exists();
    }
}

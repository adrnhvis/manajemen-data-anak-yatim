<?php

namespace App\Filament\Pages;

use App\Enums\ApplicationStatus;
use App\Enums\OrganizationUnitType;
use App\Models\Application;
use App\Models\Child;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ReportPage extends Page
{
    protected string $view = 'filament.pages.report-page';

    protected static ?string $navigationLabel = 'Laporan';

    protected static ?string $title = 'Laporan & Rekapitulasi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && ($user->hasRole('super_admin') || $user->hasPermissionTo('view reports'));
    }

    public function getViewData(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return [];
        }

        $childQuery = Child::query()->forUser($user);
        $appQuery = Application::query()->forUser($user);
        $organizationUnit = $user->organizationUnit;
        $scopeDescription = match ($organizationUnit?->type) {
            OrganizationUnitType::VILLAGE => 'Kelurahan/Desa: '.$organizationUnit->name,
            OrganizationUnitType::DISTRICT => 'Kecamatan: '.$organizationUnit->name.' (seluruh kelurahan/desa di kecamatan ini)',
            OrganizationUnitType::REGENCY => 'Kabupaten: '.$organizationUnit->name.' (seluruh kelurahan/desa di kabupaten ini)',
            default => $user->hasRole('super_admin') ? 'Seluruh wilayah' : 'Wilayah belum ditetapkan',
        };

        return [
            'scopeDescription' => $scopeDescription,
            'totalChildren' => (clone $childQuery)->count(),
            'totalApplications' => (clone $appQuery)->count(),
            'totalDraft' => (clone $appQuery)->where('status', ApplicationStatus::DRAFT)->count(),
            'totalSubmitted' => (clone $appQuery)->where('status', ApplicationStatus::SUBMITTED)->count(),
            'totalValidated' => (clone $appQuery)->where('status', ApplicationStatus::VALIDATED)->count(),
            'totalVerified' => (clone $appQuery)->where('status', ApplicationStatus::VERIFIED)->count(),
            'totalRejected' => (clone $appQuery)->where('status', ApplicationStatus::REJECTED)->count(),
            'totalRevision' => (clone $appQuery)->where('status', ApplicationStatus::REVISION_REQUIRED)->count(),
        ];
    }
}

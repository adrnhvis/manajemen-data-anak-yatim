<?php

namespace App\Filament\Pages;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Child;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ReportPage extends Page
{
    protected string $view = 'filament.pages.report-page';

    protected static ?string $navigationLabel = 'Laporan';

    protected static ?string $title = 'Laporan & Rekapitulasi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole('super_admin') || $user->hasPermissionTo('view reports'));
    }

    public function getViewData(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $childQuery = Child::query()->forUser($user);
        $appQuery = Application::query()->forUser($user);

        return [
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

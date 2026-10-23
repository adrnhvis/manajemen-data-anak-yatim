<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Dashboard\RegionBreakdownChart;
use App\Filament\Widgets\Dashboard\StatusDonutChart;
use App\Filament\Widgets\Dashboard\TaskStats;
use App\Filament\Widgets\Dashboard\TaskTable;
use App\Models\User;
use App\Services\DashboardService;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Dashboard';

    public function getSubheading(): ?string
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return null;
        }

        return 'Selamat datang, '.$user->name.' · '.app(DashboardService::class)->scopeDescription($user);
    }

    public function getWidgets(): array
    {
        return [
            TaskStats::class,
            StatusDonutChart::class,
            RegionBreakdownChart::class,
            TaskTable::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 2;
    }
}

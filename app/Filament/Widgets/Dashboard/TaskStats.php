<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Children\ChildResource;
use App\Models\User;
use App\Services\DashboardService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class TaskStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return [];
        }

        $service = app(DashboardService::class);
        $level = $service->level($user);

        return collect($service->statCards($user))->map(function (array $card, int $i) use ($level) {
            $stat = Stat::make($card['label'], $card['value'])
                ->description($card['description'])
                ->color($card['color']);

            // Kartu pertama = pekerjaan utama; klik langsung ke halaman kerjanya.
            if ($i === 0) {
                if ($level === DashboardService::LEVEL_VILLAGE) {
                    $stat->url(ChildResource::getUrl('index'));
                } elseif (in_array($level, [DashboardService::LEVEL_DISTRICT, DashboardService::LEVEL_REGENCY], true)) {
                    $stat->url(ApplicationResource::getUrl('index'));
                }
            }

            return $stat;
        })->all();
    }
}

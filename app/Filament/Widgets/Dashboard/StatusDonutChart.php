<?php

namespace App\Filament\Widgets\Dashboard;

use App\Enums\ApplicationStatus;
use App\Models\User;
use App\Services\DashboardService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class StatusDonutChart extends ChartWidget
{
    protected int|string|array $columnSpan = 1;

    public function getHeading(): ?string
    {
        return 'Komposisi status permohonan';
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return ['labels' => [], 'datasets' => []];
        }

        $counts = app(DashboardService::class)->statusCounts($user);
        $statuses = collect(ApplicationStatus::cases())->filter(fn (ApplicationStatus $s) => $counts->get($s->value, 0) > 0);

        return [
            'labels' => $statuses->map(fn (ApplicationStatus $s) => $s->label())->values()->all(),
            'datasets' => [[
                'data' => $statuses->map(fn (ApplicationStatus $s) => $counts->get($s->value))->values()->all(),
                'backgroundColor' => $statuses->map(fn (ApplicationStatus $s) => $s->chartColor())->values()->all(),
            ]],
        ];
    }
}

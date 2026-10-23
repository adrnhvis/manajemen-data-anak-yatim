<?php

namespace App\Filament\Widgets\Dashboard;

use App\Models\User;
use App\Services\DashboardService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class RegionBreakdownChart extends ChartWidget
{
    protected int|string|array $columnSpan = 1;

    /** Kelurahan/desa hanya punya satu wilayah, jadi tidak ada yang dibandingkan. */
    public static function canView(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && ! in_array(app(DashboardService::class)->level($user), [DashboardService::LEVEL_VILLAGE, DashboardService::LEVEL_NONE], true);
    }

    public function getHeading(): ?string
    {
        $user = Auth::user();
        $byVillage = $user instanceof User && app(DashboardService::class)->level($user) === DashboardService::LEVEL_DISTRICT;

        return $byVillage ? 'Permohonan per kelurahan/desa' : 'Permohonan per kecamatan';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $user = Auth::user();
        $regions = $user instanceof User ? app(DashboardService::class)->regionBreakdown($user) : [];

        $datasets = [];
        foreach (DashboardService::BUCKETS as $key => [$label, $color]) {
            $datasets[] = [
                'label' => $label,
                'data' => array_map(fn (array $buckets) => $buckets[$key] ?? 0, array_values($regions)),
                'backgroundColor' => $color,
            ];
        }

        return ['labels' => array_keys($regions), 'datasets' => $datasets];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true, 'ticks' => ['precision' => 0]],
            ],
            'plugins' => ['legend' => ['position' => 'bottom']],
        ];
    }
}

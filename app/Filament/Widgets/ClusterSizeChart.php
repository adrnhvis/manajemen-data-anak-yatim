<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\RestrictedToRegencyLevel;
use App\Models\ClusterResult;
use App\Models\ClusterRun;
use Filament\Widgets\ChartWidget;

class ClusterSizeChart extends ChartWidget
{
    use RestrictedToRegencyLevel;

    protected int|string|array $columnSpan = 1;

    public function getHeading(): ?string
    {
        return 'Sebaran kelurahan/desa per kelompok';
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $run = ClusterRun::latestRun();
        $rows = ClusterResult::query()
            ->where('cluster_run_id', $run?->id)
            ->where('is_clustered', true)
            ->selectRaw('cluster_index, cluster_label, count(*) as total')
            ->groupBy('cluster_index', 'cluster_label')
            ->orderBy('cluster_index')
            ->get();

        return [
            'labels' => $rows->pluck('cluster_label')->all(),
            'datasets' => [[
                'data' => $rows->pluck('total')->all(),
                'backgroundColor' => $rows->map(fn ($r) => ClusterResult::chartColor($r->cluster_index, $run->k))->all(),
            ]],
        ];
    }
}

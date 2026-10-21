<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\RestrictedToRegencyLevel;
use App\Models\ClusterResult;
use App\Models\ClusterRun;
use Filament\Widgets\ChartWidget;

class ClusterScatterChart extends ChartWidget
{
    use RestrictedToRegencyLevel;

    protected int|string|array $columnSpan = 1;

    public function getHeading(): ?string
    {
        return 'Revisi vs lama penyusunan berkas (per wilayah)';
    }

    protected function getType(): string
    {
        return 'scatter';
    }

    protected function getData(): array
    {
        $run = ClusterRun::latestRun();
        $results = ClusterResult::query()
            ->where('cluster_run_id', $run?->id)
            ->where('is_clustered', true)
            ->get(['cluster_index', 'cluster_label', 'avg_returns', 'avg_first_submit_days'])
            ->groupBy('cluster_index')
            ->sortKeys();

        $datasets = $results->map(fn ($group, $index) => [
            'label' => $group->first()->cluster_label,
            'data' => $group->map(fn ($r) => ['x' => round($r->avg_returns, 2), 'y' => round($r->avg_first_submit_days, 1)])->values()->all(),
            'backgroundColor' => ClusterResult::chartColor((int) $index, $run->k),
        ])->values()->all();

        return ['datasets' => $datasets];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['title' => ['display' => true, 'text' => 'Rata-rata revisi per pengajuan']],
                'y' => ['title' => ['display' => true, 'text' => 'Hari dari draft hingga diajukan']],
            ],
        ];
    }
}

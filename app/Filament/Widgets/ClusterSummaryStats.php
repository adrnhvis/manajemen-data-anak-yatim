<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\RestrictedToRegencyLevel;
use App\Models\ClusterRun;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClusterSummaryStats extends StatsOverviewWidget
{
    use RestrictedToRegencyLevel;

    protected function getStats(): array
    {
        $run = ClusterRun::latestRun();
        if ($run === null) {
            return [];
        }

        return [
            Stat::make('Jumlah kelompok (k)', $run->k)
                ->description('Dipilih otomatis dari skor silhouette'),
            Stat::make('Skor silhouette', number_format($run->silhouette, 2, ',', '.'))
                ->description($run->qualityLabel())
                ->color($run->qualityColor()),
            Stat::make('Wilayah dikelompokkan', $run->n_clustered)
                ->description("{$run->n_excluded} data belum cukup · {$run->n_without_applications} belum pernah mengajukan"),
            Stat::make('Dijalankan', $run->created_at->format('d/m/Y H:i'))
                ->description($run->period_year ? "Periode {$run->period_year}" : 'Semua periode'),
        ];
    }
}

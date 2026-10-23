<?php

namespace App\Filament\Widgets;

use App\Enums\ApplicationStatus;
use App\Models\User;
use App\Services\DashboardService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * Rincian jumlah permohonan per status untuk halaman Laporan.
 * Memakai komponen widget Filament (bukan kelas Tailwind di Blade) karena project
 * belum punya tema Filament kustom, sehingga kelas Tailwind sembarang tidak ikut dikompilasi.
 */
class ReportStatusStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected function getHeading(): ?string
    {
        return 'Rincian Status Permohonan Bantuan';
    }

    protected function getColumns(): int|array|null
    {
        return 3;
    }

    protected function getStats(): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return [];
        }

        $counts = app(DashboardService::class)->statusCounts($user);
        $total = (int) $counts->sum();

        return collect(ApplicationStatus::cases())->map(function (ApplicationStatus $status) use ($counts, $total) {
            $count = (int) $counts->get($status->value, 0);

            return Stat::make($status->label(), $count)
                ->description($total > 0 ? round($count / $total * 100).'% dari '.$total.' permohonan' : 'Belum ada permohonan')
                ->color($status->color());
        })->all();
    }
}

<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\RestrictedToRegencyLevel;
use App\Models\ClusterResult;
use App\Models\ClusterRun;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ClusterResultsTable extends TableWidget
{
    use RestrictedToRegencyLevel;

    protected static bool $isDiscovered = false; // hanya tampil di ReportPage, bukan dashboard
    protected int|string|array $columnSpan = 'full';
    public function table(Table $table): Table
    {
        $k = ClusterRun::latestRun()?->k ?? 1;

        return $table
            ->heading('Hasil pengelompokan per kelurahan/desa' . (ClusterRun::latestRun()?->is_simulation ? ' — DATA SIMULASI, bukan temuan nyata' : ''))
            ->query(fn(): Builder => ClusterResult::query()->where('cluster_run_id', ClusterRun::latestRun()?->id))
            ->columns([
                TextColumn::make('village_name')->label('Kelurahan/Desa')->searchable()->sortable(),
                TextColumn::make('district_name')->label('Kecamatan')->searchable()->sortable(),
                TextColumn::make('cluster_label')
                    ->label('Kelompok')
                    ->badge()
                    ->color(fn(ClusterResult $record): string => ClusterResult::badgeColor($record->cluster_index, $k))
                    ->sortable(query: fn(Builder $query, string $direction) => $query->orderBy('cluster_index', $direction)),
                TextColumn::make('applications_count')->label('Pengajuan')->numeric()->sortable(),
                TextColumn::make('avg_returns')->label('Revisi/pengajuan')->numeric(2)->sortable(),
                TextColumn::make('reject_rate')->label('Ditolak')
                    ->formatStateUsing(fn($state) => number_format($state * 100, 0) . '%')->sortable(),
                TextColumn::make('avg_resubmit_days')->label('Hari merespons revisi')->numeric(1)->placeholder('-')->sortable(),
                TextColumn::make('avg_first_submit_days')->label('Hari draft→diajukan')->numeric(1)->sortable(),
            ])
            ->filters([
                SelectFilter::make('cluster_label')->label('Kelompok')->options(
                    fn() => ClusterResult::query()->where('cluster_run_id', ClusterRun::latestRun()?->id)
                        ->distinct()->orderBy('cluster_label')->pluck('cluster_label', 'cluster_label')->all()
                ),
            ])
            ->defaultSort('cluster_index', 'desc')
            ->paginated([10, 25, 50]);
    }
    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full; // Menghapus margin kiri-kanan bawaan Filament
    }
}

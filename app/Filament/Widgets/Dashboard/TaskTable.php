<?php

namespace App\Filament\Widgets\Dashboard;

use App\Enums\ApplicationStatus;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Children\ChildResource;
use App\Models\Application;
use App\Models\User;
use App\Services\DashboardService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TaskTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    /** Super admin tidak punya antrian tugas. */
    public static function canView(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && in_array(app(DashboardService::class)->level($user), [
                DashboardService::LEVEL_VILLAGE, DashboardService::LEVEL_DISTRICT, DashboardService::LEVEL_REGENCY,
            ], true);
    }

    public function table(Table $table): Table
    {
        $user = Auth::user();
        $service = app(DashboardService::class);
        $isVillage = $user instanceof User && $service->level($user) === DashboardService::LEVEL_VILLAGE;

        return $table
            ->heading($user instanceof User ? $service->taskHeading($user) : 'Tugas')
            ->description('Diurutkan dari yang paling lama belum diperbarui.')
            ->query(function () use ($service): Builder {
                $user = Auth::user();

                return $service->taskQuery($user)
                    ->with(['child.address.village'])
                    ->orderBy('applications.updated_at');
            })
            ->columns([
                TextColumn::make('child.name')->label('Nama Anak')->searchable(),
                TextColumn::make('child.address.village.name')->label('Kelurahan / Desa'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ApplicationStatus $state) => $state->label())
                    ->color(fn (ApplicationStatus $state) => $state->color()),
                TextColumn::make('period_year')->label('Periode'),
                TextColumn::make('updated_at')->label('Terakhir diperbarui')->since(),
            ])
            ->recordUrl(fn (Application $record): string => $isVillage
                ? ChildResource::getUrl('edit', ['record' => $record->child_id])
                : ApplicationResource::getUrl('index'))
            ->emptyStateHeading('Tidak ada tugas saat ini')
            ->emptyStateDescription('Semua permohonan dalam cakupan Anda sudah ditindaklanjuti.')
            ->paginated([5, 10, 25]);
    }
}

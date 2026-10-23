<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\User;
use App\Services\DashboardService;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListApplications extends ListRecords
{
    protected static string $resource = ApplicationResource::class;

    protected static ?string $title = 'Verifikasi Permohonan';

    public function getTabs(): array
    {
        $user = Auth::user();
        $service = app(DashboardService::class);
        $queueCount = $user instanceof User ? $service->taskQuery($user)->count() : 0;

        return [
            'queue' => Tab::make('Antrian')
                ->badge($queueCount > 0 ? $queueCount : null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $user instanceof User
                    ? $service->applyTaskConstraints($query, $user)
                    : $query->whereRaw('1 = 0')),

            'history' => Tab::make('Riwayat Saya')
                ->modifyQueryUsing(fn (Builder $query) => $user instanceof User
                    ? $service->applyHandledConstraints($query, $user)
                    : $query->whereRaw('1 = 0')),

            'all' => Tab::make('Semua dalam Cakupan'),
        ];
    }
}

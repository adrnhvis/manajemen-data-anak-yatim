<?php

namespace App\Filament\Resources\Applications;

use App\Enums\ApplicationStatus;
use App\Filament\Resources\Applications\Pages\ListApplications;
use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Filament\Resources\Applications\Schemas\ApplicationInfolist;
use App\Filament\Resources\Children\Tables\ChildrenTable;
use App\Models\Application;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class ApplicationResource extends Resource
{
    protected static ?string $model = Application::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Verifikasi Permohonan';

    protected static string|UnitEnum|null $navigationGroup = 'Verifikasi';

    protected static ?string $modelLabel = 'Permohonan Bantuan';

    protected static ?string $pluralModelLabel = 'Permohonan Bantuan';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && ($user->hasRole('admin_kecamatan') || $user->hasRole('admin_kabupaten'))
            && Gate::forUser($user)->allows('viewAny', Application::class);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();
        $query = parent::getEloquentQuery()->with([
            'child.household',
            'child.address.village.district',
        ]);

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        // Antrian / Riwayat / Semua diatur oleh tab di ListApplications.
        // Di sini cukup dipastikan hanya peran review yang boleh membaca, dan hanya dalam wilayahnya.
        if (! $user->hasRole('admin_kecamatan') && ! $user->hasRole('admin_kabupaten')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->forUser($user);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ApplicationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('child.nik')
                    ->label('NIK')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('child.name')
                    ->label('Nama Anak')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('child.address.village.name')
                    ->label('Kelurahan / Desa')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('child.address.village.district.name')
                    ->label('Kecamatan')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('period_year')
                    ->label('Tahun Periode')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof ApplicationStatus ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof ApplicationStatus ? $state->color() : 'gray')
                    ->sortable(),

                TextColumn::make('current_review_level')
                    ->label('Tahap Review')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state?->value ?? $state) {
                        'village' => 'Kelurahan',
                        'district' => 'Kecamatan',
                        'regency' => 'Kabupaten',
                        default => 'Selesai',
                    }),

                TextColumn::make('submitted_at')
                    ->label('Tanggal Diajukan')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Terakhir Diperbarui')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(ApplicationStatus::cases())->mapWithKeys(fn (ApplicationStatus $s) => [$s->value => $s->label()])->all()),
                SelectFilter::make('period_year')
                    ->label('Periode')
                    ->options(fn (): array => Application::query()->distinct()->orderByDesc('period_year')->pluck('period_year', 'period_year')->all()),
                SelectFilter::make('village')
                    ->label('Kelurahan / Desa')
                    ->searchable()
                    ->options(fn (): array => ChildrenTable::villageOptions())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('child.address', fn ($q) => $q->where('village_id', $data['value']))
                        : $query),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->persistFiltersInSession()
            ->recordActions([
                ViewAction::make()->label('Periksa')->icon('heroicon-o-eye'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApplications::route('/'),
            'view' => ViewApplication::route('/{record}'),
        ];
    }
}

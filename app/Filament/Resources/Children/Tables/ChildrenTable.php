<?php

namespace App\Filament\Resources\Children\Tables;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\District;
use App\Models\User;
use App\Models\Village;
use App\Services\DashboardService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ChildrenTable
{
    private const AGE_GROUPS = [
        'balita' => ['0–5 tahun', 0, 5],
        'sd' => ['6–12 tahun', 6, 12],
        'smp' => ['13–15 tahun', 13, 15],
        'sma' => ['16–18 tahun', 16, 18],
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nik')
                    ->label('NIK')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nama Anak')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('gender')
                    ->label('Jenis Kelamin')
                    ->formatStateUsing(fn ($state) => match ($state?->value ?? $state) {
                        'male' => 'Laki-laki',
                        'female' => 'Perempuan',
                        default => $state,
                    })
                    ->sortable(),

                TextColumn::make('birth_date')
                    ->label('Tanggal Lahir')
                    ->date('d M Y')
                    ->description(fn ($record) => $record->birth_date ? $record->birth_date->age.' tahun' : null)
                    ->sortable(),

                TextColumn::make('household.kk_number')
                    ->label('No. KK')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('address.village.name')
                    ->label('Kelurahan / Desa')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('address.village.district.name')
                    ->label('Kecamatan')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('parent_status')
                    ->label('Status Anak')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Yatim' => 'warning',
                        'Piatu' => 'info',
                        'Yatim Piatu' => 'danger',
                        'Lengkap' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('latestApplication.status')
                    ->label('Status Permohonan')
                    ->badge()
                    ->placeholder('Belum ada')
                    ->formatStateUsing(fn ($state) => $state instanceof ApplicationStatus ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof ApplicationStatus ? $state->color() : 'gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('gender')
                    ->label('Jenis Kelamin')
                    ->options(['male' => 'Laki-laki', 'female' => 'Perempuan']),

                SelectFilter::make('parent_status')
                    ->label('Status Anak')
                    ->options(['Yatim' => 'Yatim', 'Piatu' => 'Piatu', 'Yatim Piatu' => 'Yatim Piatu', 'Lengkap' => 'Orang tua lengkap'])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->parentStatus($data['value'])
                        : $query),

                SelectFilter::make('age_group')
                    ->label('Kelompok Usia')
                    ->options(collect(self::AGE_GROUPS)->map(fn (array $g) => $g[0])->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $group = self::AGE_GROUPS[$data['value'] ?? ''] ?? null;

                        return $group ? $query->ageBetween($group[1], $group[2]) : $query;
                    }),

                SelectFilter::make('district')
                    ->label('Kecamatan')
                    ->searchable()
                    ->options(fn (): array => self::districtOptions())
                    ->visible(fn (): bool => self::level() !== DashboardService::LEVEL_VILLAGE && self::level() !== DashboardService::LEVEL_DISTRICT)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('address.village', fn ($q) => $q->where('district_id', $data['value']))
                        : $query),

                SelectFilter::make('village')
                    ->label('Kelurahan / Desa')
                    ->searchable()
                    ->options(fn (): array => self::villageOptions())
                    ->visible(fn (): bool => self::level() !== DashboardService::LEVEL_VILLAGE)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('address', fn ($q) => $q->where('village_id', $data['value']))
                        : $query),

                Filter::make('application')
                    ->label('Permohonan')
                    ->schema([
                        Select::make('status')
                            ->label('Status Permohonan')
                            ->options(collect(ApplicationStatus::cases())->mapWithKeys(fn (ApplicationStatus $s) => [$s->value => $s->label()])->all()),
                        Select::make('period_year')
                            ->label('Periode')
                            ->options(fn (): array => Application::query()->distinct()->orderByDesc('period_year')->pluck('period_year', 'period_year')->all()),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['status'] ?? null) || filled($data['period_year'] ?? null),
                        fn (Builder $q) => $q->whereHas('applications', function ($a) use ($data) {
                            $a->when(filled($data['status'] ?? null), fn ($x) => $x->where('status', $data['status']))
                                ->when(filled($data['period_year'] ?? null), fn ($x) => $x->where('period_year', $data['period_year']));
                        }),
                    ))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (filled($data['status'] ?? null)) {
                            $indicators[] = 'Permohonan: '.ApplicationStatus::from($data['status'])->label();
                        }
                        if (filled($data['period_year'] ?? null)) {
                            $indicators[] = 'Periode: '.$data['period_year'];
                        }

                        return $indicators;
                    }),

                TrashedFilter::make(),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->persistFiltersInSession()
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    private static function level(): string
    {
        $user = Auth::user();

        return $user instanceof User ? app(DashboardService::class)->level($user) : DashboardService::LEVEL_NONE;
    }

    /** @return array<string, string> */
    private static function districtOptions(): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return [];
        }

        return District::query()
            ->when($user->organizationUnit?->regency_id && ! $user->hasRole('super_admin'), fn ($q) => $q->where('regency_id', $user->organizationUnit->regency_id))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<string, string> */
    public static function villageOptions(): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return [];
        }

        $unit = $user->organizationUnit;

        return Village::query()
            ->with('district:id,name')
            ->when(! $user->hasRole('super_admin') && $unit?->district_id, fn ($q) => $q->where('district_id', $unit->district_id))
            ->when(! $user->hasRole('super_admin') && ! $unit?->district_id && $unit?->regency_id, fn ($q) => $q->whereHas('district', fn ($d) => $d->where('regency_id', $unit->regency_id)))
            ->orderBy('name')
            ->get(['id', 'name', 'district_id'])
            ->mapWithKeys(fn (Village $v) => [$v->id => $v->name.' ('.($v->district?->name ?? '-').')'])
            ->all();
    }
}

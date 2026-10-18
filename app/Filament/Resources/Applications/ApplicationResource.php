<?php

namespace App\Filament\Resources\Applications;

use App\Enums\ApplicationStatus;
use App\Enums\ReviewLevel;
use App\Filament\Resources\Applications\Pages\ListApplications;
use App\Models\Application;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
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
            'child.address.village',
        ]);

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        $query->forUser($user);

        if ($user->hasRole('admin_kecamatan')) {
            return $query
                ->where('status', ApplicationStatus::SUBMITTED)
                ->where('current_review_level', ReviewLevel::DISTRICT);
        }

        if ($user->hasRole('admin_kabupaten')) {
            return $query
                ->where('status', ApplicationStatus::VALIDATED)
                ->where('current_review_level', ReviewLevel::REGENCY);
        }

        return $query->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
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

                TextColumn::make('period_year')
                    ->label('Tahun Periode')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Application $record) => match ($record->status) {
                        ApplicationStatus::DRAFT => 'gray',
                        ApplicationStatus::SUBMITTED => 'info',
                        ApplicationStatus::REVISION_REQUIRED => 'warning',
                        ApplicationStatus::VALIDATED => 'primary',
                        ApplicationStatus::VERIFIED => 'success',
                        ApplicationStatus::REJECTED => 'danger',
                    })
                    ->formatStateUsing(fn ($state) => match ($state?->value ?? $state) {
                        'draft' => 'Draft',
                        'submitted' => 'Menunggu Pengecekan Kecamatan',
                        'revision_required' => 'Perlu Revisi',
                        'validated' => 'Menunggu Verifikasi Kabupaten',
                        'verified' => 'Terverifikasi Final',
                        'rejected' => 'Ditolak',
                        default => $state,
                    })
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
            ])
            ->defaultSort('submitted_at', 'desc')
            ->recordActions([
                ApplicationReviewActions::makeReviewDetails(),
                ApplicationReviewActions::makeValidate(),
                ApplicationReviewActions::makeRequestRevision(),
                ApplicationReviewActions::makeVerify(),
                ApplicationReviewActions::makeReject(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApplications::route('/'),
        ];
    }
}

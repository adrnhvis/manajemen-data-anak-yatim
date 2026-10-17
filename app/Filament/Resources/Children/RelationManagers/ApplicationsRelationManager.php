<?php

namespace App\Filament\Resources\Children\RelationManagers;

use App\Enums\ApplicationStatus;
use App\Enums\ReviewLevel;
use App\Models\Application;
use App\Services\ApplicationWorkflowService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ApplicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'applications';

    protected static ?string $title = 'Permohonan Pengajuan Bantuan';

    protected static ?string $modelLabel = 'Permohonan Bantuan';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('period_year')
                    ->label('Tahun Periode / Anggaran')
                    ->numeric()
                    ->default(now()->year)
                    ->minValue(2000)
                    ->maxValue(2100)
                    ->required()
                    ->disabledOn('edit'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('period_year')
            ->columns([
                TextColumn::make('period_year')
                    ->label('Tahun Periode')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Application $record) => match ($record->status?->value ?? $record->status) {
                        'draft' => 'gray',
                        'submitted' => 'info',
                        'revision_required' => 'warning',
                        'validated' => 'primary',
                        'verified' => 'success',
                        'rejected' => 'danger',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn ($state) => match ($state?->value ?? $state) {
                        'draft' => 'Draft',
                        'submitted' => 'Diajukan',
                        'revision_required' => 'Perlu Revisi',
                        'validated' => 'Tervalidasi Kecamatan',
                        'verified' => 'Terverifikasi Final',
                        'rejected' => 'Ditolak',
                        default => $state,
                    })
                    ->sortable(),

                TextColumn::make('current_review_level')
                    ->label('Tujuan Review')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state?->value ?? $state) {
                        'village' => 'Kelurahan',
                        'district' => 'Kecamatan',
                        'regency' => 'Kabupaten',
                        default => '-',
                    }),

                TextColumn::make('created_at')
                    ->label('Tanggal Buat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        ApplicationStatus::DRAFT->value => 'Draft',
                        ApplicationStatus::SUBMITTED->value => 'Diajukan',
                        ApplicationStatus::REVISION_REQUIRED->value => 'Perlu Revisi',
                        ApplicationStatus::VALIDATED->value => 'Tervalidasi Kecamatan',
                        ApplicationStatus::VERIFIED->value => 'Terverifikasi Final',
                        ApplicationStatus::REJECTED->value => 'Ditolak',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Ajukan Permohonan Baru')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();
                        $data['status'] = ApplicationStatus::DRAFT;
                        $data['current_review_level'] = ReviewLevel::VILLAGE;

                        return $data;
                    }),
            ])
            ->recordActions([
                Action::make('submit')
                    ->label('Ajukan')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Application $record) => auth()->user()?->can('submit', $record))
                    ->action(function (Application $record, ApplicationWorkflowService $workflow) {
                        try {
                            $workflow->submit(auth()->user(), $record);
                            Notification::make()->title('Aplikasi diajukan ke Kecamatan')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal: '.$e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('validate')
                    ->label('Validasi (Kecamatan)')
                    ->icon('heroicon-o-check-circle')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (Application $record) => auth()->user()?->can('validate', $record))
                    ->action(function (Application $record, ApplicationWorkflowService $workflow) {
                        try {
                            $workflow->validate(auth()->user(), $record);
                            Notification::make()->title('Aplikasi berhasil divalidasi')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal: '.$e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('requestRevision')
                    ->label('Minta Revisi')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (Application $record) => auth()->user()?->can('requestRevision', $record))
                    ->form([
                        Textarea::make('note')->label('Catatan Perbaikan')->required(),
                    ])
                    ->action(function (Application $record, array $data, ApplicationWorkflowService $workflow) {
                        try {
                            $workflow->requestRevision(auth()->user(), $record, $data['note']);
                            Notification::make()->title('Revisi dikembalikan ke Kelurahan')->warning()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal: '.$e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('verify')
                    ->label('Verifikasi Final')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Application $record) => auth()->user()?->can('verify', $record))
                    ->action(function (Application $record, ApplicationWorkflowService $workflow) {
                        try {
                            $workflow->verify(auth()->user(), $record);
                            Notification::make()->title('Aplikasi terverifikasi final')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal: '.$e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Application $record) => auth()->user()?->can('reject', $record))
                    ->form([
                        Textarea::make('note')->label('Alasan Penolakan')->required(),
                    ])
                    ->action(function (Application $record, array $data, ApplicationWorkflowService $workflow) {
                        try {
                            $workflow->reject(auth()->user(), $record, $data['note']);
                            Notification::make()->title('Aplikasi ditolak secara final')->danger()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal: '.$e->getMessage())->danger()->send();
                        }
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

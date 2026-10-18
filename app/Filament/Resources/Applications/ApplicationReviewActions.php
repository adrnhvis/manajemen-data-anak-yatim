<?php

namespace App\Filament\Resources\Applications;

use App\Models\Application;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ApplicationReviewActions
{
    public static function makeReviewDetails(): Action
    {
        return Action::make('reviewDetails')
            ->label('Periksa Data')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading('Rincian Permohonan')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->visible(function (Application $record): bool {
                $user = Auth::user();

                return $user instanceof User && Gate::forUser($user)->allows('view', $record);
            })
            ->fillForm(function (Application $record): array {
                $record->loadMissing([
                    'child.household',
                    'child.address.village.district',
                    'child.relationships.person',
                    'bankAccounts',
                    'documents.category',
                ]);
                $child = $record->child;
                $address = $child?->address;

                return [
                    'child_name' => $child?->name,
                    'nik' => $child?->nik,
                    'birth_info' => implode(' — ', array_filter([
                        $child?->birth_place,
                        $child?->birth_date?->format('d M Y'),
                    ])),
                    'gender' => $child?->gender?->value ?? $child?->gender,
                    'kk_number' => $child?->household?->kk_number,
                    'address' => implode(', ', array_filter([
                        $address?->address_detail,
                        $address?->rt ? 'RT '.$address->rt : null,
                        $address?->rw ? 'RW '.$address->rw : null,
                        $address?->village?->name,
                        $address?->village?->district?->name,
                    ])),
                    'parents' => $child?->relationships
                        ->map(fn ($relationship) => ucfirst($relationship->relationship_type->value ?? $relationship->relationship_type).': '.($relationship->person?->name ?? '-'))
                        ->implode("\n"),
                    'bank_accounts' => $record->bankAccounts
                        ->map(fn ($account) => implode(' — ', array_filter([
                            $account->bank_name,
                            $account->account_number,
                            $account->account_holder_name,
                        ])))
                        ->implode("\n"),
                    'documents' => $record->documents
                        ->map(fn ($document) => ($document->category?->name ?? 'Dokumen').': '.$document->original_filename)
                        ->implode("\n"),
                ];
            })
            ->form([
                TextInput::make('child_name')->label('Nama Anak')->disabled(),
                TextInput::make('nik')->label('NIK')->disabled(),
                TextInput::make('birth_info')->label('Tempat / Tanggal Lahir')->disabled(),
                TextInput::make('gender')->label('Jenis Kelamin')->disabled(),
                TextInput::make('kk_number')->label('Nomor KK')->disabled(),
                Textarea::make('address')->label('Alamat')->disabled(),
                Textarea::make('parents')->label('Data Orang Tua / Wali')->disabled(),
                Textarea::make('bank_accounts')->label('Informasi Rekening')->disabled(),
                Textarea::make('documents')->label('Dokumen Terlampir')->disabled(),
            ]);
    }

    public static function makeValidate(): Action
    {
        return Action::make('validate')
            ->label('Lulus Verifikasi Kecamatan')
            ->icon('heroicon-o-check-circle')
            ->color('primary')
            ->requiresConfirmation()
            ->visible(function (Application $record): bool {
                $user = Auth::user();

                return $user instanceof User && Gate::forUser($user)->allows('validate', $record);
            })
            ->action(function (Application $record, ApplicationWorkflowService $workflow): void {
                try {
                    $user = Auth::user();
                    abort_unless($user instanceof User, 403);

                    $workflow->validate($user, $record);
                    Notification::make()->title('Lolos verifikasi Kecamatan dan diteruskan ke Kabupaten')->success()->send();
                } catch (\Throwable $exception) {
                    Notification::make()->title('Gagal: '.$exception->getMessage())->danger()->send();
                }
            });
    }

    public static function makeRequestRevision(): Action
    {
        return Action::make('requestRevision')
            ->label('Minta Revisi')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->visible(function (Application $record): bool {
                $user = Auth::user();

                return $user instanceof User && Gate::forUser($user)->allows('requestRevision', $record);
            })
            ->form([
                Textarea::make('note')->label('Catatan Perbaikan')->required(),
            ])
            ->action(function (Application $record, array $data, ApplicationWorkflowService $workflow): void {
                try {
                    $user = Auth::user();
                    abort_unless($user instanceof User, 403);

                    $workflow->requestRevision($user, $record, $data['note']);
                    Notification::make()->title('Permohonan dikembalikan ke Kelurahan untuk diperbaiki')->warning()->send();
                } catch (\Throwable $exception) {
                    Notification::make()->title('Gagal: '.$exception->getMessage())->danger()->send();
                }
            });
    }

    public static function makeVerify(): Action
    {
        return Action::make('verify')
            ->label('Verifikasi Final Kabupaten')
            ->icon('heroicon-o-shield-check')
            ->color('success')
            ->requiresConfirmation()
            ->visible(function (Application $record): bool {
                $user = Auth::user();

                return $user instanceof User && Gate::forUser($user)->allows('verify', $record);
            })
            ->action(function (Application $record, ApplicationWorkflowService $workflow): void {
                try {
                    $user = Auth::user();
                    abort_unless($user instanceof User, 403);

                    $workflow->verify($user, $record);
                    Notification::make()->title('Permohonan berhasil diverifikasi final')->success()->send();
                } catch (\Throwable $exception) {
                    Notification::make()->title('Gagal: '.$exception->getMessage())->danger()->send();
                }
            });
    }

    public static function makeReject(): Action
    {
        return Action::make('reject')
            ->label('Tolak')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(function (Application $record): bool {
                $user = Auth::user();

                return $user instanceof User && Gate::forUser($user)->allows('reject', $record);
            })
            ->form([
                Textarea::make('note')->label('Alasan Penolakan')->required(),
            ])
            ->action(function (Application $record, array $data, ApplicationWorkflowService $workflow): void {
                try {
                    $user = Auth::user();
                    abort_unless($user instanceof User, 403);

                    $workflow->reject($user, $record, $data['note']);
                    Notification::make()->title('Permohonan ditolak secara final')->danger()->send();
                } catch (\Throwable $exception) {
                    Notification::make()->title('Gagal: '.$exception->getMessage())->danger()->send();
                }
            });
    }
}

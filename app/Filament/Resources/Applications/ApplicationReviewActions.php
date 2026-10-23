<?php

namespace App\Filament\Resources\Applications;

use App\Models\Application;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ApplicationReviewActions
{
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

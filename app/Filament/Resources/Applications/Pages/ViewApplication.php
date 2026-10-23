<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Applications\ApplicationReviewActions;
use App\Filament\Resources\Children\ChildResource;
use App\Models\Application;
use App\Models\Child;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ViewApplication extends ViewRecord
{
    protected static string $resource = ApplicationResource::class;

    protected static ?string $title = 'Periksa Permohonan';

    /**
     * Keputusan (lulus / revisi / verifikasi / tolak) hanya diambil di halaman ini,
     * setelah reviewer melihat dokumen dan datanya. Tombol yang tampil tetap ditentukan ApplicationPolicy.
     */
    protected function getHeaderActions(): array
    {
        // Muat ulang record sesudah tindakan agar progres, status, dan tombol langsung terbarui.
        $refresh = fn () => $this->getRecord()->refresh();

        return [
            ApplicationReviewActions::makeValidate()->after($refresh),
            ApplicationReviewActions::makeRequestRevision()->after($refresh),
            ApplicationReviewActions::makeVerify()->after($refresh),
            ApplicationReviewActions::makeReject()->after($refresh),

            Action::make('openChild')
                ->label('Data Anak Lengkap')
                ->icon('heroicon-o-user')
                ->color('gray')
                ->visible(function (): bool {
                    $user = Auth::user();
                    $child = $this->getRecord()->child;

                    return $user instanceof User && $child instanceof Child && Gate::forUser($user)->allows('view', $child);
                })
                ->url(fn (): string => ChildResource::getUrl('view', ['record' => $this->getRecord()->child_id])),
        ];
    }

    public function getRecord(): Application
    {
        /** @var Application $record */
        $record = parent::getRecord();

        return $record;
    }
}

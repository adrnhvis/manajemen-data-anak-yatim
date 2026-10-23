<?php

namespace App\Filament\Resources\Applications\Schemas;

use App\Enums\ApplicationStatus;
use App\Enums\BankAccountHolderType;
use App\Enums\RelationshipType;
use App\Filament\Resources\Children\Schemas\ChildInfolist;
use App\Models\Application;
use App\Models\BankAccount;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Progres Permohonan')
                ->schema([
                    ViewEntry::make('progress')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->view('filament.infolists.application-progress'),
                ])
                ->columnSpanFull(),

            Section::make('Dokumen Persyaratan')
                ->description('Klik nama berkas untuk membuka/mengunduh dan mencocokkannya dengan data di bawah.')
                ->schema([
                    ViewEntry::make('documents')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->view('filament.infolists.application-documents'),
                ])
                ->columnSpanFull(),

            Section::make('Data Anak')
                ->columns(2)
                ->schema([
                    TextEntry::make('child.name')->label('Nama Lengkap'),
                    TextEntry::make('child.nik')->label('NIK')->copyable(),
                    TextEntry::make('birth')
                        ->label('Tempat, Tanggal Lahir')
                        ->state(fn (Application $record): string => $record->child->birth_place.', '.$record->child->birth_date?->translatedFormat('d F Y')),
                    TextEntry::make('child.household.kk_number')->label('No. KK')->placeholder('-')->copyable(),
                    TextEntry::make('child.parent_status')->label('Status Anak')->badge()
                        ->color(fn ($state) => match ($state) {
                            'Yatim' => 'warning',
                            'Piatu' => 'info',
                            'Yatim Piatu' => 'danger',
                            'Lengkap' => 'success',
                            default => 'gray',
                        }),
                    TextEntry::make('address')
                        ->label('Alamat')
                        ->state(function (Application $record): string {
                            $address = $record->child->address;

                            return implode(', ', array_filter([
                                $address?->address_detail,
                                $address?->rt ? 'RT '.$address->rt : null,
                                $address?->rw ? 'RW '.$address->rw : null,
                                $address?->village?->name,
                                $address?->village?->district?->name,
                            ])) ?: '-';
                        }),
                    TextEntry::make('father')->label('Ayah')->state(fn (Application $r) => ChildInfolist::person($r->child, RelationshipType::FATHER)),
                    TextEntry::make('mother')->label('Ibu')->state(fn (Application $r) => ChildInfolist::person($r->child, RelationshipType::MOTHER)),
                    TextEntry::make('guardian')->label('Wali')->state(fn (Application $r) => ChildInfolist::person($r->child, RelationshipType::GUARDIAN)),
                ])
                ->columnSpanFull(),

            Section::make('Rekening Penerima')
                ->schema([
                    TextEntry::make('bank')
                        ->hiddenLabel()
                        ->listWithLineBreaks()
                        ->placeholder('Belum ada data rekening.')
                        ->state(fn (Application $record): array => self::bankLines($record)),
                ])
                ->columnSpanFull(),
        ]);
    }

    /** @return list<string> */
    private static function bankLines(Application $application): array
    {
        return $application->bankAccounts->map(function (BankAccount $account) use ($application): string {
            $holder = match ($account->holder_type) {
                BankAccountHolderType::CHILD => 'anak',
                BankAccountHolderType::FATHER => 'ayah',
                BankAccountHolderType::MOTHER => 'ibu',
                BankAccountHolderType::GUARDIAN => 'wali',
                default => 'tidak diketahui',
            };

            $line = sprintf('%s · %s · a.n. %s (%s)', $account->bank_name, $account->account_number, $account->account_holder_name ?: '-', $holder);

            return self::sharedWithOtherHousehold($application, $account) ? '⚠ '.$line.' — nomor ini juga terdaftar pada KK lain' : $line;
        })->all();
    }

    /** Rekening yang sama boleh dipakai saudara satu KK, tapi tidak lintas KK. */
    private static function sharedWithOtherHousehold(Application $application, BankAccount $account): bool
    {
        $householdId = $application->child?->household_id;
        if ($householdId === null) {
            return false;
        }

        return BankAccount::query()
            ->where('account_number', $account->account_number)
            ->where('application_id', '!=', $application->id)
            ->whereHas('application.child', fn ($child) => $child->where('household_id', '!=', $householdId))
            ->exists();
    }
}

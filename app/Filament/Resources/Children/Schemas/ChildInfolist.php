<?php

namespace App\Filament\Resources\Children\Schemas;

use App\Enums\LivingWith;
use App\Enums\PersonStatus;
use App\Enums\RelationshipType;
use App\Models\Child;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ChildInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Progres Permohonan')
                ->description('Posisi permohonan terbaru anak ini dan riwayat tindakannya.')
                ->schema([
                    ViewEntry::make('progress')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->view('filament.infolists.application-progress'),
                ])
                ->columnSpanFull(),

            Section::make('Identitas Anak')
                ->columns(2)
                ->schema([
                    TextEntry::make('nik')->label('NIK')->copyable(),
                    TextEntry::make('name')->label('Nama Lengkap'),
                    TextEntry::make('birth_place')
                        ->label('Tempat, Tanggal Lahir')
                        ->state(fn (Child $record): string => $record->birth_place.', '.$record->birth_date?->translatedFormat('d F Y')),
                    TextEntry::make('age')
                        ->label('Usia')
                        ->state(fn (Child $record): string => $record->birth_date ? $record->birth_date->age.' tahun' : '-'),
                    TextEntry::make('gender')
                        ->label('Jenis Kelamin')
                        ->formatStateUsing(fn ($state) => match ($state?->value ?? $state) {
                            'male' => 'Laki-laki',
                            'female' => 'Perempuan',
                            default => '-',
                        }),
                    TextEntry::make('household.kk_number')->label('No. KK')->placeholder('-'),
                    TextEntry::make('parent_status')
                        ->label('Status Anak')
                        ->badge()
                        ->color(fn ($state) => match ($state) {
                            'Yatim' => 'warning',
                            'Piatu' => 'info',
                            'Yatim Piatu' => 'danger',
                            'Lengkap' => 'success',
                            default => 'gray',
                        }),
                ])
                ->columnSpanFull(),

            Section::make('Alamat & Tempat Tinggal')
                ->columns(2)
                ->schema([
                    TextEntry::make('address.village.name')->label('Kelurahan / Desa')->placeholder('-'),
                    TextEntry::make('address.village.district.name')->label('Kecamatan')->placeholder('-'),
                    TextEntry::make('rt_rw')
                        ->label('RT / RW')
                        ->state(fn (Child $record): string => ($record->address?->rt ?? '-').' / '.($record->address?->rw ?? '-')),
                    TextEntry::make('address.living_with')
                        ->label('Tinggal Bersama')
                        ->formatStateUsing(fn ($state) => match ($state?->value ?? $state) {
                            LivingWith::PARENTS->value => 'Orang Tua',
                            LivingWith::ALONE->value => 'Sendiri',
                            LivingWith::RELATIVE->value => 'Kerabat',
                            LivingWith::OTHER->value => 'Lainnya',
                            default => '-',
                        }),
                    TextEntry::make('address.address_detail')->label('Detail Alamat')->placeholder('-')->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make('Orang Tua & Wali')
                ->columns(3)
                ->schema([
                    TextEntry::make('father')->label('Ayah')->state(fn (Child $record) => self::person($record, RelationshipType::FATHER)),
                    TextEntry::make('mother')->label('Ibu')->state(fn (Child $record) => self::person($record, RelationshipType::MOTHER)),
                    TextEntry::make('guardian')->label('Wali')->state(fn (Child $record) => self::person($record, RelationshipType::GUARDIAN)),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function person(Child $child, RelationshipType $type): string
    {
        $person = $child->relationships->firstWhere('relationship_type', $type)?->person;
        if (! $person) {
            return '-';
        }

        $status = match ($person->status) {
            PersonStatus::ALIVE => 'Masih hidup',
            PersonStatus::DECEASED => 'Meninggal dunia',
            default => 'Status tidak diketahui',
        };

        return trim($person->name.' · '.$status.($person->occupation ? ' · '.$person->occupation : ''));
    }
}

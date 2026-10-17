<?php

namespace App\Filament\Resources\Children\Schemas;

use App\Enums\BankAccountHolderType;
use App\Enums\Gender;
use App\Enums\LivingWith;
use App\Enums\OrganizationUnitType;
use App\Enums\PersonStatus;
use App\Models\Address;
use App\Models\DocumentCategory;
use App\Models\Household;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ChildForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Data Anak & KK')
                        ->description('Informasi pokok anak dan Kartu Keluarga')
                        ->schema([
                            Select::make('household_id')
                                ->label('Kartu Keluarga (KK)')
                                ->relationship(
                                    name: 'household',
                                    titleAttribute: 'kk_number',
                                )
                                ->searchable()
                                ->preload()
                                ->required()
                                ->createOptionForm([
                                    TextInput::make('kk_number')
                                        ->label('Nomor KK')
                                        ->required()
                                        ->maxLength(16),
                                ])
                                ->createOptionUsing(fn (array $data): string => Household::create($data)->id),

                            TextInput::make('name')
                                ->label('Nama Lengkap Anak')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('nik')
                                ->label('NIK Anak')
                                ->required()
                                ->length(16)
                                ->unique(ignoreRecord: true),

                            TextInput::make('birth_place')
                                ->label('Tempat Lahir')
                                ->required()
                                ->maxLength(100),

                            DatePicker::make('birth_date')
                                ->label('Tanggal Lahir')
                                ->required()
                                ->maxDate(now()),

                            Select::make('gender')
                                ->label('Jenis Kelamin')
                                ->options([
                                    Gender::MALE->value => 'Laki-laki',
                                    Gender::FEMALE->value => 'Perempuan',
                                ])
                                ->required(),
                        ]),

                    Step::make('Alamat Domisili')
                        ->description('Lokasi tempat tinggal anak')
                        ->schema([
                            Select::make('address_id')
                                ->label('Alamat / Domisili')
                                ->relationship(
                                    name: 'address',
                                    titleAttribute: 'address_detail',
                                    modifyQueryUsing: function (Builder $query) {
                                        $user = auth()->user();

                                        if (! $user || $user->hasRole('super_admin')) {
                                            return $query;
                                        }

                                        if (! $user->organization_unit_id || ! $user->organizationUnit) {
                                            return $query->whereRaw('1 = 0');
                                        }

                                        $org = $user->organizationUnit;

                                        if ($org->type === OrganizationUnitType::VILLAGE) {
                                            return $query->where('village_id', $org->village_id);
                                        }

                                        if ($org->type === OrganizationUnitType::DISTRICT) {
                                            return $query->whereHas('village', fn ($q) => $q->where('district_id', $org->district_id));
                                        }

                                        if ($org->type === OrganizationUnitType::REGENCY) {
                                            return $query->whereHas('village.district', fn ($q) => $q->where('regency_id', $org->regency_id));
                                        }

                                        return $query->whereRaw('1 = 0');
                                    }
                                )
                                ->getOptionLabelFromRecordUsing(fn (Address $record) => "RT {$record->rt}/RW {$record->rw} - {$record->village?->name} ({$record->address_detail})")
                                ->searchable()
                                ->preload()
                                ->required()
                                ->createOptionForm([
                                    Select::make('village_id')
                                        ->label('Kelurahan / Desa')
                                        ->relationship(
                                            name: 'village',
                                            titleAttribute: 'name',
                                            modifyQueryUsing: function (Builder $query) {
                                                $user = auth()->user();

                                                if (! $user || $user->hasRole('super_admin')) {
                                                    return $query;
                                                }

                                                if (! $user->organization_unit_id || ! $user->organizationUnit) {
                                                    return $query->whereRaw('1 = 0');
                                                }

                                                $org = $user->organizationUnit;

                                                if ($org->type === OrganizationUnitType::VILLAGE) {
                                                    return $query->where('id', $org->village_id);
                                                }

                                                if ($org->type === OrganizationUnitType::DISTRICT) {
                                                    return $query->where('district_id', $org->district_id);
                                                }

                                                if ($org->type === OrganizationUnitType::REGENCY) {
                                                    return $query->whereHas('district', fn ($q) => $q->where('regency_id', $org->regency_id));
                                                }

                                                return $query->whereRaw('1 = 0');
                                            }
                                        )
                                        ->searchable()
                                        ->preload()
                                        ->required(),

                                    TextInput::make('rt')
                                        ->label('RT')
                                        ->required()
                                        ->maxLength(5),

                                    TextInput::make('rw')
                                        ->label('RW')
                                        ->required()
                                        ->maxLength(5),

                                    Select::make('living_with')
                                        ->label('Tinggal Bersama')
                                        ->options([
                                            LivingWith::PARENTS->value => 'Orang Tua',
                                            LivingWith::ALONE->value => 'Sendiri',
                                            LivingWith::RELATIVE->value => 'Kerabat',
                                            LivingWith::OTHER->value => 'Lainnya',
                                        ])
                                        ->required(),

                                    Textarea::make('address_detail')
                                        ->label('Detail Alamat')
                                        ->maxLength(500),
                                ])
                                ->createOptionUsing(function (array $data): string {
                                    $user = auth()->user();
                                    if ($user && ! $user->isInOrganizationScope($data['village_id'] ?? null)) {
                                        abort(403, 'Anda tidak berwenang membuat alamat di luar wilayah organisasi Anda.');
                                    }

                                    return Address::create($data)->id;
                                }),
                        ]),

                    Step::make('Orang Tua & Wali')
                        ->description('Data Ayah, Ibu, dan Wali (opsional)')
                        ->schema([
                            Section::make('Data Ayah')
                                ->schema([
                                    TextInput::make('father_name')
                                        ->label('Nama Ayah')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('father_occupation')
                                        ->label('Pekerjaan Ayah')
                                        ->maxLength(100),
                                    Select::make('father_status')
                                        ->label('Status Ayah')
                                        ->options([
                                            PersonStatus::ALIVE->value => 'Masih Hidup',
                                            PersonStatus::DECEASED->value => 'Meninggal Dunia',
                                            PersonStatus::UNKNOWN->value => 'Tidak Diketahui',
                                        ])
                                        ->default(PersonStatus::ALIVE->value)
                                        ->required(),
                                ])->columns(3),

                            Section::make('Data Ibu')
                                ->schema([
                                    TextInput::make('mother_name')
                                        ->label('Nama Ibu')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('mother_occupation')
                                        ->label('Pekerjaan Ibu')
                                        ->maxLength(100),
                                    Select::make('mother_status')
                                        ->label('Status Ibu')
                                        ->options([
                                            PersonStatus::ALIVE->value => 'Masih Hidup',
                                            PersonStatus::DECEASED->value => 'Meninggal Dunia',
                                            PersonStatus::UNKNOWN->value => 'Tidak Diketahui',
                                        ])
                                        ->default(PersonStatus::ALIVE->value)
                                        ->required(),
                                ])->columns(3),

                            Section::make('Data Wali (Opsional / Wajib jika rekening atas nama wali)')
                                ->schema([
                                    TextInput::make('guardian_name')
                                        ->label('Nama Wali')
                                        ->maxLength(255),
                                    TextInput::make('guardian_occupation')
                                        ->label('Pekerjaan Wali')
                                        ->maxLength(100),
                                    Select::make('guardian_status')
                                        ->label('Status Wali')
                                        ->options([
                                            PersonStatus::ALIVE->value => 'Masih Hidup',
                                            PersonStatus::DECEASED->value => 'Meninggal Dunia',
                                            PersonStatus::UNKNOWN->value => 'Tidak Diketahui',
                                        ])
                                        ->default(PersonStatus::ALIVE->value),
                                ])->columns(3),
                        ]),

                    Step::make('Rekening Bank')
                        ->description('Informasi rekening pencairan bantuan')
                        ->schema([
                            TextInput::make('bank_name')
                                ->label('Nama Bank')
                                ->default('Bank Riau Kepri Syariah')
                                ->required()
                                ->maxLength(100),

                            TextInput::make('account_number')
                                ->label('Nomor Rekening')
                                ->required()
                                ->maxLength(50),

                            TextInput::make('account_holder_name')
                                ->label('Nama Pemilik Rekening')
                                ->required()
                                ->maxLength(255),

                            Select::make('holder_type')
                                ->label('Rekening Milik')
                                ->options([
                                    BankAccountHolderType::CHILD->value => 'Anak Sendiri',
                                    BankAccountHolderType::FATHER->value => 'Ayah',
                                    BankAccountHolderType::MOTHER->value => 'Ibu',
                                    BankAccountHolderType::GUARDIAN->value => 'Wali',
                                ])
                                ->default(BankAccountHolderType::CHILD->value)
                                ->required(),
                        ])->columns(2),

                    Step::make('Dokumen & Pengajuan')
                        ->description('Lampiran dokumen persyaratan')
                        ->schema(function (): array {
                            $categories = DocumentCategory::orderBy('name')->get();
                            $fields = [
                                TextInput::make('period_year')
                                    ->label('Tahun Periode / Anggaran')
                                    ->numeric()
                                    ->default(now()->year)
                                    ->required(),
                            ];

                            foreach ($categories as $category) {
                                $fields[] = Section::make($category->name)
                                    ->description($category->is_required ? 'Wajib diunggah' : 'Opsional')
                                    ->schema([
                                        FileUpload::make("document_file_{$category->id}")
                                            ->label('File (PDF / Gambar)')
                                            ->disk('local')
                                            ->directory('temp-uploads')
                                            ->acceptedFileTypes($category->allowed_mime_types ?: ['application/pdf', 'image/jpeg', 'image/png'])
                                            ->maxSize($category->max_file_size_kb ?: 2048)
                                            ->maxParallelUploads(1)
                                            ->required($category->is_required),
                                    ]);
                            }

                            return $fields;
                        }),
                ])
                    ->nextAction(fn (Action $action) => $action->label('Lanjutkan')) // Mengubah tulisan "Next"
                    ->previousAction(fn (Action $action) => $action->label('Kembali')) // Mengubah tulisan "Previous"
                    ->columnSpanFull(),
            ]);
    }
}

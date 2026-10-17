<?php

namespace App\Filament\Resources\Children\RelationManagers;

use App\Enums\Gender;
use App\Enums\PersonStatus;
use App\Enums\RelationshipType;
use App\Models\ChildRelationship;
use App\Models\Person;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RelationshipsRelationManager extends RelationManager
{
    protected static string $relationship = 'relationships';

    protected static ?string $title = 'Data Orang Tua & Wali';

    protected static ?string $modelLabel = 'Orang Tua / Wali';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('relationship_type')
                    ->label('Hubungan Keluarga')
                    ->options([
                        RelationshipType::FATHER->value => 'Ayah',
                        RelationshipType::MOTHER->value => 'Ibu',
                        RelationshipType::GUARDIAN->value => 'Wali',
                    ])
                    ->required(),

                Select::make('person_id')
                    ->label('Orang Tua / Wali')
                    ->relationship(
                        name: 'person',
                        titleAttribute: 'name',
                    )
                    ->getOptionLabelFromRecordUsing(fn (Person $record) => "{$record->name} (".match ($record->status?->value ?? $record->status) {
                        'alive' => 'Masih Hidup',
                        'deceased' => 'Meninggal Dunia',
                        default => 'Tidak Diketahui',
                    }.')')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),

                        Select::make('gender')
                            ->label('Jenis Kelamin')
                            ->options([
                                Gender::MALE->value => 'Laki-laki',
                                Gender::FEMALE->value => 'Perempuan',
                            ])
                            ->required(),

                        TextInput::make('occupation')
                            ->label('Pekerjaan')
                            ->maxLength(100),

                        Select::make('status')
                            ->label('Status Keberadaan')
                            ->options([
                                PersonStatus::ALIVE->value => 'Masih Hidup',
                                PersonStatus::DECEASED->value => 'Meninggal Dunia',
                                PersonStatus::UNKNOWN->value => 'Tidak Diketahui',
                            ])
                            ->default(PersonStatus::ALIVE->value)
                            ->required(),
                    ])
                    ->createOptionUsing(function (array $data): string {
                        return Person::create($data)->id;
                    }),

                Checkbox::make('is_primary')
                    ->label('Sebagai Kontak Utama / Pengasuh Utama')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('relationship_type')
            ->columns([
                TextColumn::make('relationship_type')
                    ->label('Hubungan')
                    ->badge()
                    ->color(fn (ChildRelationship $record) => match ($record->relationship_type?->value ?? $record->relationship_type) {
                        'father' => 'info',
                        'mother' => 'success',
                        'guardian' => 'warning',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn ($state) => match ($state?->value ?? $state) {
                        'father' => 'Ayah',
                        'mother' => 'Ibu',
                        'guardian' => 'Wali',
                        default => $state,
                    })
                    ->sortable(),

                TextColumn::make('person.name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('person.gender')
                    ->label('L/P')
                    ->formatStateUsing(fn ($state) => match ($state?->value ?? $state) {
                        'male' => 'L',
                        'female' => 'P',
                        default => '-',
                    }),

                TextColumn::make('person.occupation')
                    ->label('Pekerjaan')
                    ->default('-'),

                TextColumn::make('person.status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (ChildRelationship $record) => match ($record->person?->status?->value ?? $record->person?->status) {
                        'alive' => 'success',
                        'deceased' => 'danger',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn ($state) => match ($state?->value ?? $state) {
                        'alive' => 'Masih Hidup',
                        'deceased' => 'Meninggal',
                        default => 'Tidak Diketahui',
                    }),

                IconColumn::make('is_primary')
                    ->label('Utama')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('relationship_type')
                    ->label('Jenis Hubungan')
                    ->options([
                        RelationshipType::FATHER->value => 'Ayah',
                        RelationshipType::MOTHER->value => 'Ibu',
                        RelationshipType::GUARDIAN->value => 'Wali',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Orang Tua / Wali'),
            ])
            ->recordActions([
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

<?php

namespace App\Filament\Resources\Children\Pages;

use App\Enums\Gender;
use App\Enums\PersonStatus;
use App\Enums\RelationshipType;
use App\Filament\Resources\Children\ChildResource;
use App\Models\Address;
use App\Models\ChildRelationship;
use App\Models\Person;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditChild extends EditRecord
{
    protected static string $resource = ChildResource::class;

    protected static ?string $title = 'Edit Data Anak';

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $child = $this->getRecord();

        $father = $child->relationships()
            ->where('relationship_type', RelationshipType::FATHER->value)
            ->with('person')
            ->first()?->person;

        if ($father) {
            $data['father_name'] = $father->name;
            $data['father_occupation'] = $father->occupation;
            $data['father_status'] = $father->status?->value ?? $father->status;
        }

        $mother = $child->relationships()
            ->where('relationship_type', RelationshipType::MOTHER->value)
            ->with('person')
            ->first()?->person;

        if ($mother) {
            $data['mother_name'] = $mother->name;
            $data['mother_occupation'] = $mother->occupation;
            $data['mother_status'] = $mother->status?->value ?? $mother->status;
        }

        $guardian = $child->relationships()
            ->where('relationship_type', RelationshipType::GUARDIAN->value)
            ->with('person')
            ->first()?->person;

        if ($guardian) {
            $data['guardian_name'] = $guardian->name;
            $data['guardian_occupation'] = $guardian->occupation;
            $data['guardian_status'] = $guardian->status?->value ?? $guardian->status;
        }

        return $data;
    }

    protected function beforeSave(): void
    {
        $addressId = $this->data['address_id'] ?? null;
        if ($addressId && $addressId !== $this->getRecord()->address_id) {
            $address = Address::find($addressId);
            $user = auth()->user();
            if (! $address || ($user && ! $user->isInOrganizationScope($address->village_id))) {
                abort(403, 'Anda tidak berhak memindahkan anak ke luar wilayah kelurahan Anda.');
            }
        }

        if (($this->data['holder_type'] ?? null) === 'guardian' && blank($this->data['guardian_name'] ?? null)) {
            throw ValidationException::withMessages([
                'data.guardian_name' => 'Data wali wajib diisi karena rekening atas nama wali.',
            ]);
        }
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data) {
            $childData = collect($data)->only([
                'household_id',
                'address_id',
                'name',
                'nik',
                'birth_place',
                'birth_date',
                'gender',
            ])->toArray();

            $record->update($childData);

            $this->syncPerson(
                $record,
                RelationshipType::FATHER,
                $data['father_name'] ?? null,
                $data['father_occupation'] ?? null,
                $data['father_status'] ?? PersonStatus::ALIVE->value,
                Gender::MALE,
                isPrimary: true,
            );

            $this->syncPerson(
                $record,
                RelationshipType::MOTHER,
                $data['mother_name'] ?? null,
                $data['mother_occupation'] ?? null,
                $data['mother_status'] ?? PersonStatus::ALIVE->value,
                Gender::FEMALE,
                isPrimary: false,
            );

            $this->syncPerson(
                $record,
                RelationshipType::GUARDIAN,
                $data['guardian_name'] ?? null,
                $data['guardian_occupation'] ?? null,
                $data['guardian_status'] ?? PersonStatus::ALIVE->value,
                Gender::MALE,
                isPrimary: false,
            );

            return $record;
        });
    }

    protected function syncPerson(
        Model $child,
        RelationshipType $type,
        ?string $name,
        ?string $occupation,
        ?string $status,
        Gender $gender,
        bool $isPrimary,
    ): void {
        $relationship = $child->relationships()
            ->where('relationship_type', $type->value)
            ->with('person')
            ->first();

        if (blank($name)) {
            if ($relationship) {
                $person = $relationship->person;
                $relationship->delete();
                if ($person) {
                    $person->delete();
                }
            }

            return;
        }

        if ($relationship && $relationship->person) {
            $relationship->person->update([
                'name' => $name,
                'occupation' => $occupation,
                'status' => $status,
            ]);

            return;
        }

        $person = Person::create([
            'name' => $name,
            'gender' => $gender->value,
            'occupation' => $occupation,
            'status' => $status,
        ]);

        ChildRelationship::create([
            'child_id' => $child->id,
            'person_id' => $person->id,
            'relationship_type' => $type->value,
            'is_primary' => $isPrimary,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}

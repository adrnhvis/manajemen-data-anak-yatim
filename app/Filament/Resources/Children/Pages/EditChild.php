<?php

namespace App\Filament\Resources\Children\Pages;

use App\Enums\ApplicationStatus;
use App\Enums\Gender;
use App\Enums\PersonStatus;
use App\Enums\RelationshipType;
use App\Enums\ReviewLevel;
use App\Filament\Resources\Children\ChildResource;
use App\Models\Address;
use App\Models\Application;
use App\Models\BankAccount;
use App\Models\ChildRelationship;
use App\Models\DocumentCategory;
use App\Models\Person;
use App\Models\User;
use App\Services\DocumentService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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

        $application = $child->applications()
            ->withTrashed()
            ->with('documents')
            ->latest()
            ->first();

        if ($application) {
            $data['period_year'] = $application->period_year;

            $bankAccount = $application->bankAccounts()->latest()->first();
            if ($bankAccount) {
                $data['bank_name'] = $bankAccount->bank_name;
                $data['account_number'] = $bankAccount->account_number;
                $data['account_holder_name'] = $bankAccount->account_holder_name;
                $data['holder_type'] = $bankAccount->holder_type?->value ?? $bankAccount->holder_type;
            }

            foreach ($application->documents as $document) {
                $data['document_file_'.$document->document_category_id] = $document->storage_path;
            }
        }

        return $data;
    }

    protected function beforeSave(): void
    {
        $addressId = $this->data['address_id'] ?? null;
        if ($addressId && $addressId !== $this->getRecord()->address_id) {
            $address = Address::find($addressId);
            $user = Auth::user();
            if (! $address || ! ($user instanceof User) || ! $user->isInOrganizationScope($address->village_id)) {
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

            $this->syncApplicationData($record, $data);

            return $record;
        });
    }

    private function syncApplicationData(Model $child, array $data): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $periodYear = (int) ($data['period_year'] ?? now()->year);
        $application = $child->applications()
            ->withTrashed()
            ->where('period_year', $periodYear)
            ->first();

        if ($application?->trashed()) {
            $application->restore();
        }

        if (! $application) {
            Gate::authorize('create', Application::class);

            $application = Application::create([
                'child_id' => $child->getKey(),
                'period_year' => $periodYear,
                'status' => ApplicationStatus::DRAFT,
                'current_review_level' => ReviewLevel::VILLAGE,
                'created_by' => $user->getAuthIdentifier(),
            ]);
        }

        $bankData = Arr::only($data, [
            'bank_name',
            'account_number',
            'account_holder_name',
            'holder_type',
        ]);

        $bankAccount = $application->bankAccounts()->latest()->first();
        if ($bankAccount) {
            $hasBankChanges = collect($bankData)->contains(
                fn ($value, $key) => $bankAccount->getRawOriginal($key) !== $value,
            );

            if ($hasBankChanges) {
                Gate::authorize('update', $bankAccount);
                $bankAccount->update($bankData);
            }
        } else {
            Gate::authorize('update', $application);
            Gate::authorize('create', BankAccount::class);
            $application->bankAccounts()->create($bankData);
        }

        $documentService = app(DocumentService::class);
        foreach ($data as $key => $filePaths) {
            if (! str_starts_with((string) $key, 'document_file_') || blank($filePaths)) {
                continue;
            }

            $categoryId = str_replace('document_file_', '', (string) $key);
            $filePath = is_array($filePaths) ? Arr::first($filePaths) : $filePaths;
            if (blank($filePath)) {
                continue;
            }

            $existingDocument = $application->documents()
                ->where('document_category_id', $categoryId)
                ->latest()
                ->first();

            if ($existingDocument && $existingDocument->storage_path === $filePath) {
                continue;
            }

            $category = DocumentCategory::find($categoryId);
            if (! $category) {
                throw ValidationException::withMessages([
                    "data.{$key}" => 'Kategori dokumen tidak ditemukan. Silakan muat ulang halaman dan coba lagi.',
                ]);
            }

            $storedPath = Storage::disk('local')->path($filePath);
            if (! is_file($storedPath)) {
                throw ValidationException::withMessages([
                    "data.{$key}" => 'File dokumen tidak ditemukan setelah diunggah. Silakan unggah ulang file tersebut.',
                ]);
            }

            $file = new UploadedFile(
                $storedPath,
                basename($filePath),
                mime_content_type($storedPath) ?: null,
                null,
                true,
            );

            if ($existingDocument) {
                $documentService->replace($user, $existingDocument, $file);
            } else {
                $documentService->upload($user, $application, $category, $file);
            }
        }
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

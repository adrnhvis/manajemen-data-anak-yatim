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
use App\Models\Child;
use App\Models\ChildRelationship;
use App\Models\DocumentCategory;
use App\Models\Person;
use App\Services\DocumentService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CreateChild extends CreateRecord
{
    protected static string $resource = ChildResource::class;

    protected static ?string $title = 'Tambah Data Anak';

    protected function beforeCreate(): void
    {
        $addressId = $this->data['address_id'] ?? null;
        if ($addressId) {
            $address = Address::find($addressId);
            $user = auth()->user();
            if (! $address || ($user && ! $user->isInOrganizationScope($address->village_id))) {
                abort(403, 'Anda tidak berhak mendaftarkan anak di luar wilayah kelurahan Anda.');
            }
        }

        if (($this->data['holder_type'] ?? null) === 'guardian' && blank($this->data['guardian_name'] ?? null)) {
            throw ValidationException::withMessages([
                'data.guardian_name' => 'Data wali wajib diisi karena rekening atas nama wali.',
            ]);
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            // 1. Create Child
            $childData = collect($data)->only([
                'household_id',
                'address_id',
                'name',
                'nik',
                'birth_place',
                'birth_date',
                'gender',
            ])->toArray();

            $child = Child::create($childData);
            $user = auth()->user();

            // 2. Create Father (Person + Relationship)
            if (! empty($data['father_name'])) {
                $father = Person::create([
                    'name' => $data['father_name'],
                    'gender' => Gender::MALE->value,
                    'occupation' => $data['father_occupation'] ?? null,
                    'status' => $data['father_status'] ?? PersonStatus::ALIVE->value,
                ]);
                ChildRelationship::create([
                    'child_id' => $child->id,
                    'person_id' => $father->id,
                    'relationship_type' => RelationshipType::FATHER->value ?? 'father',
                    'is_primary' => true,
                ]);
            }

            // 3. Create Mother (Person + Relationship)
            if (! empty($data['mother_name'])) {
                $mother = Person::create([
                    'name' => $data['mother_name'],
                    'gender' => Gender::FEMALE->value,
                    'occupation' => $data['mother_occupation'] ?? null,
                    'status' => $data['mother_status'] ?? PersonStatus::ALIVE->value,
                ]);
                ChildRelationship::create([
                    'child_id' => $child->id,
                    'person_id' => $mother->id,
                    'relationship_type' => RelationshipType::MOTHER->value ?? 'mother',
                    'is_primary' => false,
                ]);
            }

            // 4. Create Guardian if filled (Person + Relationship)
            if (! empty($data['guardian_name'])) {
                $guardian = Person::create([
                    'name' => $data['guardian_name'],
                    'gender' => Gender::MALE->value,
                    'occupation' => $data['guardian_occupation'] ?? null,
                    'status' => $data['guardian_status'] ?? PersonStatus::ALIVE->value,
                ]);
                ChildRelationship::create([
                    'child_id' => $child->id,
                    'person_id' => $guardian->id,
                    'relationship_type' => RelationshipType::GUARDIAN->value ?? 'guardian',
                    'is_primary' => false,
                ]);
            }

            // 5. Create Application
            $application = Application::create([
                'child_id' => $child->id,
                'period_year' => $data['period_year'] ?? now()->year,
                'status' => ApplicationStatus::DRAFT->value,
                'current_review_level' => ReviewLevel::VILLAGE->value,
                'created_by' => $user->getKey(),
            ]);

            // 6. Create Bank Account
            BankAccount::create([
                'application_id' => $application->id,
                'bank_name' => $data['bank_name'] ?? 'Bank Riau Kepri Syariah',
                'account_number' => $data['account_number'],
                'account_holder_name' => $data['account_holder_name'],
                'holder_type' => $data['holder_type'],
            ]);

            // 7. Process Documents via DocumentService
            $documentService = app(DocumentService::class);
            foreach (collect($data)->filter(fn ($value, $key) => str_starts_with((string) $key, 'document_file_') && filled($value)) as $key => $filePath) {
                $categoryId = str_replace('document_file_', '', $key);
                $category = DocumentCategory::find($categoryId);
                if (! $category) {
                    continue;
                }

                $filePath = is_array($filePath) ? Arr::first($filePath) : $filePath;
                if (blank($filePath)) {
                    continue;
                }

                $storedPath = Storage::disk('local')->path($filePath);
                if (! file_exists($storedPath)) {
                    continue;
                }

                $file = new UploadedFile(
                    $storedPath,
                    basename($filePath),
                    mime_content_type($storedPath),
                    null,
                    true
                );

                $documentService->upload($user, $application, $category, $file);
            }

            return $child;
        });
    }
}

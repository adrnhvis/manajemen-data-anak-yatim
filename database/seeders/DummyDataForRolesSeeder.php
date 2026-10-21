<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Enums\RelationshipType;
use App\Models\Address;
use App\Models\Application;
use App\Models\BankAccount;
use App\Models\Child;
use App\Models\ChildRelationship;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Household;
use App\Models\Person;
use App\Models\User;
use App\Models\Village;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DummyDataForRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $villages = Village::all();
        if ($villages->isEmpty()) {
            $this->command->warn('No villages found. Run RegionSeeder first.');

            return;
        }

        $superAdmin = User::role('super_admin')->first() ?? User::firstOrCreate(
            ['email' => 'superadmin@example.test'],
            ['name' => 'Super Admin', 'password' => Hash::make('password'), 'email_verified_at' => now()]
        );

        // 1. Create Persons (Parents/Guardians) - 300 persons
        $persons = Person::factory(300)->create();

        // 2. Create Households - 100 households
        $households = Household::factory(100)->create();

        // 3. Create Children, Addresses, and Relationships
        $children = collect();
        $personIndex = 0;

        foreach ($households as $household) {
            $numChildren = rand(1, 3);

            $address = Address::factory()->create([
                'village_id' => $villages->random()->id,
            ]);

            $father = $persons[$personIndex++];
            $mother = $persons[$personIndex++];

            for ($i = 0; $i < $numChildren; $i++) {
                $child = Child::factory()->create([
                    'household_id' => $household->id,
                    'address_id' => $address->id,
                ]);
                $children->push($child);

                // Attach Father
                ChildRelationship::create([
                    'child_id' => $child->id,
                    'person_id' => $father->id,
                    'relationship_type' => RelationshipType::FATHER,
                    'is_primary' => true,
                ]);

                // Attach Mother
                ChildRelationship::create([
                    'child_id' => $child->id,
                    'person_id' => $mother->id,
                    'relationship_type' => RelationshipType::MOTHER,
                    'is_primary' => true,
                ]);

                // Optional Guardian
                if (rand(1, 10) > 8) {
                    ChildRelationship::create([
                        'child_id' => $child->id,
                        'person_id' => $persons[$personIndex++]->id,
                        'relationship_type' => RelationshipType::GUARDIAN,
                        'is_primary' => true,
                    ]);
                }
            }
        }

        // Ensure each village has at least 10 children to meet the user's request ("min 10 data untuk masing masing kelurahan")
        foreach ($villages as $village) {
            $existingCount = Child::whereHas('address', fn ($q) => $q->where('village_id', $village->id))->count();
            $needed = max(0, 10 - $existingCount);

            for ($k = 0; $k < $needed; $k++) {
                $household = $households->random();
                $address = Address::factory()->create([
                    'village_id' => $village->id,
                ]);

                $father = $persons[$personIndex++ % $persons->count()];
                $mother = $persons[$personIndex++ % $persons->count()];

                $child = Child::factory()->create([
                    'household_id' => $household->id,
                    'address_id' => $address->id,
                ]);
                $children->push($child);

                ChildRelationship::create([
                    'child_id' => $child->id,
                    'person_id' => $father->id,
                    'relationship_type' => RelationshipType::FATHER,
                    'is_primary' => true,
                ]);

                ChildRelationship::create([
                    'child_id' => $child->id,
                    'person_id' => $mother->id,
                    'relationship_type' => RelationshipType::MOTHER,
                    'is_primary' => true,
                ]);
            }
        }

        $documentCategories = DocumentCategory::all();
        if ($documentCategories->isEmpty()) {
            $documentCategories = DocumentCategory::factory(3)->create();
        }

        // 4. Create Applications, Bank Accounts, and Documents
        $statuses = ApplicationStatus::cases();

        foreach ($children as $child) {
            $years = [2025, 2026];

            foreach ($years as $year) {
                $status = $statuses[array_rand($statuses)];
                $appUser = $superAdmin;

                $submittedAt = null;
                $validatedAt = null;
                $verifiedAt = null;

                if ($status !== ApplicationStatus::DRAFT) {
                    $submittedAt = now()->subDays(rand(10, 20));
                }
                if (in_array($status, [ApplicationStatus::VALIDATED, ApplicationStatus::VERIFIED, ApplicationStatus::REJECTED])) {
                    $validatedAt = now()->subDays(rand(5, 9));
                }
                if ($status === ApplicationStatus::VERIFIED) {
                    $verifiedAt = now()->subDays(rand(1, 4));
                }

                $application = Application::factory()->create([
                    'child_id' => $child->id,
                    'period_year' => $year,
                    'status' => $status,
                    'created_by' => $appUser->id,
                    'submitted_at' => $submittedAt,
                    'validated_at' => $validatedAt,
                    'verified_at' => $verifiedAt,
                ]);

                // Bank Account
                BankAccount::factory()->create([
                    'application_id' => $application->id,
                ]);

                // Documents (At least 1-2 documents per application using correct column names)
                foreach ($documentCategories->random(rand(1, 2)) as $category) {
                    Document::create([
                        'application_id' => $application->id,
                        'document_category_id' => $category->id,
                        'original_filename' => 'dokumen_'.fake()->word().'.pdf',
                        'storage_path' => 'documents/dummy.pdf',
                        'mime_type' => 'application/pdf',
                        'file_size' => rand(10000, 500000),
                        'checksum' => hash('sha256', 'dummy'),
                        'uploaded_by' => $appUser->id,
                        'uploaded_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('Dummy data for all villages (min 10 per village) created successfully with children, household NIK, parents, optional guardians, bank accounts, and documents!');
    }
}

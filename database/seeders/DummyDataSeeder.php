<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Enums\RelationshipType;
use App\Models\Address;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Child;
use App\Models\ChildRelationship;
use App\Models\Household;
use App\Models\Person;
use App\Models\User;
use App\Models\Village;
use App\Models\WorkflowHistory;
use Illuminate\Database\Seeder;

class DummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $villages = Village::all();
        if ($villages->isEmpty()) {
            $this->command->warn('No villages found. Skipping DummyDataSeeder.');

            return;
        }

        $superAdmin = User::role('super_admin')->first();
        $users = User::whereNotNull('organization_unit_id')->get();

        // 1. Create Persons (Parents/Guardians)
        $persons = Person::factory(150)->create();

        // 2. Create Households
        $households = Household::factory(50)->create();

        // 3. Create Children, Addresses, and Relationships
        $children = collect();
        $personIndex = 0;

        foreach ($households as $household) {
            // Randomly 1 to 3 children per household
            $numChildren = rand(1, 3);

            // Shared address per household (typically)
            $address = Address::factory()->create([
                'village_id' => $villages->random()->id,
            ]);

            // Pick parents for this household
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

                // Randomly add a guardian
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

        // 4. Create Applications
        $statuses = ApplicationStatus::cases();

        foreach ($children as $index => $child) {
            // Some children have 2 applications (2025 and 2026)
            $years = rand(1, 10) > 7 ? [2025, 2026] : [2026];

            foreach ($years as $year) {
                // Ensure unique status distribution
                $status = $statuses[array_rand($statuses)];

                $appUser = $users->random() ?? $superAdmin;

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
                // Sometimes share same account number across different apps to prove non-unique constraint
                $sharedAccount = '1111-2222-3333';
                $accountNum = rand(1, 10) > 8 ? $sharedAccount : fake()->numerify('11##-####-####');

                BankAccount::factory()->create([
                    'application_id' => $application->id,
                    'account_number' => $accountNum,
                ]);

                // Workflow History
                if ($status !== ApplicationStatus::DRAFT) {
                    WorkflowHistory::create([
                        'application_id' => $application->id,
                        'from_status' => ApplicationStatus::DRAFT,
                        'to_status' => ApplicationStatus::SUBMITTED,
                        'action' => 'submit',
                        'note' => 'Disubmit oleh admin kelurahan',
                        'acted_by' => $appUser->id,
                        'acted_at' => $submittedAt,
                    ]);

                    AuditLog::factory()->create([
                        'user_id' => $appUser->id,
                        'auditable_type' => Application::class,
                        'auditable_id' => $application->id,
                        'action' => 'submitted',
                    ]);
                }
            }
        }
    }
}

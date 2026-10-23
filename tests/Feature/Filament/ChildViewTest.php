<?php

namespace Tests\Feature\Filament;

use App\Enums\ApplicationStatus;
use App\Enums\Gender;
use App\Enums\LivingWith;
use App\Enums\OrganizationUnitType;
use App\Enums\PersonStatus;
use App\Enums\RelationshipType;
use App\Enums\ReviewLevel;
use App\Filament\Resources\Children\Pages\ViewChild;
use App\Models\Address;
use App\Models\Application;
use App\Models\Child;
use App\Models\ChildRelationship;
use App\Models\District;
use App\Models\Household;
use App\Models\OrganizationUnit;
use App\Models\Person;
use App\Models\Province;
use App\Models\Regency;
use App\Models\User;
use App\Models\Village;
use App\Models\WorkflowHistory;
use App\Services\ApplicationProgressService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChildViewTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private Village $village;

    private Village $otherDistrictVillage;

    private Regency $regency;

    private District $district;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $province = Province::create(['name' => 'Riau', 'code' => '14']);
        $this->regency = Regency::create(['province_id' => $province->id, 'name' => 'Pelalawan', 'code' => '1405']);
        $this->district = District::create(['regency_id' => $this->regency->id, 'name' => 'Pangkalan Kerinci', 'code' => '140501']);
        $other = District::create(['regency_id' => $this->regency->id, 'name' => 'Bandar Sei Kijang', 'code' => '140502']);
        $this->village = Village::create(['district_id' => $this->district->id, 'name' => 'Kerinci Kota', 'code' => '1405011001']);
        $this->otherDistrictVillage = Village::create(['district_id' => $other->id, 'name' => 'Sekijang', 'code' => '1405021001']);
    }

    public function test_parent_status_scope_matches_the_accessor(): void
    {
        $cases = [
            'Yatim Piatu' => [PersonStatus::DECEASED, PersonStatus::DECEASED],
            'Yatim' => [PersonStatus::DECEASED, PersonStatus::ALIVE],
            'Piatu' => [PersonStatus::ALIVE, PersonStatus::DECEASED],
            'Lengkap' => [PersonStatus::ALIVE, PersonStatus::ALIVE],
        ];

        $children = [];
        foreach ($cases as $label => [$father, $mother]) {
            $child = $this->makeChild($this->village, '2018-01-15');
            $this->addParent($child, RelationshipType::FATHER, $father);
            $this->addParent($child, RelationshipType::MOTHER, $mother);
            $children[$label] = $child;
        }

        foreach ($children as $label => $child) {
            $this->assertSame($label, $child->fresh()->parent_status, "accessor {$label}");
            $ids = Child::query()->parentStatus($label)->pluck('id')->all();
            $this->assertSame([$child->id], $ids, "scope {$label}");
        }
    }

    public function test_age_between_scope_uses_inclusive_years(): void
    {
        $five = $this->makeChild($this->village, now()->subYears(5)->subMonths(2)->toDateString());
        $nine = $this->makeChild($this->village, now()->subYears(9)->toDateString());
        $seventeen = $this->makeChild($this->village, now()->subYears(17)->subMonths(3)->toDateString());

        $this->assertSame([$five->id], Child::query()->ageBetween(0, 5)->pluck('id')->all());
        $this->assertSame([$nine->id], Child::query()->ageBetween(6, 12)->pluck('id')->all());
        $this->assertSame([$seventeen->id], Child::query()->ageBetween(16, 18)->pluck('id')->all());
    }

    public function test_progress_service_reflects_revision_loop_and_final_state(): void
    {
        $creator = User::factory()->create();
        $child = $this->makeChild($this->village, '2018-01-15');
        $application = Application::create([
            'child_id' => $child->id, 'period_year' => (int) now()->year,
            'status' => ApplicationStatus::DRAFT, 'current_review_level' => ReviewLevel::VILLAGE,
            'created_by' => $creator->id,
        ]);
        $t = now()->subDays(10);
        $this->event($application, $creator, 'submit', ApplicationStatus::DRAFT, ApplicationStatus::SUBMITTED, $t);
        $this->event($application, $creator, 'request_revision', ApplicationStatus::SUBMITTED, ApplicationStatus::REVISION_REQUIRED, $t->copy()->addDay(), 'Foto KK buram');

        $application->update(['status' => ApplicationStatus::REVISION_REQUIRED]);
        $progress = (new ApplicationProgressService)->forChild($child);
        $this->assertSame(['revision', 'pending', 'pending'], array_column($progress['steps'], 'state'));
        $this->assertSame('Dikembalikan untuk revisi oleh Kecamatan', $progress['banner']['title']);
        $this->assertSame('Foto KK buram', $progress['banner']['note']);
        $this->assertCount(2, $progress['history']);
        $this->assertSame('Dikembalikan untuk revisi', $progress['history'][0]['label']); // terbaru di atas

        $this->event($application, $creator, 'submit', ApplicationStatus::REVISION_REQUIRED, ApplicationStatus::SUBMITTED, $t->copy()->addDays(3));
        $this->event($application, $creator, 'validate', ApplicationStatus::SUBMITTED, ApplicationStatus::VALIDATED, $t->copy()->addDays(4));
        $this->event($application, $creator, 'verify', ApplicationStatus::VALIDATED, ApplicationStatus::VERIFIED, $t->copy()->addDays(5));
        $application->update(['status' => ApplicationStatus::VERIFIED]);

        $progress = (new ApplicationProgressService)->forChild($child);
        $this->assertSame(['done', 'done', 'done'], array_column($progress['steps'], 'state'));
        $this->assertNull($progress['banner']);
        $this->assertNotNull($progress['steps'][2]['at']);
    }

    public function test_progress_is_null_when_child_has_no_application(): void
    {
        $child = $this->makeChild($this->village, '2018-01-15');

        $this->assertNull((new ApplicationProgressService)->forChild($child));
    }

    public function test_district_admin_can_view_children_in_district_but_not_outside(): void
    {
        $inside = $this->makeChild($this->village, '2018-01-15');
        $outside = $this->makeChild($this->otherDistrictVillage, '2018-01-15');
        $admin = $this->makeUser('admin_kecamatan', OrganizationUnitType::DISTRICT);

        $this->actingAs($admin);

        Livewire::test(ViewChild::class, ['record' => $inside->getKey()])->assertSuccessful();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::test(ViewChild::class, ['record' => $outside->getKey()]);
    }

    private function makeUser(string $role, OrganizationUnitType $type): User
    {
        $unit = OrganizationUnit::create([
            'name' => 'Unit '.$role, 'type' => $type,
            'regency_id' => $this->regency->id, 'district_id' => $this->district->id, 'is_active' => true,
        ]);
        $user = User::factory()->create(['organization_unit_id' => $unit->id]);
        $user->assignRole($role);

        return $user;
    }

    private function makeChild(Village $village, string $birthDate): Child
    {
        $i = ++$this->seq;
        $household = Household::create(['kk_number' => sprintf('140500%06d', $i)]);
        $address = Address::create([
            'village_id' => $village->id, 'rt' => '01', 'rw' => '01',
            'address_detail' => 'Jl. Contoh '.$i, 'living_with' => LivingWith::PARENTS,
        ]);

        return Child::create([
            'household_id' => $household->id, 'address_id' => $address->id, 'name' => 'Anak '.$i,
            'nik' => sprintf('140500%010d', $i), 'birth_place' => 'Pelalawan',
            'birth_date' => $birthDate, 'gender' => Gender::FEMALE,
        ]);
    }

    private function addParent(Child $child, RelationshipType $type, PersonStatus $status): void
    {
        $person = Person::create([
            'name' => ucfirst($type->value).' '.$child->name,
            'gender' => $type === RelationshipType::FATHER ? Gender::MALE->value : Gender::FEMALE->value,
            'occupation' => 'Petani', 'status' => $status->value,
        ]);
        ChildRelationship::create([
            'child_id' => $child->id, 'person_id' => $person->id,
            'relationship_type' => $type->value, 'is_primary' => $type === RelationshipType::FATHER,
        ]);
    }

    private function event(Application $app, User $actor, string $action, ApplicationStatus $from, ApplicationStatus $to, $at, ?string $note = null): void
    {
        WorkflowHistory::create([
            'application_id' => $app->id, 'from_status' => $from, 'to_status' => $to,
            'action' => $action, 'note' => $note, 'acted_by' => $actor->id, 'acted_at' => $at,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\LivingWith;
use App\Enums\OrganizationUnitType;
use App\Enums\ReviewLevel;
use App\Exceptions\InvalidWorkflowTransitionException;
use App\Models\Address;
use App\Models\Application;
use App\Models\Child;
use App\Models\District;
use App\Models\Household;
use App\Models\OrganizationUnit;
use App\Models\Province;
use App\Models\Regency;
use App\Models\User;
use App\Models\Village;
use App\Models\WorkflowHistory;
use App\Services\ApplicationWorkflowService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApplicationWorkflowV1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    protected function setupOrganization(): array
    {
        $province = Province::create(['name' => 'Jawa Barat', 'code' => '32']);
        $regency = Regency::create(['province_id' => $province->id, 'name' => 'Kabupaten Bandung', 'code' => '3204']);
        $district = District::create(['regency_id' => $regency->id, 'name' => 'Kecamatan Bandung Kulon', 'code' => '320401']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Kelurahan Babakan Ciparay', 'code' => '3204011001']);

        $villageUnit = OrganizationUnit::create([
            'name' => 'Kelurahan Babakan Ciparay',
            'type' => OrganizationUnitType::VILLAGE,
            'village_id' => $village->id,
            'is_active' => true,
        ]);
        $districtUnit = OrganizationUnit::create([
            'name' => 'Kecamatan Bandung Kulon',
            'type' => OrganizationUnitType::DISTRICT,
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $regencyUnit = OrganizationUnit::create([
            'name' => 'Kabupaten Bandung',
            'type' => OrganizationUnitType::REGENCY,
            'regency_id' => $regency->id,
            'is_active' => true,
        ]);

        return compact('province', 'regency', 'district', 'village', 'villageUnit', 'districtUnit', 'regencyUnit');
    }

    protected function makeApplication(User $creator, Village $village): Application
    {
        $household = Household::create(['kk_number' => '123456789012']);
        $address = Address::create([
            'village_id' => $village->id,
            'rt' => '01',
            'rw' => '02',
            'address_detail' => 'Jl. Contoh No.1',
            'living_with' => LivingWith::PARENTS,
        ]);
        $child = Child::create([
            'household_id' => $household->id,
            'address_id' => $address->id,
            'name' => 'Ayu',
            'nik' => fake()->unique()->numerify('320401##########'),
            'birth_place' => 'Bandung',
            'birth_date' => '2018-01-15',
            'gender' => 'female',
        ]);

        return Application::create([
            'child_id' => $child->id,
            'period_year' => (int) now()->year,
            'status' => ApplicationStatus::DRAFT,
            'current_review_level' => ReviewLevel::VILLAGE,
            'created_by' => $creator->id,
        ]);
    }

    public function test_happy_path_kelurahan_to_kecamatan_to_kabupaten(): void
    {
        $org = $this->setupOrganization();

        $kelurahan = User::factory()->create(['organization_unit_id' => $org['villageUnit']->id]);
        $kelurahan->assignRole('admin_kelurahan');

        $kecamatan = User::factory()->create(['organization_unit_id' => $org['districtUnit']->id]);
        $kecamatan->assignRole('admin_kecamatan');

        $kabupaten = User::factory()->create(['organization_unit_id' => $org['regencyUnit']->id]);
        $kabupaten->assignRole('admin_kabupaten');

        $workflow = app(ApplicationWorkflowService::class);
        $application = $this->makeApplication($kelurahan, $org['village']);

        $submitted = $workflow->submit($kelurahan, $application);
        $this->assertSame(ApplicationStatus::SUBMITTED, $submitted->status);
        $this->assertSame(ReviewLevel::DISTRICT, $submitted->current_review_level);

        $validated = $workflow->validate($kecamatan, $submitted);
        $this->assertSame(ApplicationStatus::VALIDATED, $validated->status);
        $this->assertSame(ReviewLevel::REGENCY, $validated->current_review_level);

        $verified = $workflow->verify($kabupaten, $validated);
        $this->assertSame(ApplicationStatus::VERIFIED, $verified->status);
        $this->assertNull($verified->current_review_level);
    }

    public function test_kecamatan_revision_returns_application_to_kelurahan_and_requires_resubmission(): void
    {
        $org = $this->setupOrganization();

        $kelurahan = User::factory()->create(['organization_unit_id' => $org['villageUnit']->id]);
        $kelurahan->assignRole('admin_kelurahan');

        $kecamatan = User::factory()->create(['organization_unit_id' => $org['districtUnit']->id]);
        $kecamatan->assignRole('admin_kecamatan');

        $application = $this->makeApplication($kelurahan, $org['village']);
        $workflow = app(ApplicationWorkflowService::class);

        $submitted = $workflow->submit($kelurahan, $application);
        $this->assertSame(ReviewLevel::DISTRICT, $submitted->current_review_level);

        $revised = $workflow->requestRevision($kecamatan, $submitted, 'Dokumen kurang lengkap');
        $this->assertSame(ApplicationStatus::REVISION_REQUIRED, $revised->status);
        $this->assertSame(ReviewLevel::VILLAGE, $revised->current_review_level);

        $resubmitted = $workflow->submit($kelurahan, $revised);
        $this->assertSame(ApplicationStatus::SUBMITTED, $resubmitted->status);
        $this->assertSame(ReviewLevel::DISTRICT, $resubmitted->current_review_level);
    }

    public function test_kabupaten_revision_routes_back_to_kelurahan_then_kecamatan_then_kabupaten(): void
    {
        $org = $this->setupOrganization();

        $kelurahan = User::factory()->create(['organization_unit_id' => $org['villageUnit']->id]);
        $kelurahan->assignRole('admin_kelurahan');

        $kecamatan = User::factory()->create(['organization_unit_id' => $org['districtUnit']->id]);
        $kecamatan->assignRole('admin_kecamatan');

        $kabupaten = User::factory()->create(['organization_unit_id' => $org['regencyUnit']->id]);
        $kabupaten->assignRole('admin_kabupaten');

        $workflow = app(ApplicationWorkflowService::class);
        $application = $this->makeApplication($kelurahan, $org['village']);

        $submitted = $workflow->submit($kelurahan, $application);
        $validated = $workflow->validate($kecamatan, $submitted);
        $kabupatenRevision = $workflow->requestRevision($kabupaten, $validated, 'Perlu revisi data final');

        $this->assertSame(ApplicationStatus::REVISION_REQUIRED, $kabupatenRevision->status);
        $this->assertSame(ReviewLevel::VILLAGE, $kabupatenRevision->current_review_level);

        $resubmitted = $workflow->submit($kelurahan, $kabupatenRevision);
        $this->assertSame(ApplicationStatus::SUBMITTED, $resubmitted->status);
        $this->assertSame(ReviewLevel::DISTRICT, $resubmitted->current_review_level);

        $revalidated = $workflow->validate($kecamatan, $resubmitted);
        $this->assertSame(ApplicationStatus::VALIDATED, $revalidated->status);
        $this->assertSame(ReviewLevel::REGENCY, $revalidated->current_review_level);

        $verified = $workflow->verify($kabupaten, $revalidated);
        $this->assertSame(ApplicationStatus::VERIFIED, $verified->status);
    }

    public function test_rejection_from_submitted_is_allowed_for_kecamatan_or_kabupaten(): void
    {
        $org = $this->setupOrganization();

        $kelurahan = User::factory()->create(['organization_unit_id' => $org['villageUnit']->id]);
        $kelurahan->assignRole('admin_kelurahan');

        $kecamatan = User::factory()->create(['organization_unit_id' => $org['districtUnit']->id]);
        $kecamatan->assignRole('admin_kecamatan');

        $kabupaten = User::factory()->create(['organization_unit_id' => $org['regencyUnit']->id]);
        $kabupaten->assignRole('admin_kabupaten');

        $workflow = app(ApplicationWorkflowService::class);
        $application = $this->makeApplication($kelurahan, $org['village']);
        $submitted = $workflow->submit($kelurahan, $application);

        $rejectedByKecamatan = $workflow->reject($kecamatan, $submitted, 'Aplikasi ditolak karena data tidak lengkap');
        $this->assertSame(ApplicationStatus::REJECTED, $rejectedByKecamatan->status);

        $newApp = $this->makeApplication($kelurahan, $org['village']);
        $submittedAgain = $workflow->submit($kelurahan, $newApp);
        $rejectedByKabupaten = $workflow->reject($kabupaten, $submittedAgain, 'Ditolak karena belum memenuhi kriteria final');
        $this->assertSame(ApplicationStatus::REJECTED, $rejectedByKabupaten->status);
    }

    public function test_rejection_from_validated_is_allowed_for_kabupaten_only(): void
    {
        $org = $this->setupOrganization();
        $kelurahan = User::factory()->create(['organization_unit_id' => $org['villageUnit']->id]);
        $kelurahan->assignRole('admin_kelurahan');
        $kecamatan = User::factory()->create(['organization_unit_id' => $org['districtUnit']->id]);
        $kecamatan->assignRole('admin_kecamatan');
        $kabupaten = User::factory()->create(['organization_unit_id' => $org['regencyUnit']->id]);
        $kabupaten->assignRole('admin_kabupaten');

        $workflow = app(ApplicationWorkflowService::class);
        $application = $this->makeApplication($kelurahan, $org['village']);
        $submitted = $workflow->submit($kelurahan, $application);
        $validated = $workflow->validate($kecamatan, $submitted);

        $rejected = $workflow->reject($kabupaten, $validated, 'Penerimaan ditolak oleh kabupaten');
        $this->assertSame(ApplicationStatus::REJECTED, $rejected->status);
        $this->assertNull($rejected->current_review_level);
    }

    public function test_verified_and_rejected_are_terminal_states(): void
    {
        $org = $this->setupOrganization();
        $kelurahan = User::factory()->create(['organization_unit_id' => $org['villageUnit']->id]);
        $kelurahan->assignRole('admin_kelurahan');
        $kecamatan = User::factory()->create(['organization_unit_id' => $org['districtUnit']->id]);
        $kecamatan->assignRole('admin_kecamatan');
        $kabupaten = User::factory()->create(['organization_unit_id' => $org['regencyUnit']->id]);
        $kabupaten->assignRole('admin_kabupaten');

        $workflow = app(ApplicationWorkflowService::class);
        $app = $this->makeApplication($kelurahan, $org['village']);
        $submitted = $workflow->submit($kelurahan, $app);
        $validated = $workflow->validate($kecamatan, $submitted);
        $verified = $workflow->verify($kabupaten, $validated);

        $this->assertSame(ApplicationStatus::VERIFIED, $verified->status);
        $this->expectException(InvalidWorkflowTransitionException::class);
        $workflow->reject($kabupaten, $verified, 'Tidak valid setelah verified');
    }

    public function test_invalid_transition_fails_without_changing_application_state(): void
    {
        $org = $this->setupOrganization();
        $kelurahan = User::factory()->create(['organization_unit_id' => $org['villageUnit']->id]);
        $kelurahan->assignRole('admin_kelurahan');
        $kecamatan = User::factory()->create(['organization_unit_id' => $org['districtUnit']->id]);
        $kecamatan->assignRole('admin_kecamatan');

        $workflow = app(ApplicationWorkflowService::class);
        $application = $this->makeApplication($kelurahan, $org['village']);
        $application->forceFill(['status' => ApplicationStatus::SUBMITTED, 'current_review_level' => ReviewLevel::DISTRICT])->save();

        $this->expectException(InvalidWorkflowTransitionException::class);
        $workflow->validate($kelurahan, $application);
    }

    public function test_wrong_review_level_fails_without_changing_state(): void
    {
        $org = $this->setupOrganization();
        $kelurahan = User::factory()->create(['organization_unit_id' => $org['villageUnit']->id]);
        $kelurahan->assignRole('admin_kelurahan');
        $kecamatan = User::factory()->create(['organization_unit_id' => $org['districtUnit']->id]);
        $kecamatan->assignRole('admin_kecamatan');

        $workflow = app(ApplicationWorkflowService::class);
        $application = $this->makeApplication($kelurahan, $org['village']);
        $application->forceFill(['status' => ApplicationStatus::SUBMITTED, 'current_review_level' => ReviewLevel::VILLAGE])->save();

        $this->expectException(InvalidWorkflowTransitionException::class);
        $workflow->validate($kecamatan, $application);
    }

    public function test_wrong_organization_scope_fails(): void
    {
        $org = $this->setupOrganization();
        $kelurahan = User::factory()->create(['organization_unit_id' => $org['villageUnit']->id]);
        $kelurahan->assignRole('admin_kelurahan');

        $otherVillage = Village::create(['district_id' => $org['district']->id, 'name' => 'Kelurahan Lain', 'code' => '3204011002']);
        $otherUnit = OrganizationUnit::create([
            'name' => 'Kelurahan Lain',
            'type' => OrganizationUnitType::VILLAGE,
            'village_id' => $otherVillage->id,
            'is_active' => true,
        ]);
        $otherKelurahan = User::factory()->create(['organization_unit_id' => $otherUnit->id]);
        $otherKelurahan->assignRole('admin_kelurahan');

        $workflow = app(ApplicationWorkflowService::class);
        $application = $this->makeApplication($kelurahan, $org['village']);

        $this->expectException(InvalidWorkflowTransitionException::class);
        $workflow->submit($otherKelurahan, $application);
    }

    public function test_insufficient_permission_fails_for_super_admin_verification(): void
    {
        $org = $this->setupOrganization();
        $kelurahan = User::factory()->create(['organization_unit_id' => $org['villageUnit']->id]);
        $kelurahan->assignRole('admin_kelurahan');
        $kecamatan = User::factory()->create(['organization_unit_id' => $org['districtUnit']->id]);
        $kecamatan->assignRole('admin_kecamatan');

        $superAdmin = User::factory()->create(['organization_unit_id' => $org['regencyUnit']->id]);
        $superAdmin->assignRole('super_admin');

        $workflow = app(ApplicationWorkflowService::class);
        $application = $this->makeApplication($kelurahan, $org['village']);
        $submitted = $workflow->submit($kelurahan, $application);
        $validated = $workflow->validate($kecamatan, $submitted);

        $this->expectException(InvalidWorkflowTransitionException::class);
        $workflow->verify($superAdmin, $validated);
    }

    public function test_note_is_required_for_revision_and_reject(): void
    {
        $org = $this->setupOrganization();
        $kelurahan = User::factory()->create(['organization_unit_id' => $org['villageUnit']->id]);
        $kelurahan->assignRole('admin_kelurahan');
        $kecamatan = User::factory()->create(['organization_unit_id' => $org['districtUnit']->id]);
        $kecamatan->assignRole('admin_kecamatan');
        $kabupaten = User::factory()->create(['organization_unit_id' => $org['regencyUnit']->id]);
        $kabupaten->assignRole('admin_kabupaten');

        $workflow = app(ApplicationWorkflowService::class);
        $application = $this->makeApplication($kelurahan, $org['village']);
        $submitted = $workflow->submit($kelurahan, $application);
        $validated = $workflow->validate($kecamatan, $submitted);

        $this->expectException(ValidationException::class);
        $workflow->requestRevision($kabupaten, $validated, '   ');
    }

    public function test_atomic_rollback_on_failed_transition_keeps_database_consistent(): void
    {
        $org = $this->setupOrganization();
        $kelurahan = User::factory()->create(['organization_unit_id' => $org['villageUnit']->id]);
        $kelurahan->assignRole('admin_kelurahan');
        $kecamatan = User::factory()->create(['organization_unit_id' => $org['districtUnit']->id]);
        $kecamatan->assignRole('admin_kecamatan');

        $workflow = app(ApplicationWorkflowService::class);
        $application = $this->makeApplication($kelurahan, $org['village']);
        $submitted = $workflow->submit($kelurahan, $application);

        $this->assertSame(1, WorkflowHistory::query()->where('application_id', $submitted->id)->count());

        $this->expectException(InvalidWorkflowTransitionException::class);
        $workflow->validate($kelurahan, $submitted);
    }

    public function test_workflow_history_and_review_level_are_correct_for_each_transition(): void
    {
        $org = $this->setupOrganization();
        $kelurahan = User::factory()->create(['organization_unit_id' => $org['villageUnit']->id]);
        $kelurahan->assignRole('admin_kelurahan');
        $kecamatan = User::factory()->create(['organization_unit_id' => $org['districtUnit']->id]);
        $kecamatan->assignRole('admin_kecamatan');
        $kabupaten = User::factory()->create(['organization_unit_id' => $org['regencyUnit']->id]);
        $kabupaten->assignRole('admin_kabupaten');

        $workflow = app(ApplicationWorkflowService::class);
        $application = $this->makeApplication($kelurahan, $org['village']);

        $submitted = $workflow->submit($kelurahan, $application);
        $validated = $workflow->validate($kecamatan, $submitted);
        $verified = $workflow->verify($kabupaten, $validated);

        $history = WorkflowHistory::query()->where('application_id', $verified->id)->orderBy('acted_at')->get();
        $this->assertCount(3, $history);
        $this->assertSame(['submit', 'validate', 'verify'], $history->pluck('action')->all());
        $this->assertSame([ApplicationStatus::SUBMITTED, ApplicationStatus::VALIDATED, ApplicationStatus::VERIFIED], $history->pluck('to_status')->map(fn ($value) => $value instanceof ApplicationStatus ? $value : ApplicationStatus::from($value))->all());
        $this->assertNull($verified->current_review_level);
    }
}

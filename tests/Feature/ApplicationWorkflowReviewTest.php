<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\LivingWith;
use App\Enums\OrganizationUnitType;
use App\Enums\ReviewLevel;
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
use App\Services\ApplicationWorkflowService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationWorkflowReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    protected function buildOrganizationStructure(): array
    {
        $province = Province::create([
            'name' => 'Jawa Barat',
            'code' => '32',
        ]);

        $regency = Regency::create([
            'province_id' => $province->id,
            'name' => 'Kabupaten Bandung',
            'code' => '3204',
        ]);

        $district = District::create([
            'regency_id' => $regency->id,
            'name' => 'Kecamatan Bandung Kulon',
            'code' => '320401',
        ]);

        $village = Village::create([
            'district_id' => $district->id,
            'name' => 'Kelurahan Babakan Ciparay',
            'code' => '3204011001',
        ]);

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

    protected function createApplicationForVillage(User $creator, Village $village): Application
    {
        $household = Household::create([
            'kk_number' => '123456789012',
        ]);

        $address = Address::create([
            'village_id' => $village->id,
            'rt' => '01',
            'rw' => '02',
            'address_detail' => 'Jl. Contoh No. 1',
            'living_with' => LivingWith::PARENTS,
        ]);

        $child = Child::create([
            'household_id' => $household->id,
            'address_id' => $address->id,
            'name' => 'Ayu',
            'nik' => '3204010101010001',
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

    public function test_application_must_follow_district_then_regency_review_path(): void
    {
        $orgs = $this->buildOrganizationStructure();

        $kelurahan = User::factory()->create(['organization_unit_id' => $orgs['villageUnit']->id]);
        $kelurahan->assignRole('admin_kelurahan');

        $kecamatan = User::factory()->create(['organization_unit_id' => $orgs['districtUnit']->id]);
        $kecamatan->assignRole('admin_kecamatan');

        $kabupaten = User::factory()->create(['organization_unit_id' => $orgs['regencyUnit']->id]);
        $kabupaten->assignRole('admin_kabupaten');

        $application = $this->createApplicationForVillage($kelurahan, $orgs['village']);
        $workflow = app(ApplicationWorkflowService::class);

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

    public function test_regency_revision_returns_application_to_kelurahan_review_flow(): void
    {
        $orgs = $this->buildOrganizationStructure();

        $kelurahan = User::factory()->create(['organization_unit_id' => $orgs['villageUnit']->id]);
        $kelurahan->assignRole('admin_kelurahan');

        $kecamatan = User::factory()->create(['organization_unit_id' => $orgs['districtUnit']->id]);
        $kecamatan->assignRole('admin_kecamatan');

        $kabupaten = User::factory()->create(['organization_unit_id' => $orgs['regencyUnit']->id]);
        $kabupaten->assignRole('admin_kabupaten');

        $application = $this->createApplicationForVillage($kelurahan, $orgs['village']);
        $workflow = app(ApplicationWorkflowService::class);

        $submitted = $workflow->submit($kelurahan, $application);
        $validated = $workflow->validate($kecamatan, $submitted);

        $revised = $workflow->requestRevision($kabupaten, $validated, 'Dokumen perlu diperbaiki.');
        $this->assertSame(ApplicationStatus::REVISION_REQUIRED, $revised->status);
        $this->assertSame(ReviewLevel::VILLAGE, $revised->current_review_level);

        $resubmitted = $workflow->submit($kelurahan, $revised);
        $this->assertSame(ApplicationStatus::SUBMITTED, $resubmitted->status);
        $this->assertSame(ReviewLevel::DISTRICT, $resubmitted->current_review_level);
    }
}

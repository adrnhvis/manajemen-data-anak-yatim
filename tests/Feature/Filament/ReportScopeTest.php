<?php

namespace Tests\Feature\Filament;

use App\Enums\ApplicationStatus;
use App\Enums\Gender;
use App\Enums\LivingWith;
use App\Enums\OrganizationUnitType;
use App\Enums\ReviewLevel;
use App\Filament\Pages\ReportPage;
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
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_report_metrics_follow_village_district_and_regency_scope(): void
    {
        $province = Province::create(['name' => 'Riau', 'code' => '14']);
        $regency = Regency::create(['province_id' => $province->id, 'name' => 'Pelalawan', 'code' => '1405']);
        $otherRegency = Regency::create(['province_id' => $province->id, 'name' => 'Kampar', 'code' => '1401']);

        $district = District::create(['regency_id' => $regency->id, 'name' => 'Pangkalan Kerinci', 'code' => '140501']);
        $otherDistrict = District::create(['regency_id' => $regency->id, 'name' => 'Bandar Sei Kijang', 'code' => '140502']);
        $outsideDistrict = District::create(['regency_id' => $otherRegency->id, 'name' => 'Bangkinang Kota', 'code' => '140101']);

        $village = Village::create(['district_id' => $district->id, 'name' => 'Kerinci Kota', 'code' => '1405011001']);
        $neighborVillage = Village::create(['district_id' => $district->id, 'name' => 'Makmur', 'code' => '1405011002']);
        $otherDistrictVillage = Village::create(['district_id' => $otherDistrict->id, 'name' => 'Sekijang', 'code' => '1405021001']);
        $outsideVillage = Village::create(['district_id' => $outsideDistrict->id, 'name' => 'Kumantan', 'code' => '1401011001']);

        $villageUnit = OrganizationUnit::create([
            'name' => 'Kelurahan Kerinci Kota',
            'type' => OrganizationUnitType::VILLAGE,
            'regency_id' => $regency->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'is_active' => true,
        ]);
        $districtUnit = OrganizationUnit::create([
            'name' => 'Kecamatan Pangkalan Kerinci',
            'type' => OrganizationUnitType::DISTRICT,
            'regency_id' => $regency->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $regencyUnit = OrganizationUnit::create([
            'name' => 'Kabupaten Pelalawan',
            'type' => OrganizationUnitType::REGENCY,
            'regency_id' => $regency->id,
            'is_active' => true,
        ]);

        $villageAdmin = User::factory()->create(['organization_unit_id' => $villageUnit->id]);
        $villageAdmin->assignRole('admin_kelurahan');
        $districtAdmin = User::factory()->create(['organization_unit_id' => $districtUnit->id]);
        $districtAdmin->assignRole('admin_kecamatan');
        $regencyAdmin = User::factory()->create(['organization_unit_id' => $regencyUnit->id]);
        $regencyAdmin->assignRole('admin_kabupaten');

        foreach ([$village, $neighborVillage, $otherDistrictVillage, $outsideVillage] as $index => $recordVillage) {
            $this->createApplicationData($recordVillage, $villageAdmin, $index + 1);
        }

        $this->actingAs($villageAdmin);
        $villageReport = (new ReportPage)->getViewData();
        $this->assertSame(1, $villageReport['totalChildren']);
        $this->assertSame(1, $villageReport['totalApplications']);
        $this->assertSame('Kelurahan/Desa: Kelurahan Kerinci Kota', $villageReport['scopeDescription']);

        $this->actingAs($districtAdmin);
        $districtReport = (new ReportPage)->getViewData();
        $this->assertSame(2, $districtReport['totalChildren']);
        $this->assertSame(2, $districtReport['totalApplications']);
        $this->assertSame('Kecamatan: Kecamatan Pangkalan Kerinci (seluruh kelurahan/desa di kecamatan ini)', $districtReport['scopeDescription']);

        $this->actingAs($regencyAdmin);
        $regencyReport = (new ReportPage)->getViewData();
        $this->assertSame(3, $regencyReport['totalChildren']);
        $this->assertSame(3, $regencyReport['totalApplications']);
        $this->assertSame('Kabupaten: Kabupaten Pelalawan (seluruh kelurahan/desa di kabupaten ini)', $regencyReport['scopeDescription']);
    }

    private function createApplicationData(Village $village, User $creator, int $index): void
    {
        $household = Household::create(['kk_number' => sprintf('140500%06d', $index)]);
        $address = Address::create([
            'village_id' => $village->id,
            'rt' => '01',
            'rw' => '01',
            'address_detail' => 'Jl. Contoh '.$index,
            'living_with' => LivingWith::PARENTS,
        ]);
        $child = Child::create([
            'household_id' => $household->id,
            'address_id' => $address->id,
            'name' => 'Anak '.$index,
            'nik' => sprintf('140500%010d', $index),
            'birth_place' => 'Pelalawan',
            'birth_date' => '2018-01-15',
            'gender' => Gender::FEMALE,
        ]);

        Application::create([
            'child_id' => $child->id,
            'period_year' => (int) now()->year,
            'status' => ApplicationStatus::DRAFT,
            'current_review_level' => ReviewLevel::VILLAGE,
            'created_by' => $creator->id,
        ]);
    }
}

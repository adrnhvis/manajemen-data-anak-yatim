<?php

namespace Tests\Feature\Filament;

use App\Enums\ApplicationStatus;
use App\Enums\Gender;
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
use App\Services\DashboardService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_dashboard_numbers_follow_each_roles_scope(): void
    {
        $province = Province::create(['name' => 'Riau', 'code' => '14']);
        $regency = Regency::create(['province_id' => $province->id, 'name' => 'Pelalawan', 'code' => '1405']);
        $otherRegency = Regency::create(['province_id' => $province->id, 'name' => 'Kampar', 'code' => '1401']);

        $d1 = District::create(['regency_id' => $regency->id, 'name' => 'Pangkalan Kerinci', 'code' => '140501']);
        $d2 = District::create(['regency_id' => $regency->id, 'name' => 'Bandar Sei Kijang', 'code' => '140502']);
        $dOut = District::create(['regency_id' => $otherRegency->id, 'name' => 'Bangkinang Kota', 'code' => '140101']);

        $vA = Village::create(['district_id' => $d1->id, 'name' => 'Kerinci Kota', 'code' => '1405011001']);
        $vB = Village::create(['district_id' => $d1->id, 'name' => 'Makmur', 'code' => '1405011002']);
        $vC = Village::create(['district_id' => $d2->id, 'name' => 'Sekijang', 'code' => '1405021001']);
        $vOut = Village::create(['district_id' => $dOut->id, 'name' => 'Kumantan', 'code' => '1401011001']);

        $villageAdmin = $this->makeUser('admin_kelurahan', OrganizationUnitType::VILLAGE, $regency, $d1, $vA);
        $districtAdmin = $this->makeUser('admin_kecamatan', OrganizationUnitType::DISTRICT, $regency, $d1);
        $regencyAdmin = $this->makeUser('admin_kabupaten', OrganizationUnitType::REGENCY, $regency);
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        // Kerinci Kota: draft, perlu revisi, menunggu kecamatan.
        $this->makeApplication($vA, $villageAdmin, ApplicationStatus::DRAFT, ReviewLevel::VILLAGE);
        $this->makeApplication($vA, $villageAdmin, ApplicationStatus::REVISION_REQUIRED, ReviewLevel::VILLAGE);
        $this->makeApplication($vA, $villageAdmin, ApplicationStatus::SUBMITTED, ReviewLevel::DISTRICT);
        // Makmur (kecamatan yang sama): menunggu kecamatan, menunggu kabupaten.
        $this->makeApplication($vB, $villageAdmin, ApplicationStatus::SUBMITTED, ReviewLevel::DISTRICT);
        $this->makeApplication($vB, $villageAdmin, ApplicationStatus::VALIDATED, ReviewLevel::REGENCY);
        // Sekijang (kecamatan lain, kabupaten sama): menunggu kabupaten.
        $this->makeApplication($vC, $villageAdmin, ApplicationStatus::VALIDATED, ReviewLevel::REGENCY);
        // Kumantan (kabupaten lain): tidak boleh bocor ke siapa pun kecuali super admin.
        $this->makeApplication($vOut, $villageAdmin, ApplicationStatus::SUBMITTED, ReviewLevel::DISTRICT);

        $service = new DashboardService;

        // Kelurahan: hanya desanya sendiri.
        $this->assertSame('village', $service->level($villageAdmin));
        $this->assertSame(3, $service->statusCounts($villageAdmin)->sum());
        $this->assertSame(2, $service->taskQuery($villageAdmin)->count()); // draft + revisi

        // Kecamatan: seluruh desa di kecamatannya, tugas = menunggu pengecekan kecamatan.
        $this->assertSame('district', $service->level($districtAdmin));
        $this->assertSame(5, $service->statusCounts($districtAdmin)->sum());
        $this->assertSame(2, $service->taskQuery($districtAdmin)->count());
        $this->assertSame(['Kerinci Kota', 'Makmur'], array_keys($service->regionBreakdown($districtAdmin)));
        $this->assertSame(2, $service->statCards($districtAdmin)[0]['value']);

        // Kabupaten: seluruh kecamatan di kabupatennya, tanpa kabupaten lain.
        $this->assertSame('regency', $service->level($regencyAdmin));
        $this->assertSame(6, $service->statusCounts($regencyAdmin)->sum());
        $this->assertSame(2, $service->taskQuery($regencyAdmin)->count());
        $this->assertSame(['Bandar Sei Kijang', 'Pangkalan Kerinci'], array_keys($service->regionBreakdown($regencyAdmin)));
        $this->assertSame(2, $service->statCards($regencyAdmin)[0]['value']);

        // Super admin: semuanya, tanpa antrian tugas.
        $this->assertSame('admin', $service->level($superAdmin));
        $this->assertSame(7, $service->statusCounts($superAdmin)->sum());
        $this->assertSame(0, $service->taskQuery($superAdmin)->count());
        $this->assertSame(7, $service->statCards($superAdmin)[0]['value']);
    }

    private function makeUser(string $role, OrganizationUnitType $type, Regency $regency, ?District $district = null, ?Village $village = null): User
    {
        $unit = OrganizationUnit::create([
            'name' => 'Unit '.$role,
            'type' => $type,
            'regency_id' => $regency->id,
            'district_id' => $district?->id,
            'village_id' => $village?->id,
            'is_active' => true,
        ]);
        $user = User::factory()->create(['organization_unit_id' => $unit->id]);
        $user->assignRole($role);

        return $user;
    }

    private function makeApplication(Village $village, User $creator, ApplicationStatus $status, ReviewLevel $level): void
    {
        $i = ++$this->seq;
        $household = Household::create(['kk_number' => sprintf('140500%06d', $i)]);
        $address = Address::create([
            'village_id' => $village->id, 'rt' => '01', 'rw' => '01',
            'address_detail' => 'Jl. Contoh '.$i, 'living_with' => LivingWith::PARENTS,
        ]);
        $child = Child::create([
            'household_id' => $household->id, 'address_id' => $address->id, 'name' => 'Anak '.$i,
            'nik' => sprintf('140500%010d', $i), 'birth_place' => 'Pelalawan',
            'birth_date' => '2018-01-15', 'gender' => Gender::FEMALE,
        ]);
        Application::create([
            'child_id' => $child->id, 'period_year' => (int) now()->year,
            'status' => $status, 'current_review_level' => $level, 'created_by' => $creator->id,
        ]);
    }
}

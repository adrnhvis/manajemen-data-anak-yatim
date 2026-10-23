<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\LivingWith;
use App\Enums\OrganizationUnitType;
use App\Models\Address;
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
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ChildForceDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_permanently_delete_children(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $province = Province::create(['name' => 'Riau', 'code' => '14']);
        $regency = Regency::create(['province_id' => $province->id, 'name' => 'Pelalawan', 'code' => '1405']);
        $district = District::create(['regency_id' => $regency->id, 'name' => 'Pangkalan Kerinci', 'code' => '140501']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Kerinci Kota', 'code' => '1405011001']);

        $child = Child::create([
            'household_id' => Household::create(['kk_number' => '1405000000000001'])->id,
            'address_id' => Address::create([
                'village_id' => $village->id, 'rt' => '01', 'rw' => '01',
                'address_detail' => 'Jl. Contoh', 'living_with' => LivingWith::PARENTS,
            ])->id,
            'name' => 'Anak Uji', 'nik' => '1405000000000001', 'birth_place' => 'Pelalawan',
            'birth_date' => '2018-01-15', 'gender' => Gender::FEMALE,
        ]);

        $unit = fn (OrganizationUnitType $type, array $extra) => OrganizationUnit::create([
            'name' => 'Unit '.$type->value, 'type' => $type, 'regency_id' => $regency->id, 'is_active' => true,
        ] + $extra);

        $villageAdmin = User::factory()->create(['organization_unit_id' => $unit(OrganizationUnitType::VILLAGE, ['district_id' => $district->id, 'village_id' => $village->id])->id]);
        $villageAdmin->assignRole('admin_kelurahan');
        $districtAdmin = User::factory()->create(['organization_unit_id' => $unit(OrganizationUnitType::DISTRICT, ['district_id' => $district->id])->id]);
        $districtAdmin->assignRole('admin_kecamatan');
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        // Hapus sementara tetap boleh untuk kelurahan (kesalahan input bisa dipulihkan).
        $this->assertTrue(Gate::forUser($villageAdmin)->allows('delete', $child));
        $this->assertTrue(Gate::forUser($villageAdmin)->allows('restore', $child));

        // Hapus permanen: hanya super admin.
        foreach ([$villageAdmin, $districtAdmin] as $user) {
            $this->assertFalse(Gate::forUser($user)->allows('forceDelete', $child));
            $this->assertFalse(Gate::forUser($user)->allows('forceDeleteAny', Child::class));
        }
        $this->assertTrue(Gate::forUser($superAdmin)->allows('forceDelete', $child));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('forceDeleteAny', Child::class));
    }
}

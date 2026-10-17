<?php

namespace Tests\Feature\Filament;

use App\Enums\BankAccountHolderType;
use App\Enums\Gender;
use App\Enums\LivingWith;
use App\Enums\OrganizationUnitType;
use App\Enums\PersonStatus;
use App\Filament\Resources\Children\Pages\CreateChild;
use App\Filament\Resources\Children\Pages\ListChildren;
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
use Livewire\Livewire;
use Tests\TestCase;

class ChildResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_kelurahan_admin_can_only_see_children_in_their_village_scope(): void
    {
        $province = Province::create(['name' => 'Riau', 'code' => '14']);
        $regency = Regency::create(['province_id' => $province->id, 'name' => 'Pelalawan', 'code' => '1405']);
        $district = District::create(['regency_id' => $regency->id, 'name' => 'Pangkalan Kerinci', 'code' => '140501']);

        $village1 = Village::create(['district_id' => $district->id, 'name' => 'Pangkalan Kerinci Kota', 'code' => '1405011001']);
        $village2 = Village::create(['district_id' => $district->id, 'name' => 'Makmur', 'code' => '1405011002']);

        $orgUnit1 = OrganizationUnit::create([
            'name' => 'Kelurahan Kerinci Kota',
            'type' => OrganizationUnitType::VILLAGE,
            'regency_id' => $regency->id,
            'district_id' => $district->id,
            'village_id' => $village1->id,
            'is_active' => true,
        ]);

        $adminKelurahan1 = User::factory()->create(['organization_unit_id' => $orgUnit1->id]);
        $adminKelurahan1->assignRole('admin_kelurahan');

        $household1 = Household::create(['kk_number' => '1405010000000001']);
        $address1 = Address::create([
            'village_id' => $village1->id,
            'rt' => '01',
            'rw' => '01',
            'address_detail' => 'Jl. Utama',
            'living_with' => LivingWith::PARENTS,
        ]);
        $child1 = Child::create([
            'household_id' => $household1->id,
            'address_id' => $address1->id,
            'name' => 'Anak Kerinci Kota',
            'nik' => '1405010101010001',
            'birth_place' => 'Pelalawan',
            'birth_date' => '2015-05-01',
            'gender' => Gender::MALE,
        ]);

        $household2 = Household::create(['kk_number' => '1405010000000002']);
        $address2 = Address::create([
            'village_id' => $village2->id,
            'rt' => '02',
            'rw' => '02',
            'address_detail' => 'Jl. Kebun',
            'living_with' => LivingWith::PARENTS,
        ]);
        $child2 = Child::create([
            'household_id' => $household2->id,
            'address_id' => $address2->id,
            'name' => 'Anak Makmur',
            'nik' => '1405010101010002',
            'birth_place' => 'Pelalawan',
            'birth_date' => '2016-06-01',
            'gender' => Gender::FEMALE,
        ]);

        $this->actingAs($adminKelurahan1);

        Livewire::test(ListChildren::class)
            ->assertCanSeeTableRecords([$child1])
            ->assertCanNotSeeTableRecords([$child2]);
    }

    public function test_kelurahan_admin_can_create_child_inside_their_village(): void
    {
        $province = Province::create(['name' => 'Riau', 'code' => '14']);
        $regency = Regency::create(['province_id' => $province->id, 'name' => 'Pelalawan', 'code' => '1405']);
        $district = District::create(['regency_id' => $regency->id, 'name' => 'Pangkalan Kerinci', 'code' => '140501']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Pangkalan Kerinci Kota', 'code' => '1405011001']);

        $orgUnit = OrganizationUnit::create([
            'name' => 'Kelurahan Kerinci Kota',
            'type' => OrganizationUnitType::VILLAGE,
            'regency_id' => $regency->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'is_active' => true,
        ]);

        $adminKelurahan = User::factory()->create(['organization_unit_id' => $orgUnit->id]);
        $adminKelurahan->assignRole('admin_kelurahan');

        $household = Household::create(['kk_number' => '1405010000000001']);
        $address = Address::create([
            'village_id' => $village->id,
            'rt' => '01',
            'rw' => '01',
            'address_detail' => 'Jl. Utama',
            'living_with' => LivingWith::PARENTS,
        ]);

        $this->actingAs($adminKelurahan);

        Livewire::test(CreateChild::class)
            ->fillForm([
                'household_id' => $household->id,
                'address_id' => $address->id,
                'name' => 'Budi Pratama',
                'nik' => '1405010101010099',
                'birth_place' => 'Pelalawan',
                'birth_date' => '2017-01-01',
                'gender' => Gender::MALE->value,
                'father_name' => 'Ayah Budi',
                'father_status' => PersonStatus::DECEASED->value,
                'mother_name' => 'Ibu Budi',
                'mother_status' => PersonStatus::ALIVE->value,
                'account_number' => '1234567890',
                'account_holder_name' => 'Budi Pratama',
                'holder_type' => BankAccountHolderType::CHILD->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('children', [
            'nik' => '1405010101010099',
            'name' => 'Budi Pratama',
        ]);
    }
}

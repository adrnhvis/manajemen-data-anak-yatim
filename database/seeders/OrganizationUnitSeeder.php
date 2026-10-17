<?php

namespace Database\Seeders;

use App\Enums\OrganizationUnitType;
use App\Models\OrganizationUnit;
use App\Models\Regency;
use Illuminate\Database\Seeder;

class OrganizationUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Build Organization Unit Hierarchy based on existing regions
        $regencies = Regency::with(['districts.villages'])->get();

        foreach ($regencies as $regency) {
            $regencyOrg = OrganizationUnit::firstOrCreate(
                [
                    'type' => OrganizationUnitType::REGENCY,
                    'regency_id' => $regency->id,
                ],
                [
                    'name' => 'Kabupaten '.$regency->name,
                    'is_active' => true,
                ]
            );

            foreach ($regency->districts as $district) {
                $districtOrg = OrganizationUnit::firstOrCreate(
                    [
                        'parent_id' => $regencyOrg->id,
                        'type' => OrganizationUnitType::DISTRICT,
                        'regency_id' => $regency->id,
                        'district_id' => $district->id,
                    ],
                    [
                        'name' => 'Kecamatan '.$district->name,
                        'is_active' => true,
                    ]
                );

                foreach ($district->villages as $village) {
                    OrganizationUnit::firstOrCreate(
                        [
                            'parent_id' => $districtOrg->id,
                            'type' => OrganizationUnitType::VILLAGE,
                            'regency_id' => $regency->id,
                            'district_id' => $district->id,
                            'village_id' => $village->id,
                        ],
                        [
                            'name' => 'Kelurahan/Desa '.$village->name,
                            'is_active' => true,
                        ]
                    );
                }
            }
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Village;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Province
        $riau = Province::firstOrCreate(
            ['code' => '14'],
            ['name' => 'Riau']
        );

        // 2. Regencies
        $pelalawan = Regency::firstOrCreate(
            ['code' => '14.05'],
            ['province_id' => $riau->id, 'name' => 'Pelalawan']
        );

        $kampar = Regency::firstOrCreate(
            ['code' => '14.01'],
            ['province_id' => $riau->id, 'name' => 'Kampar']
        );

        // 3. Districts - Pelalawan
        $pangkalanKerinci = District::firstOrCreate(
            ['code' => '14.05.01'],
            ['regency_id' => $pelalawan->id, 'name' => 'Pangkalan Kerinci']
        );

        $bandarSeiKijang = District::firstOrCreate(
            ['code' => '14.05.02'],
            ['regency_id' => $pelalawan->id, 'name' => 'Bandar Sei Kijang']
        );

        // 3. Districts - Kampar
        $bangkinang = District::firstOrCreate(
            ['code' => '14.01.01'],
            ['regency_id' => $kampar->id, 'name' => 'Bangkinang Kota']
        );

        // 4. Villages - Pangkalan Kerinci
        Village::firstOrCreate(['code' => '14.05.01.1001'], ['district_id' => $pangkalanKerinci->id, 'name' => 'Pangkalan Kerinci Kota']);
        Village::firstOrCreate(['code' => '14.05.01.1002'], ['district_id' => $pangkalanKerinci->id, 'name' => 'Pangkalan Kerinci Timur']);
        Village::firstOrCreate(['code' => '14.05.01.2003'], ['district_id' => $pangkalanKerinci->id, 'name' => 'Makmur']);
        Village::firstOrCreate(['code' => '14.05.01.2004'], ['district_id' => $pangkalanKerinci->id, 'name' => 'Batu Hampar']);
        Village::firstOrCreate(['code' => '14.05.01.2005'], ['district_id' => $pangkalanKerinci->id, 'name' => 'Batu Nangoi']);

        // 4. Villages - Bandar Sei Kijang
        Village::firstOrCreate(['code' => '14.05.02.1001'], ['district_id' => $bandarSeiKijang->id, 'name' => 'Sekijang']);
        Village::firstOrCreate(['code' => '14.05.02.2002'], ['district_id' => $bandarSeiKijang->id, 'name' => 'Lubuk Ogung']);
        Village::firstOrCreate(['code' => '14.05.02.2003'], ['district_id' => $bandarSeiKijang->id, 'name' => 'Teluk Meranti']);
        Village::firstOrCreate(['code' => '14.05.02.2004'], ['district_id' => $bandarSeiKijang->id, 'name' => 'Pangkalan Jambi']);

        // 4. Villages - Bangkinang
        Village::firstOrCreate(['code' => '14.01.01.1001'], ['district_id' => $bangkinang->id, 'name' => 'Bangkinang']);
        Village::firstOrCreate(['code' => '14.01.01.1002'], ['district_id' => $bangkinang->id, 'name' => 'Kumantan']);
        Village::firstOrCreate(['code' => '14.01.01.1003'], ['district_id' => $bangkinang->id, 'name' => 'Pangkalan Baru']);
        Village::firstOrCreate(['code' => '14.01.01.1004'], ['district_id' => $bangkinang->id, 'name' => 'Suka Damai']);
    }
}

<?php

namespace Database\Seeders;

use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        // 1. Super Admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@example.test'],
            [
                'name' => 'Super Admin',
                'password' => $password,
                'organization_unit_id' => null,
                'email_verified_at' => now(),
            ]
        );
        $superAdmin->syncRoles(['super_admin']);

        // Fetch organization units to assign correctly
        $orgPelalawan = OrganizationUnit::where('name', 'Kabupaten Pelalawan')->first();
        $orgKerinci = OrganizationUnit::where('name', 'Kecamatan Pangkalan Kerinci')->first();
        $orgKerinciKota = OrganizationUnit::where('name', 'Kelurahan/Desa Pangkalan Kerinci Kota')->first();
        $orgMakmur = OrganizationUnit::where('name', 'Kelurahan/Desa Makmur')->first();

        $orgSeiKijang = OrganizationUnit::where('name', 'Kecamatan Bandar Sei Kijang')->first();
        $orgSekijang = OrganizationUnit::where('name', 'Kelurahan/Desa Sekijang')->first();

        $orgKampar = OrganizationUnit::where('name', 'Kabupaten Kampar')->first();
        $orgBangkinang = OrganizationUnit::where('name', 'Kecamatan Bangkinang Kota')->first();
        $orgKumantan = OrganizationUnit::where('name', 'Kelurahan/Desa Kumantan')->first();

        // Admin Kabupaten Pelalawan
        if ($orgPelalawan) {
            $user = User::firstOrCreate(
                ['email' => 'kabupaten.pelalawan@example.test'],
                ['name' => 'Admin Kab. Pelalawan', 'password' => $password, 'organization_unit_id' => $orgPelalawan->id, 'email_verified_at' => now()]
            );
            $user->syncRoles(['admin_kabupaten']);
        }

        // Admin Kecamatan Pangkalan Kerinci
        if ($orgKerinci) {
            $user = User::firstOrCreate(
                ['email' => 'kecamatan.pangkalan@example.test'],
                ['name' => 'Admin Kec. Pangkalan Kerinci', 'password' => $password, 'organization_unit_id' => $orgKerinci->id, 'email_verified_at' => now()]
            );
            $user->syncRoles(['admin_kecamatan']);
        }

        // Admin Kelurahan Pangkalan Kerinci Kota
        if ($orgKerinciKota) {
            $user = User::firstOrCreate(
                ['email' => 'kelurahan.kerincikota@example.test'],
                ['name' => 'Admin Kel. Pangkalan Kerinci Kota', 'password' => $password, 'organization_unit_id' => $orgKerinciKota->id, 'email_verified_at' => now()]
            );
            $user->syncRoles(['admin_kelurahan']);
        }

        // Admin Kelurahan Makmur
        if ($orgMakmur) {
            $user = User::firstOrCreate(
                ['email' => 'kelurahan.makmur@example.test'],
                ['name' => 'Admin Kel. Makmur', 'password' => $password, 'organization_unit_id' => $orgMakmur->id, 'email_verified_at' => now()]
            );
            $user->syncRoles(['admin_kelurahan']);
        }

        // Admin Kecamatan Bandar Sei Kijang
        if ($orgSeiKijang) {
            $user = User::firstOrCreate(
                ['email' => 'kecamatan.seikijang@example.test'],
                ['name' => 'Admin Kec. Bandar Sei Kijang', 'password' => $password, 'organization_unit_id' => $orgSeiKijang->id, 'email_verified_at' => now()]
            );
            $user->syncRoles(['admin_kecamatan']);
        }

        // Admin Kelurahan Sekijang
        if ($orgSekijang) {
            $user = User::firstOrCreate(
                ['email' => 'kelurahan.sekijang@example.test'],
                ['name' => 'Admin Kel. Sekijang', 'password' => $password, 'organization_unit_id' => $orgSekijang->id, 'email_verified_at' => now()]
            );
            $user->syncRoles(['admin_kelurahan']);
        }

        // Admin Kabupaten Kampar
        if ($orgKampar) {
            $user = User::firstOrCreate(
                ['email' => 'kabupaten.kampar@example.test'],
                ['name' => 'Admin Kab. Kampar', 'password' => $password, 'organization_unit_id' => $orgKampar->id, 'email_verified_at' => now()]
            );
            $user->syncRoles(['admin_kabupaten']);
        }

        // Admin Kecamatan Bangkinang
        if ($orgBangkinang) {
            $user = User::firstOrCreate(
                ['email' => 'kecamatan.bangkinang@example.test'],
                ['name' => 'Admin Kec. Bangkinang', 'password' => $password, 'organization_unit_id' => $orgBangkinang->id, 'email_verified_at' => now()]
            );
            $user->syncRoles(['admin_kecamatan']);
        }

        // Admin Kelurahan Kumantan
        if ($orgKumantan) {
            $user = User::firstOrCreate(
                ['email' => 'kelurahan.kumantan@example.test'],
                ['name' => 'Admin Kel. Kumantan', 'password' => $password, 'organization_unit_id' => $orgKumantan->id, 'email_verified_at' => now()]
            );
            $user->syncRoles(['admin_kelurahan']);
        }
    }
}

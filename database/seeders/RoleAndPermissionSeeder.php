<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // create permissions
        $permissions = [
            // Children
            'view children',
            'create children',
            'update children',
            'delete children',
            'submit children',

            // Applications
            'view applications',
            'create applications',
            'update applications',
            'submit applications',
            'review applications',
            'validate applications',
            'verify applications',
            'reject applications',
            'request revision',

            // Documents
            'view documents',
            'upload documents',
            'update documents',
            'delete documents',

            // Reports
            'view reports',
            'export reports',

            // User & System
            'manage users',
            'manage roles',
            'manage permissions',
            'manage organization',
            'manage master data',
            'view audit logs',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // create roles and assign created permissions

        // 1. super_admin
        // Has all system administrative rights, but we explicitly do NOT give 'verify applications' 
        // to test separation of system admin vs business authority, as requested.
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->givePermissionTo([
            'manage users',
            'manage roles',
            'manage permissions',
            'manage organization',
            'manage master data',
            'view audit logs',
            'view children',
            'view applications',
            'view documents',
            'view reports',
        ]);

        // 2. admin_kabupaten
        // Highest business authority: can verify/reject, view reports
        $adminKabupaten = Role::firstOrCreate(['name' => 'admin_kabupaten']);
        $adminKabupaten->givePermissionTo([
            'view children',
            'view applications',
            'verify applications',
            'reject applications',
            'view documents',
            'view reports',
            'export reports',
        ]);

        // 3. admin_kecamatan
        // Middle authority: can review, validate, request revision
        $adminKecamatan = Role::firstOrCreate(['name' => 'admin_kecamatan']);
        $adminKecamatan->givePermissionTo([
            'view children',
            'view applications',
            'review applications',
            'validate applications',
            'request revision',
            'view documents',
            'view reports',
        ]);

        // 4. admin_kelurahan
        // Entry level: can create child, application, upload doc, submit
        $adminKelurahan = Role::firstOrCreate(['name' => 'admin_kelurahan']);
        $adminKelurahan->givePermissionTo([
            'view children',
            'create children',
            'update children',
            'delete children',
            'submit children',
            
            'view applications',
            'create applications',
            'update applications',
            'submit applications',
            
            'view documents',
            'upload documents',
            'update documents',
            'delete documents',
        ]);
    }
}

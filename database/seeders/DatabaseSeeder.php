<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            RegionSeeder::class,
            OrganizationUnitSeeder::class,
            UserSeeder::class,
            DocumentCategorySeeder::class,
            DummyDataSeeder::class,
            DummyDataForRolesSeeder::class,
        ]);
    }
}

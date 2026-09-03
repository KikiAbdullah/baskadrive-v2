<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Urutan eksekusi:
     * 1. PermissionSeeder — buat permission yang diperlukan
     * 2. RoleSeeder — buat role dan assign permissions
     * 3. UserSeeder — buat user dan assign role
     * 4. UserLogSeeder — generate log aktivitas sampel
     * 5. SanctumTokenSeeder — generate API token
     * 6. RentalErpMasterSeeder — brand, model, vehicle, location, maintenance type
     * 7. RentalErpEmployeeSeeder — karyawan default (admin/password)
     * 8. RentalErpCoaSeeder — chart of accounts
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            UserLogSeeder::class,
            SanctumTokenSeeder::class,
            RentalErpMasterSeeder::class,
            RentalErpEmployeeSeeder::class,
            RentalErpCoaSeeder::class,
        ]);
    }
}
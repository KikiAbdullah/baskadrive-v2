<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Urutan:
     * 1. Admin (users, roles, permissions, logs, tokens)
     * 2. Rental ERP Master (brand, model, vehicle, customer, driver, location, workshop, maintenance_type, promo, coa, employee)
     * 3. Rental ERP Transaksi (rental, detail, extension, return, damage, photo, claim, maintenance, fine)
     * 4. Rental ERP Finance (invoice, payment, refund, journal, journal_detail)
     */
    public function run(): void
    {
        $this->call([
            // Admin
            PermissionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            UserLogSeeder::class,
            SanctumTokenSeeder::class,

            // Rental ERP Master
            RentalErpMasterSeeder::class,
            RentalErpEmployeeSeeder::class,
            RentalErpCoaSeeder::class,

            // Rental ERP Transaksi
            RentalErpRentalSeeder::class,
            RentalErpOperationalSeeder::class,

            // Rental ERP Keuangan
            RentalErpFinanceSeeder::class,

            // Rental ERP — DATA LENGKAP 12 BULAN (realistis, jumlah besar)
            RentalErpCompleteSeeder::class,
        ]);
    }
}
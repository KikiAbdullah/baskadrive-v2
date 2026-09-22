<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Urutan (remediasi SED-01):
     * 1. Admin (users, roles, permissions, logs, tokens, settings)
     * 2. RentalErpCompleteSeeder — SATU-SATUNYA sumber data ERP (master + transaksi
     *    + keuangan 12 bulan). Pipeline lama (RentalErpMaster/Employee/Coa/Rental/
     *    Operational/Finance) dipertahankan sebagai arsip opsional tetapi tidak lagi
     *    dipanggil karena seluruh hasilnya di-truncate oleh CompleteSeeder.
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
            SettingsSeeder::class,

            // Rental ERP — dataset lengkap 12 bulan (sumber tunggal)
            RentalErpCompleteSeeder::class,
        ]);
    }
}

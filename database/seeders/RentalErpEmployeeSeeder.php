<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RentalErpEmployeeSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('m_employee')->insertOrIgnore([
            'first_name' => 'Admin',
            'last_name' => 'Utama',
            'email' => 'admin@rental.com',
            'phone' => '081234567890',
            'position' => 'System Administrator',
            'hire_date' => '2024-01-01',
            'username' => 'admin',
            'password_hash' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->command->info('RentalErpEmployeeSeeder selesai. Login: admin / password');
    }
}
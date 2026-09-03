<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RentalErpEmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $employees = [
            ['first' => 'Admin', 'last' => 'Utama', 'email' => 'admin@rental.com', 'phone' => '081234567890', 'position' => 'System Administrator', 'username' => 'admin', 'role' => 'admin'],
            ['first' => 'Siti', 'last' => 'Rahmawati', 'email' => 'manager@rental.com', 'phone' => '081234567891', 'position' => 'Manager Operasional', 'username' => 'manager', 'role' => 'manager'],
            ['first' => 'Rudi', 'last' => 'Hartono', 'email' => 'cashier@rental.com', 'phone' => '081234567892', 'position' => 'Kasir', 'username' => 'kasir', 'role' => 'cashier'],
            ['first' => 'Agus', 'last' => 'Supriyadi', 'email' => 'mechanic@rental.com', 'phone' => '081234567893', 'position' => 'Mekanik', 'username' => 'mekanik', 'role' => 'mechanic'],
            ['first' => 'Dewi', 'last' => 'Lestari', 'email' => 'accountant@rental.com', 'phone' => '081234567894', 'position' => 'Akuntan', 'username' => 'akuntan', 'role' => 'accountant'],
            ['first' => 'Budi', 'last' => 'Santoso', 'email' => 'director@rental.com', 'phone' => '081234567895', 'position' => 'Direktur', 'username' => 'direktur', 'role' => 'director'],
        ];

        foreach ($employees as $e) {
            Employee::firstOrCreate(
                ['username' => $e['username']],
                [
                    'first_name' => $e['first'],
                    'last_name' => $e['last'],
                    'email' => $e['email'],
                    'phone' => $e['phone'],
                    'position' => $e['position'],
                    'hire_date' => '2024-01-01',
                    'password_hash' => Hash::make('password'),
                    'role' => $e['role'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('RentalErpEmployeeSeeder selesai: '.Employee::count().' karyawan. Login: <username> / password');
    }
}
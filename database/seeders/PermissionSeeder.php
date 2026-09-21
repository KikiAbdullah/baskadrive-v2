<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Daftar permission lengkap yang dikelompokkan per modul.
     *
     * Konvensi penamaan: <modul>_<aksi> (contoh: users_view, roles_add, dsb).
     */
    protected array $permissionGroups = [

        // ===========================================
        // DASHBOARD
        // ===========================================
        'dashboard' => [
            'dashboard_view',
        ],

        // ===========================================
        // MODUL OPERASIONAL (audit Setup S-01: gate per modul;
        // audit Sewa §6: permission tulis & finansial terpisah)
        // ===========================================
        'rental' => [
            'rental_view',
            'rental_add',
            'rental_edit',
            'rental_confirm',
            'rental_return',
            'rental_cancel',
            'rental_export',
            'fine_add',
            'fine_pay',
            'fine_waive',
        ],

        'fleet' => [
            'fleet_view',
            // FLE-01 (audit Fleet): permission aksi terpisah — pemegang fleet_view
            // tidak otomatis boleh mengeksekusi mutasi berdampak (jurnal, tagihan,
            // hapus bukti foto, status kendaraan).
            'fleet_maintain',
            'fleet_damage_add',
            'fleet_damage_manage',
            'fleet_claim_manage',
            'fleet_bill_renter',
        ],

        'finance' => [
            'finance_view',
            'finance_invoice_add',
            'finance_payment_add',
            'finance_refund_add',
        ],

        'accounting' => [
            'accounting_view',
            'accounting_export',
        ],

        // ===========================================
        // USER SETUP - USERS
        // ===========================================
        'users' => [
            'users_view',
            'users_add',
            'users_edit',
            'users_delete',
            'users_export',
        ],

        // ===========================================
        // USER SETUP - ROLES
        // ===========================================
        'roles' => [
            'roles_view',
            'roles_add',
            'roles_edit',
            'roles_delete',
        ],

        // ===========================================
        // USER SETUP - PERMISSIONS
        // ===========================================
        'permissions' => [
            'permissions_view',
            'permissions_add',
            'permissions_edit',
            'permissions_delete',
        ],

        // ===========================================
        // DEBUG / LOG VIEWER
        // ===========================================
        'debug' => [
            'debug_view',
        ],

        // ===========================================
        // USER LOGS
        // ===========================================
        'logs' => [
            'logs_view',
            'logs_export',
        ],

        // ===========================================
        // SETTINGS
        // ===========================================
        'settings' => [
            'settings_view',
            'settings_edit',
        ],

        // ===========================================
        // REPORT
        // ===========================================
        'report' => [
            'report_view',
            'report_export',
        ],

        // ===========================================
        // MASTER DATA (audit M-06)
        // ===========================================
        'master' => [
            'master_view',
            'master_add',
            'master_edit',
            'master_delete',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $created = 0;
        $skipped = 0;

        foreach ($this->permissionGroups as $module => $permissions) {
            foreach ($permissions as $permission) {
                $existing = Permission::where('name', $permission)->where('guard_name', 'web')->exists();

                if ($existing) {
                    $skipped++;

                    continue;
                }

                Permission::create([
                    'name' => $permission,
                    'guard_name' => 'web',
                ]);

                $created++;
            }
        }

        $this->command->info("PermissionSeeder selesai: {$created} dibuat, {$skipped} sudah ada.");

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}

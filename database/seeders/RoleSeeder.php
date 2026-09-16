<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Daftar role beserta permission yang dimilikinya.
     *
     * Role dibagi menjadi beberapa level akses:
     * - SUPERADMIN : akses penuh semua modul
     * - ADMIN      : kelola user, role, permission, logs, settings & report
     * - MANAGER    : kelola user (terbatas), lihat logs & report
     * - SUPERVISOR : lihat user, logs & report
     * - STAFF      : akses operasional dasar
     * - VIEWER     : akses view-only dashboard & report
     */
    protected array $roles = [

        'SUPERADMIN' => '*', // semua permission

        'ADMIN' => [
            'dashboard_view',
            'users_view', 'users_add', 'users_edit', 'users_delete', 'users_export',
            'roles_view', 'roles_add', 'roles_edit', 'roles_delete',
            'permissions_view', 'permissions_add', 'permissions_edit', 'permissions_delete',
            'logs_view', 'logs_export',
            'settings_view', 'settings_edit',
            'report_view', 'report_export', 'report_download',
            'rental_view', 'rental_add', 'rental_edit', 'rental_confirm', 'rental_return',
            'rental_cancel', 'rental_export', 'fine_add', 'fine_pay', 'fine_waive',
            'finance_view', 'finance_invoice_add', 'finance_payment_add', 'finance_refund_add',
            'fleet_view', 'accounting_view', 'accounting_export',
            'master_view', 'master_add', 'master_edit', 'master_delete',
        ],

        'MANAGER' => [
            'dashboard_view',
            'users_view', 'users_add', 'users_edit',
            'roles_view',
            'logs_view',
            'report_view', 'report_export', 'report_download',
            'rental_view', 'rental_add', 'rental_edit', 'rental_confirm', 'rental_return',
            'rental_cancel', 'rental_export', 'fine_add', 'fine_pay', 'fine_waive',
            'finance_view', 'finance_invoice_add', 'finance_payment_add', 'finance_refund_add',
            'fleet_view', 'accounting_view', 'accounting_export',
            'master_view', 'master_add', 'master_edit', 'master_delete',
        ],

        'SUPERVISOR' => [
            'dashboard_view',
            'users_view',
            'logs_view',
            'report_view', 'report_export',
            'rental_view', 'rental_add', 'rental_edit', 'rental_confirm', 'rental_return',
            'rental_cancel', 'rental_export', 'fine_add', 'fine_pay',
            'finance_view', 'finance_invoice_add', 'finance_payment_add', 'finance_refund_add',
            'fleet_view', 'accounting_view',
            'master_view', 'master_add', 'master_edit',
        ],

        'STAFF' => [
            'dashboard_view',
            'users_view',
            'report_view',
            'master_view',
            'rental_view', 'fleet_view', 'finance_view',
            'rental_add', 'rental_edit', 'rental_confirm', 'rental_return',
            'fine_add', 'fine_pay',
            'finance_invoice_add', 'finance_payment_add',
        ],

        'VIEWER' => [
            'dashboard_view',
            'report_view',
            'master_view',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $allPermissions = Permission::pluck('name')->all();

        $processed = 0;

        foreach ($this->roles as $roleName => $permissionList) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            if (! $role) {
                $role = Role::create([
                    'name' => $roleName,
                    'guard_name' => 'web',
                ]);
            }

            if ($permissionList === '*') {
                $role->syncPermissions($allPermissions);
            } else {
                $role->syncPermissions($permissionList);
            }

            $processed++;
        }

        $this->command->info("RoleSeeder selesai: {$processed} role diproses.");

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}

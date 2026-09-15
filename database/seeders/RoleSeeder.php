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
            'permissions_view',
            'logs_view', 'logs_export',
            'settings_view', 'settings_edit',
            'report_view', 'report_export', 'report_download',
            'rental_cancel', 'fine_waive', 'accounting_export',
            'master_view', 'master_add', 'master_edit', 'master_delete',
            'rental_view', 'fleet_view', 'finance_view', 'accounting_view',
        ],

        'MANAGER' => [
            'dashboard_view',
            'users_view', 'users_add', 'users_edit',
            'roles_view',
            'logs_view',
            'report_view', 'report_export', 'report_download',
            'rental_cancel', 'fine_waive', 'accounting_export',
            'master_view', 'master_add', 'master_edit', 'master_delete',
            'rental_view', 'fleet_view', 'finance_view', 'accounting_view',
        ],

        'SUPERVISOR' => [
            'dashboard_view',
            'users_view',
            'logs_view',
            'report_view', 'report_export',
            'rental_cancel',
            'master_view', 'master_add', 'master_edit',
            'rental_view', 'fleet_view', 'finance_view', 'accounting_view',
        ],

        'STAFF' => [
            'dashboard_view',
            'users_view',
            'report_view',
            'master_view',
            'rental_view', 'fleet_view', 'finance_view',
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

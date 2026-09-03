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
            'report_download',
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

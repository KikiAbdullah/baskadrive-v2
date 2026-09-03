<?php

namespace App\Http\Controllers\Traits;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

trait ListTrait
{
    public function listRole()
    {
        return Role::with(['users'])->get();
    }

    public function listRolePluckId()
    {
        return Role::pluck('name', 'id');
    }

    public function listPermission()
    {
        return Permission::pluck('name', 'id');
    }

    public function listPermissionGroup()
    {
        $permissions = Permission::all();

        // Mengelompokkan berdasarkan prefix sebelum underscore
        $data = $permissions->groupBy(function ($permission) {
            // Ambil prefix sebelum underscore pertama
            return explode('_', $permission->name)[0];
        })->map(function ($group) {
            // Setelah dikelompokkan, kembalikan array dengan key sebagai ID dan value sebagai nama akhir
            return $group->mapWithKeys(function ($permission) {
                // Key adalah ID, value adalah bagian setelah underscore
                return [$permission->id => last(explode('_', $permission->name))];
            });
        });

        return $data;
    }
}

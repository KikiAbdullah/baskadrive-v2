<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    protected $roleData;

    public function __construct(Role $model)
    {
        $this->title = 'Role';
        $this->subtitle = 'Role';
        $this->model_request = Request::class;
        $this->folder = 'user-setup';
        $this->relation = ['permissions', 'users'];
        $this->model = $model;
        $this->withTrashed = false;
    }

    public function formData()
    {
        return [
            'list_roles' => $this->listRole(),
            'list_permission' => $this->listPermission(),
            'list_permission_group' => $this->listPermissionGroup(),
        ];
    }

    public function create()
    {
        $view = [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'folder' => $this->folder ?? '',
            'data' => method_exists($this, 'formData') ? $this->formData() : null,
            'form' => $this->generateViewName('form'),
            'url' => [
                'store' => $this->generateUrl('store'),
            ],
        ];

        $response = [
            'status' => true,
            'view' => view($this->generateViewName(__FUNCTION__))->with($view)->render(),
        ];

        return response()->json($response);
    }

    public function customRequest($request)
    {
        $data = $request->all();

        unset($data['_token'], $data['_method']);

        if ($request->method() === 'POST' && isset($data['name'])) {
            $data['name'] = strtoupper($data['name']);

            if (Role::where('name', $data['name'])->count() > 0) {
                throw new Exception('Role '.$data['name'].' already exists');
            }
        } elseif ($request->method() !== 'POST') {
            unset($data['name']);
        }

        $this->roleData = $data;

        unset($data['permission']);

        return $data;
    }

    public function customStore($data, $model)
    {
        if (! empty($this->roleData['permission']) && is_array($this->roleData['permission'])) {
            $permissions = Permission::whereIn('id', array_keys($this->roleData['permission']))->pluck('name');

            foreach ($permissions as $permission) {
                $model->givePermissionTo($permission);
            }
        }
    }

    public function customUpdate($data, $model)
    {
        // S-06: lindungi role sistem — SUPERADMIN tidak boleh diubah permission-nya
        if (in_array($model->name, ['SUPERADMIN'], true)) {
            throw new Exception('Role sistem "'.$model->name.'" tidak boleh diubah.');
        }

        foreach ($model->permissions as $permission) {
            $model->revokePermissionTo($permission->name);
        }

        if (! empty($this->roleData['permission']) && is_array($this->roleData['permission'])) {
            $permissions = Permission::whereIn('id', array_keys($this->roleData['permission']))->pluck('name');

            foreach ($permissions as $permission) {
                $model->givePermissionTo($permission);
            }
        }
    }

    public function customDestroy($model)
    {
        // S-06: lindungi role sistem & role yang masih dipakai user
        if (in_array($model->name, ['SUPERADMIN', 'ADMIN'], true)) {
            throw new Exception('Role sistem "'.$model->name.'" tidak boleh dihapus.');
        }

        if ($model->users()->exists()) {
            throw new Exception('Role "'.$model->name.'" masih dipakai oleh user — cabut dulu dari user terkait sebelum menghapus.');
        }
    }
}

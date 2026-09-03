<?php

namespace App\Http\Controllers;

use App\Helpers\LogHelper;
use DB;
use Exception;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
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

    //ADD
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

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $data = $this->getRequest();

            if (Role::where('name', strtoupper($data['name']))->count() <= 0) {
                $model = Role::create(['name' => strtoupper($data['name'])]);

                if (! empty($data['permission']) && is_array($data['permission'])) {
                    $permissions = Permission::whereIn('id', array_keys($data['permission']))->pluck('name');
                    foreach ($permissions as $permission) {
                        $model->givePermissionTo($permission);
                    }
                }
            } else {
                return $this->redirectBackWithError('Role '.strtoupper($data['name']).' already exists');
            }

            $log_helper = new LogHelper;

            $log_helper->storeLog('add', $model->no ?? $model->id, $this->subtitle);

            DB::commit();
            if ($request->ajax()) {
                $response = [
                    'status' => true,
                    'msg' => 'Data Saved.',
                ];

                return response()->json($response);
            } else {
                return $this->redirectSuccess(__FUNCTION__, false);
            }
        } catch (Exception $e) {
            DB::rollback();
            if ($request->ajax()) {
                $response = [
                    'status' => false,
                    'msg' => $e->getMessage(),
                ];

                return response()->json($response);
            } else {
                return $this->redirectBackWithError($e->getMessage());
            }
        }
    }
    //ADD

    //UPDATE
    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $data = $this->getRequest();

            if ($this->withTrashed) {
                $model = $this->model->with($this->relation)->withTrashed()->findOrFail($id);
            } else {
                $model = $this->model->with($this->relation)->findOrFail($id);
            }

            foreach ($model->permissions as $key => $permission) {
                $model->revokePermissionTo($permission->name);
            }

            if (! empty($data['permission']) && is_array($data['permission'])) {
                $permissions = Permission::whereIn('id', array_keys($data['permission']))->pluck('name');
                foreach ($permissions as $permission) {
                    $model->givePermissionTo($permission);
                }
            }

            $log_helper = new LogHelper;

            $log_helper->storeLog('edit', $model->no ?? $model->id, $this->subtitle);

            DB::commit();
            if ($request->ajax()) {
                $response = [
                    'status' => true,
                    'msg' => 'Data Saved.',
                ];

                return response()->json($response);
            } else {
                return $this->redirectSuccess(__FUNCTION__, false);
            }
        } catch (Exception $e) {
            DB::rollback();
            if ($request->ajax()) {
                $response = [
                    'status' => false,
                    'msg' => $e->getMessage(),
                ];

                return response()->json($response);
            } else {
                return $this->redirectBackWithError($e->getMessage());
            }
        }
    }
    //UPDATE
}

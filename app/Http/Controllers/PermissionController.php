<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Yajra\DataTables\DataTables;

class PermissionController extends Controller
{
    public function __construct(Permission $model)
    {
        $this->title = 'Permission';
        $this->subtitle = 'Permission';
        $this->model_request = Request::class;
        $this->folder = 'user-setup';
        $this->relation = [];
        $this->model = $model;
        $this->withTrashed = false;
    }

    public function ajaxData()
    {
        $mapped = $this->model->with($this->relation);

        return DataTables::of($mapped)
            ->editColumn('created_at', function ($data) {
                return $data->created_at->format('d/m/Y H:i:s');
            })
            ->toJson();
    }

    public function customRequest($request)
    {
        $data = $request->all();

        unset($data['_token'], $data['_method']);

        if ($request->method() === 'POST' && isset($data['name']) && Permission::where('name', $data['name'])->count() > 0) {
            throw new Exception('Permission already exists');
        }

        return $data;
    }

    /**
     * Tolak hapus permission yang masih dipakai role/user (audit Setup S-14):
     * tanpa ini Spatie diam-diam mencabut izin dari role terkait lewat cascade pivot.
     */
    public function customDestroy($model)
    {
        $tables = config('permission.table_names', []);

        $inRoles = \DB::table($tables['role_has_permissions'] ?? 'role_has_permissions')
            ->where('permission_id', $model->id)
            ->exists();

        $inUsers = \DB::table($tables['model_has_permissions'] ?? 'model_has_permissions')
            ->where('permission_id', $model->id)
            ->where('model_type', 'App\\Models\\User')
            ->exists();

        if ($inRoles || $inUsers) {
            throw new Exception(
                'Permission "'.$model->name.'" masih melekat pada '.($inRoles ? 'satu/lebih role' : '').
                ($inRoles && $inUsers ? ' dan ' : '').($inUsers ? 'user langsung' : '').
                '. Cabut dulu dari role/user terkait sebelum menghapus.'
            );
        }
    }
}

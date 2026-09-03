<?php

namespace App\Http\Controllers;

use App\Helpers\LogHelper;
use DB;
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

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $data = $this->getRequest();

            if (Permission::where('name', $data['name'])->count() <= 0) {
                $model = Permission::create(['name' => $data['name']]);

                $log_helper = new LogHelper;

                $log_helper->storeLog('add', $model->no ?? $model->id, $this->subtitle);
            } else {
                return $this->redirectBackWithError('Permission already exists');
            }

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
}

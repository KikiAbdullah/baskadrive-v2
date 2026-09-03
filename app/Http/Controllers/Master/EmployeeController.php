<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class EmployeeController extends Controller
{
    public function __construct(Employee $model)
    {
        $this->middleware('auth');

        $this->title = 'Employee';
        $this->subtitle = 'Data Karyawan Internal';
        $this->model_request = Request::class;
        $this->folder = 'master';
        $this->relation = [];
        $this->model = $model;
        $this->withTrashed = false;
    }

    public function customRequest($request)
    {
        $data = $request->all();

        unset($data['_token'], $data['_method']);

        if (empty($data['password_hash'])) {
            unset($data['password_hash']);
        }

        return $data;
    }

    public function ajaxData()
    {
        return DataTables::of($this->model->query())
            ->addColumn('full_name', fn ($e) => $e->full_name)
            ->editColumn('is_active', fn ($e) => $e->is_active
                ? '<span class="badge bg-success">Aktif</span>'
                : '<span class="badge bg-secondary">Nonaktif</span>')
            ->rawColumns(['is_active'])
            ->make(true);
    }

    public function toggleActive($id)
    {
        $item = $this->model->findOrFail($id);

        $item->update(['is_active' => ! $item->is_active]);

        return redirect()->back()->withSuccess('Status karyawan diperbarui.');
    }
}
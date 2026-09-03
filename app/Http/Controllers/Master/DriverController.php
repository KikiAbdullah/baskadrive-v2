<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class DriverController extends Controller
{
    public function __construct(Driver $model)
    {
        $this->middleware('auth');

        $this->title = 'Driver';
        $this->subtitle = 'Data Sopir Mitra';
        $this->model_request = Request::class;
        $this->folder = 'master';
        $this->relation = [];
        $this->model = $model;
        $this->withTrashed = false;
    }

    public function ajaxData()
    {
        return DataTables::of($this->model->query())
            ->addColumn('full_name', fn ($d) => $d->full_name)
            ->editColumn('is_active', fn ($d) => $d->is_active
                ? '<span class="badge bg-success">Aktif</span>'
                : '<span class="badge bg-secondary">Nonaktif</span>')
            ->rawColumns(['is_active'])
            ->make(true);
    }

    public function history($id)
    {
        $item = $this->model->with('rentals')->findOrFail($id);

        return view('master.driver.history')->with(['item' => $item]);
    }
}
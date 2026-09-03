<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceType;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class MaintenanceTypeController extends Controller
{
    public function __construct(MaintenanceType $model)
    {
        $this->middleware('auth');

        $this->title = 'Maintenance Type';
        $this->subtitle = 'Jenis Perawatan';
        $this->model_request = Request::class;
        $this->folder = 'master';
        $this->relation = [];
        $this->model = $model;
        $this->withTrashed = false;
    }

    public function ajaxData()
    {
        return DataTables::of($this->model->query())
            ->editColumn('interval_km', fn ($mt) => $mt->interval_km ? number_format($mt->interval_km, 0, ',', '.').' KM' : '-')
            ->editColumn('interval_months', fn ($mt) => $mt->interval_months ? $mt->interval_months.' bulan' : '-')
            ->editColumn('is_active', fn ($mt) => $mt->is_active
                ? '<span class="badge bg-success">Aktif</span>'
                : '<span class="badge bg-secondary">Nonaktif</span>')
            ->rawColumns(['is_active'])
            ->make(true);
    }
}
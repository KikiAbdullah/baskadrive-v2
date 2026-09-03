<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\VehicleModel;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class VehicleModelController extends Controller
{
    public function __construct(VehicleModel $model)
    {
        $this->middleware('auth');

        $this->title = 'Vehicle Model';
        $this->subtitle = 'Spesifikasi Model Mobil';
        $this->model_request = Request::class;
        $this->folder = 'master';
        $this->relation = ['brand'];
        $this->model = $model;
        $this->withTrashed = false;
    }

    public function formData()
    {
        return [
            'list_brand' => Brand::pluck('brand_name', 'brand_id')->toArray(),
        ];
    }

    public function ajaxData()
    {
        return DataTables::of($this->model->with('brand')->select('m_vehicle_model.*'))
            ->addColumn('brand', fn ($m) => $m->brand->brand_name ?? '-')
            ->editColumn('base_price_per_day', fn ($m) => 'Rp '.number_format($m->base_price_per_day, 0, ',', '.'))
            ->editColumn('is_active', fn ($m) => $m->is_active
                ? '<span class="badge bg-success">Aktif</span>'
                : '<span class="badge bg-secondary">Nonaktif</span>')
            ->rawColumns(['is_active'])
            ->make(true);
    }
}
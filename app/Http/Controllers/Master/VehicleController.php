<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class VehicleController extends Controller
{
    public function __construct(Vehicle $model)
    {
        $this->middleware('auth');

        $this->title = 'Vehicle';
        $this->subtitle = 'Data Mobil';
        $this->model_request = Request::class;
        $this->folder = 'master';
        $this->relation = ['model'];
        $this->model = $model;
        $this->withTrashed = false;
    }

    public function formData()
    {
        return [
            'list_model' => VehicleModel::with('brand')->get()->mapWithKeys(fn ($m) => [
                $m->model_id => $m->model_name.' ('.($m->brand->brand_name ?? '-').')',
            ])->toArray(),
        ];
    }

    public function ajaxData()
    {
        $query = $this->model->with(['model.brand']);

        return DataTables::of($query)
            ->addColumn('model', fn ($v) => optional($v->model)->model_name.' ('.optional(optional($v->model)->brand)->brand_name.')')
            ->editColumn('mileage', fn ($v) => number_format($v->mileage, 0, ',', '.').' KM')
            ->editColumn('status', fn ($v) => match ($v->status) {
                'available' => '<span class="badge bg-success">Available</span>',
                'rented' => '<span class="badge bg-primary">Rented</span>',
                'maintenance' => '<span class="badge bg-warning">Maintenance</span>',
                'reserved' => '<span class="badge bg-info">Reserved</span>',
                default => '<span class="badge bg-secondary">Retired</span>',
            })
            ->rawColumns(['status'])
            ->make(true);
    }

    public function barcode($id)
    {
        $item = $this->model->with(['model.brand'])->findOrFail($id);

        return view('master.vehicle.barcode')->with(['item' => $item]);
    }
}
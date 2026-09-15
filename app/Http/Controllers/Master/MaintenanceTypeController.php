<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    public function customRequest($request)
    {
        $data = $this->blanksToNull($request, ['interval_km', 'interval_months', 'description']);

        $request->validate([
            'type_name' => [
                'required', 'string', 'max:50',
                Rule::unique('m_maintenance_type', 'type_name')->ignore($request->route('id'), 'type_id'),
            ],
            'interval_km' => 'nullable|integer|min:0|max:2000000',
            'interval_months' => 'nullable|integer|min:0|max:600',
            'description' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ], [
            'type_name.required' => 'Nama jenis perawatan wajib diisi.',
            'type_name.unique' => 'Jenis perawatan ":input" sudah ada.',
            'interval_km.min' => 'Interval KM tidak boleh negatif.',
            'interval_months.min' => 'Interval bulan tidak boleh negatif.',
        ]);

        return $data;
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
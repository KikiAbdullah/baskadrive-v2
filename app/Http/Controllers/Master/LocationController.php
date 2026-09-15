<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class LocationController extends Controller
{
    public function __construct(Location $model)
    {
        $this->middleware('auth');

        $this->title = 'Location';
        $this->subtitle = 'Lokasi / Cabang';
        $this->model_request = Request::class;
        $this->folder = 'master';
        $this->relation = [];
        $this->model = $model;
        $this->withTrashed = false;
    }

    public function customRequest($request)
    {
        $data = $this->blanksToNull($request, ['latitude', 'longitude', 'contact_phone', 'opening_hours']);

        $request->validate([
            'location_name' => 'required|string|max:100',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:50',
            'province' => 'nullable|string|max:50',
            'contact_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'opening_hours' => 'nullable|string|max:50',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_active' => 'nullable|boolean',
        ], [
            'location_name.required' => 'Nama lokasi/cabang wajib diisi.',
            'contact_phone.regex' => 'Format nomor telepon tidak valid.',
            'latitude.between' => 'Latitude harus di antara -90 s.d. 90.',
            'longitude.between' => 'Longitude harus di antara -180 s.d. 180.',
        ]);

        return $data;
    }

    public function ajaxData()
    {
        return DataTables::of($this->model->query())
            ->addColumn('coords', fn ($l) => $l->latitude && $l->longitude ? $l->latitude.','.$l->longitude : '<span class="text-muted">-</span>')
            ->editColumn('is_active', fn ($l) => $l->is_active
                ? '<span class="badge bg-success">Aktif</span>'
                : '<span class="badge bg-secondary">Nonaktif</span>')
            ->rawColumns(['is_active', 'coords'])
            ->make(true);
    }
}
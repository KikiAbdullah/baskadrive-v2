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

    public function ajaxData()
    {
        return DataTables::of($this->model->query())
            ->editColumn('is_active', fn ($l) => $l->is_active
                ? '<span class="badge bg-success">Aktif</span>'
                : '<span class="badge bg-secondary">Nonaktif</span>')
            ->rawColumns(['is_active'])
            ->make(true);
    }
}
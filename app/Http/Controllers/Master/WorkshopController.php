<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Workshop;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class WorkshopController extends Controller
{
    public function __construct(Workshop $model)
    {
        $this->middleware('auth');

        $this->title = 'Workshop';
        $this->subtitle = 'Bengkel Mitra';
        $this->model_request = Request::class;
        $this->folder = 'master';
        $this->relation = [];
        $this->model = $model;
        $this->withTrashed = false;
    }

    public function ajaxData()
    {
        return DataTables::of($this->model->query())
            ->editColumn('is_active', fn ($w) => $w->is_active
                ? '<span class="badge bg-success">Aktif</span>'
                : '<span class="badge bg-secondary">Nonaktif</span>')
            ->rawColumns(['is_active'])
            ->make(true);
    }
}
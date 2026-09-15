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

    public function customRequest($request)
    {
        $data = $this->blanksToNull($request, ['rating', 'address', 'phone', 'contact_person', 'specialization']);

        $request->validate([
            'name' => 'required|string|max:100',
            'address' => 'nullable|string|max:500',
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'contact_person' => 'nullable|string|max:100',
            'rating' => 'nullable|numeric|between:0,5',
            'specialization' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ], [
            'name.required' => 'Nama bengkel wajib diisi.',
            'rating.between' => 'Rating harus di antara 0 s.d. 5.',
            'phone.regex' => 'Format nomor telepon tidak valid.',
        ]);

        return $data;
    }

    public function ajaxData()
    {
        return DataTables::of($this->model->query())
            ->addColumn('rating_stars', function ($w) {
                if (empty($w->rating)) return '<span class="text-muted">-</span>';
                $full = floor($w->rating);
                $half = ($w->rating - $full) >= 0.5 ? 1 : 0;
                $stars = str_repeat('<i class="ri-star-fill text-warning"></i>', $full)
                       . ($half ? '<i class="ri-star-half-fill text-warning"></i>' : '')
                       . ' <small>'.$w->rating.'</small>';
                return $stars;
            })
            ->editColumn('is_active', fn ($w) => $w->is_active
                ? '<span class="badge bg-success">Aktif</span>'
                : '<span class="badge bg-secondary">Nonaktif</span>')
            ->rawColumns(['is_active', 'rating_stars'])
            ->make(true);
    }
}
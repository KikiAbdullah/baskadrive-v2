<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class PromoController extends Controller
{
    public function __construct(Promo $model)
    {
        $this->middleware('auth');

        $this->title = 'Promo';
        $this->subtitle = 'Manajemen Promo / Diskon';
        $this->model_request = Request::class;
        $this->folder = 'master';
        $this->relation = [];
        $this->model = $model;
        $this->withTrashed = false;
    }

    public function ajaxData()
    {
        return DataTables::of($this->model->query())
            ->editColumn('discount_type', fn ($p) => $p->discount_type === 'percentage' ? 'Persentase (%)' : 'Nominal (Rp)')
            ->editColumn('discount_value', fn ($p) => $p->discount_type === 'percentage'
                ? $p->discount_value.'%'
                : 'Rp '.number_format($p->discount_value, 0, ',', '.'))
            ->addColumn('valid_period', fn ($p) => optional($p->valid_from)->format('d/m/Y').' - '.optional($p->valid_to)->format('d/m/Y'))
            ->editColumn('is_active', fn ($p) => $p->is_active
                ? '<span class="badge bg-success">Aktif</span>'
                : '<span class="badge bg-secondary">Nonaktif</span>')
            ->rawColumns(['is_active'])
            ->make(true);
    }

    public function toggle($id)
    {
        $item = $this->model->findOrFail($id);

        $item->update(['is_active' => ! $item->is_active]);

        return redirect()->back()->withSuccess('Status promo diperbarui.');
    }
}
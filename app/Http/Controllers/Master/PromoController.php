<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            ->addColumn('applicable_categories', function ($p) {
                if (empty($p->applicable_categories)) return '<span class="badge bg-secondary">Semua</span>';
                return collect($p->applicable_categories)->map(fn($c) => '<span class="badge bg-info me-1">'.e(ucfirst($c)).'</span>')->implode('');
            })
            ->addColumn('valid_period', fn ($p) => optional($p->valid_from)->format('d/m/Y').' - '.optional($p->valid_to)->format('d/m/Y'))
            ->addColumn('usage', function ($p) {
                $max = $p->max_usage ?? 0;
                $pct = $max > 0 ? min(100, round(min($p->usage_count ?? 0, $max) / $max * 100)) : 100;
                $bar = $pct >= 100 ? '#ff4d49' : ($pct >= 70 ? '#fdb528' : '#72e128');

                return '<span style="display:inline-block;width:70px;height:8px;background:#eef0ff;border-radius:20px;overflow:hidden;vertical-align:middle;margin-right:6px">'
                    .'<span style="display:block;height:100%;width:'.$pct.'%;background:'.$bar.'"></span></span>'
                    .'<small>'.(int) ($p->usage_count ?? 0).'/'.(int) $max.'</small>';
            })
            ->editColumn('is_active', fn ($p) => $p->is_active
                ? '<span class="badge bg-success">Aktif</span>'
                : '<span class="badge bg-secondary">Nonaktif</span>')
            ->rawColumns(['is_active', 'applicable_categories', 'usage'])
            ->make(true);
    }

    public function customRequest($request)
    {
        $data = $this->blanksToNull($request, ['description', 'min_rental_days', 'max_usage', 'discount_value']);

        if (isset($data['promo_code'])) {
            $data['promo_code'] = strtoupper(trim((string) $data['promo_code']));
            $request->merge(['promo_code' => $data['promo_code']]);
        }

        $request->validate([
            'promo_code' => [
                'required', 'string', 'max:30', 'regex:/^[A-Z0-9\-_]+$/',
                Rule::unique('m_promo', 'promo_code')->ignore($request->route('id'), 'promo_id'),
            ],
            'description' => 'nullable|string|max:255',
            'discount_type' => ['required', Rule::in(['percentage', 'fixed_amount'])],
            'discount_value' => [
                'required', 'numeric', 'min:0', 'max:999999999',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->input('discount_type') === 'percentage' && (float) $value > 100) {
                        $fail('Diskon persentase tidak boleh lebih dari 100%.');
                    }
                },
            ],
            'min_rental_days' => 'nullable|integer|min:1|max:365',
            'valid_from' => 'required|date',
            'valid_to' => 'required|date|after_or_equal:valid_from',
            'max_usage' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ], [
            'promo_code.required' => 'Kode promo wajib diisi.',
            'promo_code.regex' => 'Kode promo hanya boleh huruf, angka, strip dan underscore.',
            'promo_code.unique' => 'Kode promo ":input" sudah dipakai.',
            'valid_to.after_or_equal' => 'Tanggal berakhir promo tidak boleh lebih awal dari tanggal mulai.',
        ]);

        $cats = $request->input('applicable_categories', []);
        $data['applicable_categories'] = !empty($cats) ? array_values((array) $cats) : null;

        return $data;
    }

    public function toggle($id)
    {
        $item = $this->model->findOrFail($id);

        $item->update(['is_active' => ! $item->is_active]);

        return redirect()->back()->withSuccess('Status promo diperbarui.');
    }
}
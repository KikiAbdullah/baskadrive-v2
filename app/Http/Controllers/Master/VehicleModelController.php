<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\VehicleModel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            ->addColumn('photo', function ($m) {
                if (! empty($m->photo)) {
                    $url = asset('storage/vehicle_model/'.$m->photo);
                    return '<img src="'.$url.'" alt="'.e($m->model_name).'" style="width:48px;height:32px;object-fit:cover;border-radius:4px;border:1px solid #e5e5e8;">';
                }

                return '<span class="text-muted">-</span>';
            })
            ->editColumn('base_price_per_day', fn ($m) => 'Rp '.number_format($m->base_price_per_day, 0, ',', '.'))
            ->editColumn('is_active', fn ($m) => $m->is_active
                ? '<span class="badge bg-success">Aktif</span>'
                : '<span class="badge bg-secondary">Nonaktif</span>')
            ->rawColumns(['is_active', 'photo'])
            ->make(true);
    }

    public function customRequest($request)
    {
        $data = $this->blanksToNull($request, [
            'category', 'fuel_type', 'transmission', 'seat_capacity',
            'base_price_per_km', 'insurance_rate', 'deposit_amount',
        ]);

        // Kanonikalisasi ke nilai enum DB (M-03: form lama kirim 'bensin'/'cvt' yang ditolak enum)
        $fuelMap = ['bensin' => 'Petrol', 'diesel' => 'Diesel', 'listrik' => 'Electric', 'hybrid' => 'Hybrid'];
        $transMap = ['manual' => 'Manual', 'automatic' => 'Automatic', 'cvt' => 'CVT'];
        if (! empty($data['fuel_type']) && isset($fuelMap[strtolower((string) $data['fuel_type'])])) {
            $data['fuel_type'] = $fuelMap[strtolower((string) $data['fuel_type'])];
        }
        if (! empty($data['transmission']) && isset($transMap[strtolower((string) $data['transmission'])])) {
            $data['transmission'] = $transMap[strtolower((string) $data['transmission'])];
        }
        $request->merge(['fuel_type' => $data['fuel_type'] ?? null, 'transmission' => $data['transmission'] ?? null]);

        $request->validate([
            'brand_id' => 'required|exists:m_brand,brand_id',
            'model_name' => 'required|string|max:100',
            'category' => ['nullable', Rule::in(['sedan', 'suv', 'mpv', 'hatchback', 'pickup', 'van', 'luxury'])],
            'fuel_type' => ['required', Rule::in(['Petrol', 'Diesel', 'Electric', 'Hybrid'])],
            'transmission' => ['nullable', Rule::in(['Manual', 'Automatic', 'CVT'])],
            'seat_capacity' => 'nullable|integer|min:2|max:20',
            'base_price_per_day' => 'required|numeric|min:0',
            'base_price_per_km' => 'nullable|numeric|min:0',
            'insurance_rate' => 'nullable|numeric|between:0,100',
            'deposit_amount' => 'nullable|numeric|min:0',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'is_active' => 'nullable|boolean',
        ], [
            'model_name.required' => 'Nama model wajib diisi.',
            'brand_id.required' => 'Brand wajib dipilih.',
            'fuel_type.required' => 'Tipe bahan bakar wajib dipilih.',
            'base_price_per_day.required' => 'Harga sewa per hari wajib diisi.',
            'base_price_per_day.min' => 'Harga sewa tidak boleh negatif.',
            'insurance_rate.between' => 'Rate asuransi harus di antara 0 s.d. 100%.',
        ]);

        if ($request->hasFile('photo')) {
            $filename = $this->saveFoto($request->file('photo'), 'vehicle_model');
            if ($filename) $data['photo'] = $filename;
        } else {
            unset($data['photo']);
        }
        return $data;
    }

    public function customUpdate($data, $model, array $originalAttributes = [])
    {
        // hapus foto LAMA (bukan yang baru) saat upload menggantikan (M-02)
        $oldPhoto = $originalAttributes['photo'] ?? null;
        if (isset($data['photo']) && $oldPhoto && $data['photo'] !== $oldPhoto) {
            $this->delImage($oldPhoto, 'vehicle_model');
        }
    }

    public function customDestroy($model)
    {
        if ($model->photo) {
            $this->delImage($model->photo, 'vehicle_model');
        }
    }
}
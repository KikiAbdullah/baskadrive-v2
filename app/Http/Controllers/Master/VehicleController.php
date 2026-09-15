<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Vehicle;
use App\Models\VehicleLocationHistory;
use App\Models\VehicleModel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Picqer\Barcode\BarcodeGeneratorPNG;
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

    public function customRequest($request)
    {
        $data = $this->blanksToNull($request, [
            'vin', 'color', 'year', 'mileage', 'purchase_date', 'purchase_price',
            'current_value', 'engine_number', 'notes',
            'latitude', 'longitude', 'location_id', 'mutation_notes',
        ]);
        unset($data['photo_url']); // hanya diisi lewat upload file di bawah

        if (! empty($data['license_plate'])) {
            $data['license_plate'] = strtoupper(preg_replace('/\s+/', ' ', trim((string) $data['license_plate'])));
            $request->merge(['license_plate' => $data['license_plate']]);
        }

        if (empty($data['status'])) {
            $data['status'] = 'available';
            $request->merge(['status' => 'available']);
        }

        $request->validate([
            'license_plate' => [
                'required', 'string', 'max:15', 'regex:/^[A-Z]{1,2}\s?\d{1,4}\s?[A-Z]{0,3}$/',
                Rule::unique('m_vehicle', 'license_plate')->ignore($request->route('id'), 'vehicle_id'),
            ],
            'vin' => [
                'nullable', 'string', 'size:17', 'alpha_num',
                Rule::unique('m_vehicle', 'vin')->ignore($request->route('id'), 'vehicle_id'),
            ],
            'model_id' => 'required|exists:m_vehicle_model,model_id',
            'location_id' => 'nullable|exists:m_location,location_id',
            'color' => 'nullable|string|max:30',
            'year' => 'nullable|integer|min:1980|max:'.(date('Y') + 1),
            'mileage' => 'nullable|integer|min:0|max:2000000',
            'status' => ['required', Rule::in(['available', 'rented', 'maintenance', 'reserved', 'retired'])],
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric|min:0',
            'current_value' => 'nullable|numeric|min:0',
            'engine_number' => 'nullable|string|max:50',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'notes' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ], [
            'license_plate.required' => 'Nomor polisi wajib diisi.',
            'license_plate.regex' => 'Format nomor polisi tidak valid (contoh: B 1234 ABC).',
            'license_plate.unique' => 'Nomor polisi ":input" sudah terdaftar.',
            'vin.size' => 'VIN/Nomor rangka harus tepat 17 karakter.',
            'vin.unique' => 'VIN ":input" sudah terdaftar.',
            'model_id.required' => 'Model kendaraan wajib dipilih.',
            'year.min' => 'Tahun pembuatan tidak masuk akal (minimal 1980).',
            'year.max' => 'Tahun pembuatan tidak boleh melebihi tahun berikutnya.',
            'mileage.min' => 'Odometer tidak boleh negatif.',
            'latitude.between' => 'Latitude harus di antara -90 s.d. 90.',
            'longitude.between' => 'Longitude harus di antara -180 s.d. 180.',
        ]);

        // Upload foto unit (M-18): simpan filename ke storage/vehicle/
        if ($request->hasFile('photo')) {
            $filename = $this->saveFoto($request->file('photo'), 'vehicle');
            if ($filename) {
                $data['photo_url'] = $filename;
            }
        }
        unset($data['photo']);

        return $data;
    }

    public function formData()
    {
        return [
            'list_model' => VehicleModel::with('brand')->get()->mapWithKeys(fn ($m) => [
                $m->model_id => $m->model_name.' ('.($m->brand->brand_name ?? '-').')',
            ])->toArray(),
            'list_location' => Location::active()->pluck('location_name', 'location_id')->toArray(),
        ];
    }

    public function ajaxData()
    {
        $query = $this->model->with(['model.brand', 'location']);

        return DataTables::of($query)
            ->addColumn('model', fn ($v) => optional($v->model)->model_name.' ('.optional(optional($v->model)->brand)->brand_name.')')
            ->addColumn('location', fn ($v) => $v->location?->location_name ?? '<span class="text-muted">-</span>')
            ->editColumn('mileage', fn ($v) => number_format($v->mileage, 0, ',', '.').' KM')
            ->editColumn('status', fn ($v) => match ($v->status) {
                'available' => '<span class="badge bg-success">Available</span>',
                'rented' => '<span class="badge bg-primary">Rented</span>',
                'maintenance' => '<span class="badge bg-warning">Maintenance</span>',
                'reserved' => '<span class="badge bg-info">Reserved</span>',
                default => '<span class="badge bg-secondary">Retired</span>',
            })
            ->rawColumns(['status', 'location'])
            ->make(true);
    }

    public function customStore($data, $model)
    {
        if (! empty($data['location_id'])) {
            VehicleLocationHistory::create([
                'vehicle_id' => $model->vehicle_id,
                'from_location_id' => null,
                'to_location_id' => $data['location_id'],
                'notes' => 'Penempatan awal',
                'created_by' => auth()->id(),
            ]);
        }
    }

    public function customUpdate($data, $model, array $originalAttributes = [])
    {
        $originalLocation = array_key_exists('location_id', $originalAttributes)
            ? $originalAttributes['location_id']
            : null;

        // M-18: bila foto diganti, hapus file lama (skip URL eksternal)
        $oldPhoto = $originalAttributes['photo_url'] ?? null;
        $newPhoto = $data['photo_url'] ?? null;
        if ($newPhoto && $oldPhoto && $newPhoto !== $oldPhoto && ! str_starts_with((string) $oldPhoto, 'http')) {
            $this->delImage($oldPhoto, 'vehicle');
        }

        $newLocation = $data['location_id'] ?? null;
        if ($originalLocation != $newLocation && ! empty($newLocation)) {
            VehicleLocationHistory::create([
                'vehicle_id' => $model->vehicle_id,
                'from_location_id' => $originalLocation,
                'to_location_id' => $newLocation,
                'notes' => $data['mutation_notes'] ?? null,
                'created_by' => auth()->id(),
            ]);
        }
    }

    public function customDestroy($model)
    {
        $photo = $model->photo_url;
        if ($photo && ! str_starts_with((string) $photo, 'http')) {
            $this->delImage($photo, 'vehicle');
        }
    }

    public function barcode($id)
    {
        $item = $this->model->with(['model.brand', 'location'])->findOrFail($id);
        $generator = new BarcodeGeneratorPNG();
        $barcode = base64_encode($generator->getBarcode($item->license_plate, $generator::TYPE_CODE_128, 2, 50));

        return view('master.vehicle.barcode')->with(['item' => $item, 'barcode' => $barcode]);
    }

    public function barcodePdf($id)
    {
        $item = $this->model->with(['model.brand', 'location'])->findOrFail($id);
        $generator = new BarcodeGeneratorPNG();
        $barcode = base64_encode($generator->getBarcode($item->license_plate, $generator::TYPE_CODE_128, 2, 50));
        $view = 'master.vehicle.barcode-pdf';
        $data = ['item' => $item, 'barcode' => $barcode, 'settings' => \App\Support\AppSettings::all()];
        $filename = 'label-'.$item->license_plate;
        try {
            return \Spatie\LaravelPdf\Facades\Pdf::view($view, $data)->paperSize(50, 30, 'mm')->name($filename)->download();
        } catch (\Throwable $e) {
            return \Spatie\LaravelPdf\Facades\Pdf::view($view, $data)->paperSize(50, 30, 'mm')->driver('dompdf')->name($filename)->download();
        }
    }

    public function history($id)
    {
        $item = $this->model->with(['model.brand'])->findOrFail($id);
        $histories = VehicleLocationHistory::with(['fromLocation', 'toLocation', 'creator'])
            ->where('vehicle_id', $id)->orderBy('created_at', 'desc')->get();

        return view('master.vehicle.history')->with(['item' => $item, 'histories' => $histories, 'title' => 'Vehicle', 'subtitle' => 'Riwayat Mutasi']);
    }
}
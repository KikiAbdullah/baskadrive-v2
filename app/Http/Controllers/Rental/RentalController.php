<?php

namespace App\Http\Controllers\Rental;

use App\Http\Controllers\Controller;
use App\Models\Coa;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Fine;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Promo;
use App\Models\Rental;
use App\Models\RentalDetail;
use App\Models\RentalExtension;
use App\Models\RentalInspection;
use App\Models\Refund;
use App\Models\ReturnCar;
use App\Models\Vehicle;
use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;

class RentalController extends Controller
{
    public function __construct(Rental $model)
    {
        $this->middleware('auth');
        $this->model = $model;
    }

    public function create()
    {
        $step = session()->get('rental_wizard_step', 1);
        $data = session()->get('rental_wizard_data', []);

        $view = [
            'title' => 'Buat Sewa',
            'subtitle' => 'Buat Sewa Baru',
            'step' => $step,
            'data' => $data,
            'customers' => Customer::where('is_blacklisted', false)->orderBy('first_name')->get(),
            'locations' => Location::active()->get(),
            'drivers' => Driver::active()->licenseValid()->get(),
            'promos' => Promo::active()->get(),
        ];

        return view('rental.create')->with($view);
    }

    public function createStep(Request $request, $step)
    {
        $request->session()->put('rental_wizard_step', $step);

        if ($request->ajax()) {
            return $this->renderStep($request, $step);
        }

        return redirect()->route('rental.create');
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->session()->get('rental_wizard_data', []);
            $settings = \App\Support\AppSettings::all();

            if (empty($data['customer_id']) || empty($data['vehicle_id'])) {
                throw new Exception('Data pelanggan dan kendaraan harus diisi.');
            }

            $vehicle = Vehicle::with('model')->findOrFail($data['vehicle_id']);
            $startDate = Carbon::parse($data['rental_start_date']);
            $endDate = Carbon::parse($data['rental_end_date']);
            $rentalDays = $startDate->diffInDays($endDate) ?: 1;

            $baseRate = $vehicle->model->base_price_per_day;
            $totalBase = $baseRate * $rentalDays;
            $insuranceFee = $totalBase * ($vehicle->model->insurance_rate / 100);
            $driverFee = ($data['is_with_driver'] ?? false) ? ($data['driver_fee'] ?? 0) : 0;
            $discountAmount = 0;
            $taxAmount = 0;
            $totalAmount = $totalBase + $insuranceFee + $driverFee;

            if (!empty($data['promo_id'])) {
                // Audit M-08: re-check kuota dengan row-lock dalam transaction yang sama
                $promo = Promo::where('promo_id', $data['promo_id'])->lockForUpdate()->first();
                if ($promo && ! $this->isPromoApplicable($promo, $vehicle)) {
                    $promo = null;
                    $data['promo_id'] = null;
                }
                if ($promo && $promo->max_usage !== null && $promo->usage_count >= $promo->max_usage) {
                    $promo = null;
                    $data['promo_id'] = null;
                }
                if ($promo) {
                    if ($promo->discount_type === 'percentage') {
                        $discountAmount = $totalAmount * ($promo->discount_value / 100);
                    } else {
                        $discountAmount = $promo->discount_value;
                    }
                    $totalAmount -= $discountAmount;
                }
            }

            // PPN: per sewa (tax_percent) → settings (tax_percent bila tax_enabled)
            $taxPercent = $this->resolveTaxPercent($data['tax_percent'] ?? null, $settings);
            $taxAmount = $totalAmount * ($taxPercent / 100);
            $totalAmount += $taxAmount;

            // Deposit: input wizard (bisa 0) → fallback default settings
            $depositAmount = $data['deposit_amount'] ?? null;
            if ($depositAmount === null || $depositAmount === '') {
                $depositAmount = $settings['deposit_enabled'] ? ($vehicle->model->deposit_amount ?: $settings['deposit_default']) : 0;
            }

            $rental = $this->model->create([
                'rental_code' => $this->gen_number($this->model, 'rental_code', 'RNT-$/#####', now(), 'created_at', true),
                'customer_id' => $data['customer_id'],
                'vehicle_id' => $data['vehicle_id'],
                'employee_id' => auth()->user()->employee_id ?? null,
                'pickup_location_id' => $data['pickup_location_id'] ?? null,
                'return_location_id' => $data['return_location_id'] ?? null,
                'promo_id' => $data['promo_id'] ?? null,
                'rental_start_date' => $startDate,
                'rental_end_date' => $endDate,
                'rental_days' => $rentalDays,
                'is_with_driver' => $data['is_with_driver'] ?? false,
                'driver_id' => $data['driver_id'] ?? null,
                'base_rate_per_day' => $baseRate,
                'total_base_price' => $totalBase,
                'insurance_fee' => $insuranceFee,
                'driver_fee' => $driverFee,
                'young_driver_fee' => 0,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'tax_percent' => $taxPercent,
                'deposit_amount' => $depositAmount,
                'total_amount' => $totalAmount,
                'status' => 'reserved',
                'payment_status' => 'unpaid',
                'notes' => $data['notes'] ?? null,
            ]);

            // Audit M-08: konsumsi kuota promo dalam transaction yang sama
            if (!empty($data['promo_id'])) {
                Promo::where('promo_id', $data['promo_id'])->increment('usage_count');
            }

            if (!empty($data['addons'])) {
                foreach ($data['addons'] as $addon) {
                    RentalDetail::create([
                        'rental_id' => $rental->rental_id,
                        'item_type' => $addon['item_type'] ?? 'other',
                        'item_name' => $addon['item_name'],
                        'quantity' => $addon['quantity'] ?? 1,
                        'unit_price' => $addon['unit_price'] ?? 0,
                        'total_price' => ($addon['quantity'] ?? 1) * ($addon['unit_price'] ?? 0),
                    ]);
                }
            }

            $vehicle->update(['status' => 'reserved']);

            DB::commit();

            // Notifikasi WA booking berhasil
            try { $this->sendRentalWA($rental, 'Booking Berhasil', 'Sewa *'.$rental->rental_code.'* berhasil dibuat. Periode: '.$rental->rental_start_date->format('d M Y').' s/d '.$rental->rental_end_date->format('d M Y')); } catch (\Throwable $e) {}

            $request->session()->forget(['rental_wizard_step', 'rental_wizard_data']);

            if ($request->ajax()) {
                return response()->json([
                    'status' => true,
                    'msg' => 'Sewa berhasil dibuat. Kode: ' . $rental->rental_code,
                    'redirect' => route('rental.show', $rental->rental_id),
                ]);
            }

            return redirect()->route('rental.show', $rental->rental_id)
                ->withSuccess('Sewa berhasil dibuat. Kode: ' . $rental->rental_code);
        } catch (Exception $e) {
            DB::rollback();

            if ($request->ajax()) {
                return response()->json([
                    'status' => false,
                    'msg' => $e->getMessage(),
                ]);
            }

            return redirect()->back()->withInput()->withErrors($e->getMessage());
        }
    }

    public function searchCustomer(Request $request)
    {
        $search = $request->get('q', '');
        $customers = Customer::where('is_blacklisted', false)->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        })
            ->orderBy('first_name')
            ->limit(20)
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->customer_id,
                    'text' => $c->full_name . ' - ' . $c->phone . ($c->is_blacklisted ? ' [BLACKLIST]' : ''),
                    'full_name' => $c->full_name,
                    'phone' => $c->phone,
                    'email' => $c->email,
                    'customer_type' => $c->customer_type,
                    'is_verified' => $c->is_verified,
                    'is_blacklisted' => $c->is_blacklisted,
                ];
            });

        return response()->json(['results' => $customers]);
    }

    public function availableVehicles(Request $request)
    {
        $startDate = Carbon::parse($request->get('start_date'));
        $endDate = Carbon::parse($request->get('end_date'));
        $bufferHours = 3;
        // Overlap dengan buffer pembersihan 3 jam setelah rental_end (sesuai audit 2.3)
        $rentedVehicleIds = Rental::whereIn('status', ['ongoing', 'reserved'])
            ->whereRaw("rental_start_date <= ? AND DATE_ADD(rental_end_date, INTERVAL ? HOUR) >= ?", [$endDate, $bufferHours, $startDate])
            ->pluck('vehicle_id');

        $vehicles = Vehicle::with('model.brand')
            ->where('status', 'available')
            ->whereNotIn('vehicle_id', $rentedVehicleIds)
            ->get()
            ->map(function ($v) {
                $model = $v->model;
                $brand = $model?->brand;

                return [
                    'id' => $v->vehicle_id,
                    'text' => $v->license_plate . ' - ' . ($brand?->brand_name ?? '') . ' ' . ($model?->model_name ?? ''),
                    'license_plate' => $v->license_plate,
                    'model_name' => $model?->model_name ?? '',
                    'brand_name' => $brand?->brand_name ?? '',
                    'year' => $v->year,
                    'color' => $v->color,
                    'transmission' => $model?->transmission ?? '',
                    'seat_capacity' => $model?->seat_capacity ?? '',
                    'photo' => $model?->photo ? asset('storage/vehicle_model/'.$model->photo) : null,
                    'base_price_per_day' => $model?->base_price_per_day ?? 0,
                    'deposit_amount' => $model?->deposit_amount ?? 0,
                ];
            });

        return response()->json(array_values($vehicles->toArray()));
    }

    public function calculateTotal(Request $request)
    {
        $vehicleId = $request->get('vehicle_id');
        $startDate = Carbon::parse($request->get('rental_start_date'));
        $endDate = Carbon::parse($request->get('rental_end_date'));
        $rentalDays = $startDate->diffInDays($endDate) ?: 1;
        $withDriver = $request->boolean('is_with_driver');
        $promoId = $request->get('promo_id');
        $settings = \App\Support\AppSettings::all();

        $vehicle = Vehicle::with('model')->findOrFail($vehicleId);
        $baseRate = $vehicle->model->base_price_per_day;
        $totalBase = $baseRate * $rentalDays;
        $insuranceFee = $totalBase * ($vehicle->model->insurance_rate / 100);
        $depositAmount = $settings['deposit_enabled']
            ? ($request->filled('deposit_amount') ? $request->get('deposit_amount') : ($vehicle->model->deposit_amount ?: $settings['deposit_default']))
            : 0;
        $driverFee = 0;

        if ($withDriver) {
            $driver = Driver::find($request->get('driver_id'));
            if ($driver) {
                $driverFee = $request->get('driver_fee', $settings['driver_fee_default']);
            }
        }

        $subtotal = $totalBase + $insuranceFee + $driverFee;
        $discountAmount = 0;

        if ($promoId) {
            $promo = Promo::find($promoId);
            if ($promo && $rentalDays >= $promo->min_rental_days && $this->isPromoApplicable($promo, $vehicle)) {
                if ($promo->discount_type === 'percentage') {
                    $discountAmount = $subtotal * ($promo->discount_value / 100);
                } else {
                    $discountAmount = $promo->discount_value;
                }
            }
        }

        $afterDiscount = $subtotal - $discountAmount;
        $taxPercent = $this->resolveTaxPercent($request->get('tax_percent'), $settings);
        $taxAmount = $afterDiscount * ($taxPercent / 100);
        $totalAmount = $afterDiscount + $taxAmount;

        return response()->json([
            'status' => true,
            'data' => [
                'base_rate_per_day' => (float) $baseRate,
                'rental_days' => $rentalDays,
                'total_base_price' => round($totalBase, 2),
                'insurance_fee' => round($insuranceFee, 2),
                'driver_fee' => round($driverFee, 2),
                'discount_amount' => round($discountAmount, 2),
                'tax_percent' => $taxPercent,
                'tax_amount' => round($taxAmount, 2),
                'deposit_amount' => (float) $depositAmount,
                'total_amount' => round($totalAmount, 2),
            ],
        ]);
    }

    /**
     * Tentukan persentase PPN untuk sebuah sewa.
     * Prioritas: input per sewa → settings (bila tax_enabled) → 0 (tanpa PPN).
     */
    private function resolveTaxPercent($perRental = null, ?array $settings = null): float
    {
        $settings = $settings ?? \App\Support\AppSettings::all();

        // Sewa lama / explicit per-sewa: null = ikut setting, angka = dipakai apa adanya (0 = tanpa PPN)
        if ($perRental !== null && $perRental !== '') {
            return (float) $perRental;
        }

        if (! ($settings['tax_enabled'] ?? true)) {
            return 0.0;
        }

        return (float) ($settings['tax_percent'] ?? 11);
    }

    private function isPromoApplicable(?Promo $promo, ?Vehicle $vehicle): bool
    {
        if (! $promo || empty($promo->applicable_categories)) return true;
        if (! $vehicle || ! $vehicle->model) return true;
        $cat = strtolower($vehicle->model->category ?? '');
        $allowed = array_map('strtolower', (array) $promo->applicable_categories);
        return in_array($cat, $allowed, true);
    }

    private function renderStep(Request $request, $step)
    {
        $data = $request->session()->get('rental_wizard_data', []);

        $viewData = [
            'step' => $step,
            'data' => $data,
            'customers' => Customer::where('is_blacklisted', false)->orderBy('first_name')->get(),
            'locations' => Location::active()->get(),
            'drivers' => Driver::active()->licenseValid()->get(),
            'promos' => Promo::active()->get(),
            'vehicles' => Vehicle::with('model.brand')->where('status', 'available')->get(),
            'settings' => \App\Support\AppSettings::all(),
        ];

        $view = match ((int) $step) {
            2 => view('rental._step-2', $viewData)->render(),
            3 => view('rental._step-3', $viewData)->render(),
            4 => view('rental._step-4', $viewData)->render(),
            default => view('rental._step-1', $viewData)->render(),
        };

        return response()->json([
            'status' => true,
            'view' => $view,
            'step' => (int) $step,
        ]);
    }

    public function saveStep(Request $request)
    {
        $step = $request->get('step', 1);
        $data = $request->session()->get('rental_wizard_data', []);

        $rules = match ((int) $step) {
            2 => ['vehicle_id' => 'required'],
            3 => ['rental_start_date' => 'required|date', 'rental_end_date' => 'required|date|after:rental_start_date'],
            default => ['customer_id' => 'required'],
        };

        $request->validate($rules);

        // Blokir pelanggan blacklist di step 1
        if ((int)$step === 1 && $request->filled('customer_id')) {
            $cust = Customer::find($request->customer_id);
            if ($cust && $cust->is_blacklisted) {
                return response()->json(['status' => false, 'msg' => 'Pelanggan ini diblacklist: '.($cust->blacklist_reason ?? 'tanpa alasan').'. Tidak bisa membuat sewa.'], 422);
            }
        }

        $data = array_merge($data, $request->except('_token', 'step'));
        $request->session()->put('rental_wizard_data', $data);
        $request->session()->put('rental_wizard_step', (int) $step + 1);

        return response()->json([
            'status' => true,
            'step' => (int) $step + 1,
        ]);
    }

    public function index(Request $request)
    {
        $tabs = [
            'all' => 'Semua',
            'reserved' => 'Reservasi',
            'ongoing' => 'Berjalan',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            'overdue' => 'Terlambat',
        ];

        $counts = [
            'all' => Rental::count(),
            'reserved' => Rental::where('status', 'reserved')->count(),
            'ongoing' => Rental::where('status', 'ongoing')->count(),
            'completed' => Rental::where('status', 'completed')->count(),
            'cancelled' => Rental::where('status', 'cancelled')->count(),
            'overdue' => Rental::where('status', 'ongoing')->where('rental_end_date', '<', now())->count(),
        ];

        return view('rental.index')->with([
            'title' => 'Sewa',
            'subtitle' => 'Daftar Transaksi Sewa',
            'status' => $request->get('status', 'all'),
            'tabs' => $tabs,
            'counts' => $counts,
        ]);
    }

    public function data(Request $request)
    {
        $status = $request->get('status', 'all');

        $rentals = Rental::with(['customer', 'vehicle.model.brand', 'pickupLocation']);

        match ($status) {
            'reserved' => $rentals->where('status', 'reserved'),
            'ongoing' => $rentals->where('status', 'ongoing'),
            'completed' => $rentals->where('status', 'completed'),
            'cancelled' => $rentals->where('status', 'cancelled'),
            'overdue' => $rentals->where('status', 'ongoing')->where('rental_end_date', '<', now()),
            default => null,
        };

        $rentals->orderBy('created_at', 'desc');

        return datatables()->of($rentals)
            ->addColumn('rental_code', fn($r) => $r->rental_code)
            ->addColumn('customer_name', fn($r) => $r->customer?->full_name)
            ->addColumn('vehicle_info', fn($r) => $r->vehicle?->license_plate . ' - ' . ($r->vehicle?->model?->brand?->brand_name ?? '') . ' ' . ($r->vehicle?->model?->model_name ?? ''))
            ->addColumn('pickup', fn($r) => $r->pickupLocation?->location_name)
            ->addColumn('date_range', fn($r) => $r->rental_start_date?->format('d/m/Y') . ' - ' . $r->rental_end_date?->format('d/m/Y'))
            ->addColumn('total_amount', fn($r) => 'Rp ' . number_format($r->total_amount, 0, ',', '.'))
            ->addColumn('status_badge', fn($r) => view('components.rental-status-badge', ['status' => $r->status])->render())
            ->addColumn('status', fn($r) => $r->status)
            ->rawColumns(['status_badge'])
            ->toJson();
    }

    public function getButtonOption(Request $request)
    {
        try {
            $rental = Rental::findOrFail($request->get('id'));

            return response()->json([
                'status' => true,
                'view' => view('rental.button_option')->with(['rental' => $rental])->render(),
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function confirmPickup(Request $request, $id)
    {
        try {
            $rental = $this->model->where('status', 'reserved')->findOrFail($id);
            $rental->update(['status' => 'ongoing']);
            try { $this->sendRentalWA($rental, 'Penjemputan Dikonfirmasi', 'Sewa *'.$rental->rental_code.'* telah dikonfirmasi, kendaraan siap diambil.'); } catch (\Throwable $e) {}

            return response()->json([
                'status' => true,
                'msg' => 'Reservasi berhasil dikonfirmasi sebagai sewa aktif.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    public function cancelReservation(Request $request, $id)
    {
        try {
            $rental = $this->model->whereIn('status', ['reserved', 'ongoing'])->findOrFail($id);
            $rental->update(['status' => 'cancelled']);

            // Audit M-08: kembalikan kuota promo yang sudah terkonsumsi
            if ($rental->promo_id) {
                Promo::where('promo_id', $rental->promo_id)->where('usage_count', '>', 0)->decrement('usage_count');
            }

            if ($rental->vehicle) {
                $rental->vehicle->update(['status' => 'available']);
            }

            return response()->json([
                'status' => true,
                'msg' => 'Reservasi berhasil dibatalkan.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    public function show($rental)
    {
        $rental = $this->model->with([
            'customer', 'vehicle.model.brand', 'driver', 'pickupLocation',
            'returnLocation', 'promo', 'details', 'extensions', 'fines', 'invoices', 'payments', 'inspections',
        ])->findOrFail($rental);

        $view = [
            'title' => 'Detail Transaksi',
            'subtitle' => 'Detail Transaksi - ' . $rental->rental_code,
            'rental' => $rental,
        ];

        return view('rental.show')->with($view);
    }

    public function edit(Request $request, $rental)
    {
        $rental = $this->model->with([
            'customer', 'vehicle.model.brand', 'driver', 'pickupLocation', 'returnLocation', 'promo',
        ])->findOrFail($rental);

        $view = [
            'title' => 'Edit Sewa',
            'subtitle' => 'Edit ' . $rental->rental_code,
            'rental' => $rental,
            'customers' => Customer::orderBy('first_name')->get(),
            'locations' => Location::active()->get(),
            'drivers' => Driver::active()->licenseValid()->get(),
            'promos' => Promo::active()->get(),
            'settings' => \App\Support\AppSettings::all(),
        ];

        return view('rental.edit')->with($view);
    }

    public function update(Request $request, $rental)
    {
        try {
            $rental = Rental::findOrFail($rental);

            $validated = $request->validate([
                'rental_start_date' => 'required|date',
                'rental_end_date' => 'required|date|after:rental_start_date',
                'pickup_location_id' => 'nullable|exists:m_location,location_id',
                'return_location_id' => 'nullable|exists:m_location,location_id',
                'is_with_driver' => 'nullable|boolean',
                'driver_id' => 'nullable|exists:m_driver,driver_id',
                'promo_id' => 'nullable|exists:m_promo,promo_id',
                'tax_percent' => 'nullable|numeric|min:0|max:100',
                'deposit_amount' => 'nullable|numeric|min:0',
                'status' => 'required|in:reserved,ongoing,completed,cancelled',
                'notes' => 'nullable|string',
            ]);

            app(\App\Services\AccountingService::class)->assertPeriodOpen($rental->rental_start_date);

            DB::beginTransaction();

            $startDate = Carbon::parse($validated['rental_start_date']);
            $endDate = Carbon::parse($validated['rental_end_date']);
            $days = $startDate->diffInDays($endDate) ?: 1;

            $baseRate = $rental->base_rate_per_day;
            $totalBase = $baseRate * $days;
            $insuranceFee = $rental->vehicle?->model ? $totalBase * ($rental->vehicle->model->insurance_rate / 100) : $rental->insurance_fee;
            $driverFee = ($validated['is_with_driver'] ?? false) ? ($rental->driver_fee ?: 150000 * $days) : 0;

            $subtotal = $totalBase + $insuranceFee + $driverFee;
            $discountAmount = 0;
            // Audit M-08: lacak konsumsi kuota promo sebelum/sesudah edit
            $oldPromoId = $rental->promo_id;
            $effectivePromoId = null;

            if (!empty($validated['promo_id'])) {
                $promo = Promo::where('promo_id', $validated['promo_id'])->lockForUpdate()->first();
                if ($promo && $this->isPromoApplicable($promo, $rental->vehicle)) {
                    if ($promo->max_usage !== null && $promo->usage_count >= $promo->max_usage
                        && (int) $oldPromoId !== (int) $promo->promo_id) {
                        throw new Exception('Kuota promo ini sudah habis terpakai.');
                    }
                    if ($promo->discount_type === 'percentage') {
                        $discountAmount = $subtotal * ($promo->discount_value / 100);
                    } else {
                        $discountAmount = $promo->discount_value;
                    }
                    $effectivePromoId = $promo->promo_id;
                } elseif ($promo && ! $this->isPromoApplicable($promo, $rental->vehicle)) {
                    $validated['promo_id'] = null;
                }
            } elseif ($rental->promo_id) {
                $promo = Promo::find($rental->promo_id);
                if ($promo && $this->isPromoApplicable($promo, $rental->vehicle)) {
                    $discountAmount = $promo->discount_type === 'percentage'
                        ? $subtotal * ($promo->discount_value / 100)
                        : $promo->discount_value;
                    $effectivePromoId = $promo->promo_id;
                }
            }

            $afterDiscount = $subtotal - $discountAmount;
            $taxPercent = $this->resolveTaxPercent(
                $validated['tax_percent'] ?? $rental->tax_percent,
                \App\Support\AppSettings::all()
            );
            $taxAmount = $afterDiscount * ($taxPercent / 100);
            $totalAmount = $afterDiscount + $taxAmount;
            $depositAmount = $validated['deposit_amount'] ?? $rental->deposit_amount ?? 0;

            $rental->update([
                'rental_start_date' => $startDate,
                'rental_end_date' => $endDate,
                'rental_days' => $days,
                'pickup_location_id' => $validated['pickup_location_id'] ?? null,
                'return_location_id' => $validated['return_location_id'] ?? null,
                'is_with_driver' => $validated['is_with_driver'] ?? false,
                'driver_id' => ($validated['is_with_driver'] ?? false) ? ($validated['driver_id'] ?? null) : null,
                'promo_id' => $effectivePromoId,
                'total_base_price' => $totalBase,
                'insurance_fee' => $insuranceFee,
                'driver_fee' => $driverFee,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'tax_percent' => $taxPercent,
                'deposit_amount' => $depositAmount,
                'total_amount' => $totalAmount,
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]);

            // Audit M-08: sesuaikan kuota promo bila assignments berubah saat edit
            if ((int) $oldPromoId !== (int) $effectivePromoId) {
                if ($oldPromoId) {
                    Promo::where('promo_id', $oldPromoId)->where('usage_count', '>', 0)->decrement('usage_count');
                }
                if ($effectivePromoId) {
                    Promo::where('promo_id', $effectivePromoId)->increment('usage_count');
                }
            }

            DB::commit();

            return redirect()->route('rental.show', $rental->rental_id)
                ->withSuccess('Data sewa berhasil diperbarui.');
        } catch (Exception $e) {
            DB::rollback();
            return redirect()->back()->withInput()->withErrors($e->getMessage());
        }
    }

    public function printContract($rental)
    {
        $rental = $this->model->with([
            'customer', 'vehicle.model.brand', 'driver', 'pickupLocation', 'returnLocation', 'details', 'employee',
        ])->findOrFail($rental);

        $settings = \App\Support\AppSettings::all();

        return \App\Support\PdfDocument::download(
            'rental.print',
            ['rental' => $rental, 'settings' => $settings],
            'Kontrak-Sewa-' . $rental->rental_code
        );
    }

    public function export(Request $request)
    {
        $status = $request->get('status');
        $start = $request->get('start_date');
        $end = $request->get('end_date');

        $query = Rental::with(['customer', 'vehicle.model.brand', 'pickupLocation', 'returnLocation'])
            ->when($status && $status !== 'all', fn($q) => $q->where('status', $status))
            ->when($start, fn($q) => $q->whereDate('rental_start_date', '>=', $start))
            ->when($end, fn($q) => $q->whereDate('rental_end_date', '<=', $end))
            ->orderBy('rental_start_date', 'desc');

        $rentals = $query->get();
        $filename = 'rental_export_'.now()->format('Ymd_His').'.csv';
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="'.$filename.'"'];

        $callback = function () use ($rentals) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Kode Sewa','Pelanggan','Telepon','Kendaraan','Plat','Penjemputan','Pengembalian','Mulai','Selesai','Hari','Status','Pembayaran','Total (Rp)']);
            foreach ($rentals as $r) {
                fputcsv($handle, [
                    $r->rental_code,
                    $r->customer?->full_name,
                    $r->customer?->phone,
                    ($r->vehicle?->model?->brand?->brand_name ?? '').' '.($r->vehicle?->model?->model_name ?? ''),
                    $r->vehicle?->license_plate,
                    $r->pickupLocation?->location_name,
                    $r->returnLocation?->location_name,
                    $r->rental_start_date?->format('Y-m-d H:i'),
                    $r->rental_end_date?->format('Y-m-d H:i'),
                    $r->rental_days,
                    $r->status,
                    $r->payment_status,
                    $r->total_amount,
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ============================================================
    // DETAIL SUB-ACTIONS
    // ============================================================

    public function extensionStore(Request $request, $rental)
    {
        try {
            $rental = Rental::whereIn('status', ['reserved', 'ongoing'])->findOrFail($rental);

            $validated = $request->validate([
                'new_end_date' => 'required|date|after:' . $rental->rental_end_date,
                'notes' => 'nullable|string',
            ]);

            $newEnd = Carbon::parse($validated['new_end_date']);
            $oldEnd = Carbon::parse($rental->rental_end_date);
            $days = $oldEnd->diffInDays($newEnd);

            if ($days < 1) {
                return response()->json(['status' => false, 'msg' => 'Perpanjangan minimal 1 hari.']);
            }

            $additionalBase = $rental->base_rate_per_day * $days;
            // PPN perpanjangan mengikuti PPN sewa induknya
            $extensionTaxPercent = (float) ($rental->tax_percent ?? $this->resolveTaxPercent(null, \App\Support\AppSettings::all()));
            $additionalTax = $additionalBase * ($extensionTaxPercent / 100);

            $extension = RentalExtension::create([
                'rental_id' => $rental->rental_id,
                'old_end_date' => $rental->rental_end_date,
                'new_end_date' => $validated['new_end_date'],
                'extended_days' => $days,
                'additional_base_price' => $additionalBase,
                'additional_tax' => $additionalTax,
                'additional_total' => $additionalBase + $additionalTax,
                'status' => 'pending',
                'notes' => $validated['notes'] ?? null,
            ]);

            return response()->json(['status' => true, 'msg' => 'Permintaan perpanjangan dibuat.', 'data' => $extension]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function extensionApprove(Request $request, $rental, $extensionId)
    {
        try {
            DB::beginTransaction();

            $extension = RentalExtension::where('rental_id', $rental)->where('status', 'pending')->findOrFail($extensionId);
            $rental = Rental::findOrFail($rental);

            $extension->update([
                'status' => 'approved',
                'approved_by' => auth()->user()->employee_id ?? null,
                'approved_at' => now(),
            ]);

            $rental->update([
                'rental_end_date' => $extension->new_end_date,
                'total_base_price' => ($rental->total_base_price ?? 0) + $extension->additional_base_price,
                'tax_amount' => ($rental->tax_amount ?? 0) + $extension->additional_tax,
                'total_amount' => ($rental->total_amount ?? 0) + $extension->additional_total,
            ]);

            DB::commit();

            return response()->json(['status' => true, 'msg' => 'Perpanjangan disetujui.']);
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function extensionReject(Request $request, $rental, $extensionId)
    {
        try {
            $extension = RentalExtension::where('rental_id', $rental)->where('status', 'pending')->findOrFail($extensionId);
            $extension->update(['status' => 'rejected']);

            return response()->json(['status' => true, 'msg' => 'Perpanjangan ditolak.']);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function returnForm($rental)
    {
        $rental = Rental::with(['customer', 'vehicle.model.brand'])->where('status', 'ongoing')->findOrFail($rental);

        return view('rental.return')->with([
            'title' => 'Form Pengembalian',
            'subtitle' => 'Pengembalian ' . $rental->rental_code,
            'rental' => $rental,
        ]);
    }

    public function returnStore(Request $request, $rental)
    {
        try {
            $rental = Rental::where('status', 'ongoing')->findOrFail($rental);

            $minOdo = (int) ($rental->vehicle?->mileage ?? 0);

            $validated = $request->validate([
                'return_date' => 'required|date',
                'return_mileage' => 'nullable|integer|min:'.$minOdo,
                'fuel_level' => 'nullable|string',
                'vehicle_condition' => 'required|in:good,minor_damage,damage',
                'damage_description' => 'nullable|string',
                'extra_charge' => 'nullable|numeric',
                'deposit_refund' => 'nullable|numeric',
            ], [
                'return_mileage.min' => 'Odometer pengembalian harus >= odometer kendaraan saat berangkat (:min km). Nilai yang dimasukkan: :input.',
            ]);

            app(\App\Services\AccountingService::class)->assertPeriodOpen($validated['return_date']);

            DB::beginTransaction();

            $return = ReturnCar::create(array_merge($validated, ['rental_id' => $rental->rental_id]));

            $rental->update([
                'status' => 'completed',
                'actual_return_date' => $validated['return_date'],
            ]);

            if ($rental->vehicle) {
                $rental->vehicle->update(['status' => 'available', 'mileage' => $validated['return_mileage'] ?? $rental->vehicle->mileage]);
            }

            // Auto jurnal untuk extra charge (pendapatan tambahan)
            if (! empty($validated['extra_charge']) && $validated['extra_charge'] > 0) {
                $this->createAutoJournal($validated['return_date'], 'RET-'.$rental->rental_code, 'Extra charge pengembalian '.$rental->rental_code, 'rental', [
                    ['account' => $this->resolveCoa('1-2100'), 'debit' => $validated['extra_charge'], 'credit' => 0],
                    ['account' => $this->resolveCoa('4-1200'), 'debit' => 0, 'credit' => $validated['extra_charge']],
                ]);
            }

            DB::commit();

            // Notifikasi WA pengembalian
            try { $this->sendRentalWA($rental, 'Pengembalian Selesai', 'Kendaraan *'.$rental->vehicle->license_plate.'* telah dikembalikan. Terima kasih!'); } catch (\Throwable $e) {}

            return redirect()->route('rental.show', $rental->rental_id)
                ->withSuccess('Pengembalian berhasil diproses.');
        } catch (Exception $e) {
            DB::rollback();
            return redirect()->back()->withInput()->withErrors($e->getMessage());
        }
    }

    public function fineStore(Request $request, $rental)
    {
        try {
            $rental = Rental::findOrFail($rental);

            $validated = $request->validate([
                'fine_type' => 'required|string',
                'description' => 'required|string',
                'amount' => 'required|numeric|min:0',
                'notes' => 'nullable|string',
            ]);

            $fine = Fine::create([
                'rental_id' => $rental->rental_id,
                'fine_type' => $validated['fine_type'],
                'description' => $validated['description'],
                'amount' => $validated['amount'],
                'status' => 'unpaid',
                'issued_date' => now(),
                'issued_by' => auth()->user()->employee_id ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            return response()->json(['status' => true, 'msg' => 'Denda berhasil ditambahkan.', 'data' => $fine]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function finePay(Request $request, $rental, $fineId)
    {
        try {
            $fine = Fine::where('rental_id', $rental)->where('status', 'unpaid')->findOrFail($fineId);
            $fine->update(['status' => 'paid', 'paid_date' => now()]);

            return response()->json(['status' => true, 'msg' => 'Denda berhasil dibayar.']);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function fineWaive(Request $request, $rental, $fineId)
    {
        try {
            $fine = Fine::where('rental_id', $rental)->where('status', 'unpaid')->findOrFail($fineId);
            $fine->update(['status' => 'waived']);

            return response()->json(['status' => true, 'msg' => 'Denda dibebaskan.']);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function invoiceGenerate($rental)
    {
        $rental = Rental::with(['customer', 'vehicle.model.brand', 'details'])->findOrFail($rental);

        $existing = Invoice::where('rental_id', $rental->rental_id)->first();
        if ($existing) {
            return redirect()->route('finance.invoice.show', $existing->invoice_id);
        }

        return view('rental.invoice')->with([
            'title' => 'Buat Invoice',
            'subtitle' => 'Invoice ' . $rental->rental_code,
            'rental' => $rental,
        ]);
    }

    public function invoiceStore(Request $request, $rental)
    {
        try {
            $rental = Rental::findOrFail($rental);

            if (Invoice::where('rental_id', $rental->rental_id)->exists()) {
                return redirect()->route('rental.show', $rental->rental_id)
                    ->withErrors('Invoice untuk sewa ini sudah ada.');
            }

            $validated = $request->validate([
                'due_date' => 'required|date',
                'notes' => 'nullable|string',
            ]);

            DB::beginTransaction();

            $invoiceNumber = $this->gen_number(Invoice::class, 'invoice_number', 'INV-$/#####', now(), 'created_at', true);

            Invoice::create([
                'rental_id' => $rental->rental_id,
                'invoice_number' => $invoiceNumber,
                'issue_date' => now(),
                'due_date' => $validated['due_date'],
                'sub_total' => $rental->total_amount - ($rental->tax_amount ?? 0),
                'tax' => $rental->tax_amount ?? 0,
                'discount' => $rental->discount_amount ?? 0,
                'total_amount' => $rental->total_amount,
                'paid_amount' => $rental->payments()->where('status', 'completed')->sum('amount'),
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
            ]);

            DB::commit();

            try { $this->sendRentalWA($rental, 'Invoice Terbit', 'Invoice *'.$invoiceNumber.'* untuk sewa *'.$rental->rental_code.'* telah terbit. Jatuh tempo: '.Carbon::parse($validated['due_date'])->format('d M Y')); } catch (\Throwable $e) {}

            return redirect()->route('finance.index')->withSuccess('Invoice berhasil dibuat.');
        } catch (Exception $e) {
            DB::rollback();
            return redirect()->back()->withInput()->withErrors($e->getMessage());
        }
    }

    public function invoicePrint($rental)
    {
        $invoice = Invoice::where('rental_id', $rental)->firstOrFail();

        return redirect()->route('finance.invoice.print', $invoice->invoice_id);
    }

    public function paymentStore(Request $request, $rental)
    {
        try {
            $rental = Rental::findOrFail($rental);

            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'payment_method' => 'required|in:cash,transfer,credit_card,debit_card,e_wallet',
                'reference_number' => 'nullable|string',
                'notes' => 'nullable|string',
            ]);

            app(\App\Services\AccountingService::class)->assertPeriodOpen(now()->toDateString());

            DB::beginTransaction();

            $invoice = Invoice::where('rental_id', $rental->rental_id)->first();

            $payment = Payment::create([
                'invoice_id' => $invoice?->invoice_id,
                'rental_id' => $rental->rental_id,
                'payment_date' => now(),
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'reference_number' => $validated['reference_number'] ?? null,
                'status' => 'completed',
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($invoice) {
                $paid = $invoice->paid_amount + $validated['amount'];
                $invoice->update([
                    'paid_amount' => $paid,
                    'status' => $paid >= $invoice->total_amount ? 'paid' : 'partially_paid',
                ]);
            }

            $rental->update(['payment_status' => 'paid']);

            // Auto jurnal: Dr Kas/Bank, Cr Piutang
            $cashAccount = $validated['payment_method'] === 'cash' ? $this->resolveCoa('1-1100') : $this->resolveCoa('1-1200');
            $this->createAutoJournal(now()->toDateString(), 'PAY-'.$payment->payment_id, 'Pembayaran sewa '.$rental->rental_code, 'payment', [
                ['account' => $cashAccount, 'debit' => $validated['amount'], 'credit' => 0],
                ['account' => $this->resolveCoa('1-2100'), 'debit' => 0, 'credit' => $validated['amount']],
            ]);

            DB::commit();

            try { $this->sendRentalWA($rental, 'Pembayaran Diterima', 'Pembayaran *Rp '.number_format($validated['amount'],0,',','.').'* untuk sewa *'.$rental->rental_code.'* telah diterima. Terima kasih!'); } catch (\Throwable $e) {}

            return response()->json(['status' => true, 'msg' => 'Pembayaran berhasil dicatat.', 'data' => $payment]);
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function refundStore(Request $request, $rental)
    {
        try {
            $rental = Rental::findOrFail($rental);

            $validated = $request->validate([
                'payment_id' => 'required|exists:tr_payment,payment_id',
                'amount' => 'required|numeric|min:0.01',
                'refund_type' => 'required|in:deposit,cancelled,overpayment',
                'notes' => 'nullable|string',
            ]);

            $refund = Refund::create([
                'payment_id' => $validated['payment_id'],
                'rental_id' => $rental->rental_id,
                'refund_date' => now(),
                'amount' => $validated['amount'],
                'refund_type' => $validated['refund_type'],
                'status' => 'processed',
                'notes' => $validated['notes'] ?? null,
            ]);

            return response()->json(['status' => true, 'msg' => 'Refund berhasil dicatat.', 'data' => $refund]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function handoverForm($rental, $type)
    {
        $rental = $this->model->with(['customer', 'vehicle.model.brand', 'inspections'])->findOrFail($rental);
        $inspection = $rental->inspections()->where('inspection_type', $type === 'in' ? 'handover_in' : 'handover_out')->latest()->first();

        return view('rental.handover')->with([
            'title' => $type === 'out' ? 'Inspeksi Serah Terima Awal' : 'Inspeksi Pengembalian',
            'subtitle' => $rental->rental_code.' - '.($type === 'out' ? 'Checklist Awal' : 'Checklist Akhir'),
            'rental' => $rental,
            'type' => $type,
            'inspection' => $inspection,
        ]);
    }

    public function handoverStore(Request $request, $rental, $type)
    {
        try {
            $rental = Rental::findOrFail($rental);
            $validated = $request->validate([
                'odometer' => 'nullable|integer',
                'fuel_level' => 'nullable|in:full,three_quarter,half,quarter,empty',
                'body_damage_points' => 'nullable|json',
                'checklist' => 'nullable|array',
                'exterior_notes' => 'nullable|string',
                'interior_notes' => 'nullable|string',
                'notes' => 'nullable|string',
            ]);

            $inspection = RentalInspection::create([
                'rental_id' => $rental->rental_id,
                'vehicle_id' => $rental->vehicle_id,
                'inspection_type' => $type === 'in' ? 'handover_in' : 'handover_out',
                'odometer' => $validated['odometer'] ?? null,
                'fuel_level' => $validated['fuel_level'] ?? null,
                'body_damage_points' => isset($validated['body_damage_points']) ? json_decode($validated['body_damage_points'], true) : null,
                'checklist' => $validated['checklist'] ?? null,
                'exterior_notes' => $validated['exterior_notes'] ?? null,
                'interior_notes' => $validated['interior_notes'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            return redirect()->route('rental.show', $rental->rental_id)->withSuccess('Inspeksi '.($type === 'out' ? 'awal' : 'akhir').' berhasil disimpan.');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->withErrors($e->getMessage());
        }
    }

    private function createAutoJournal($date, $ref, $desc, $type, array $lines): void
    {
        app(\App\Services\AccountingService::class)->postQuietly($date, $ref, $desc, $type, $lines);
    }

    private function resolveCoa(string $code): ?int
    {
        return app(\App\Services\AccountingService::class)->resolveCoa($code);
    }

    private function sendRentalWA(Rental $rental, string $subject, string $text): void
    {
        if (! function_exists('kirimWA') || empty($rental->customer?->phone)) return;
        try { kirimWA($rental->customer->phone, $subject, $text); } catch (\Throwable $e) {}
        // H-1 reminder bisa dipanggil via scheduler: Rental::where('rental_start_date', tomorrow)->each(...)
    }
}
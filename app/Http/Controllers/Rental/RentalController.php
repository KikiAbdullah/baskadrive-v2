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
use Illuminate\Validation\ValidationException;
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
            $response = $this->renderStep($request, $step);
            // Cegah cache GET yang mengubah state session
            return $response->header('Cache-Control', 'no-store, no-cache, must-revalidate')
                ->header('Pragma', 'no-cache');
        }

        return redirect()->route('rental.create');
    }

    public function store(Request $request)
    {
        $inTx = false;

        try {
            DB::beginTransaction();
            $inTx = true;

            $data = $request->session()->get('rental_wizard_data', []);
            $settings = \App\Support\AppSettings::all();

            if (empty($data['customer_id']) || empty($data['vehicle_id'])) {
                throw new Exception('Data pelanggan dan kendaraan harus diisi.');
            }

            $vehicle = Vehicle::with('model')->findOrFail($data['vehicle_id']);

            // FASE 1 audit sewa: re-check blacklist (pemicu asli bypass via session wizard)
            $customer = Customer::find($data['customer_id']);
            if (! $customer) {
                throw new Exception('Pelanggan tidak ditemukan.');
            }
            if ($customer->is_blacklisted) {
                throw new Exception('Pelanggan diblacklist: '.($customer->blacklist_reason ?? 'tanpa alasan').'. Tidak dapat membuat sewa.');
            }

            $startDate = Carbon::parse($data['rental_start_date']);
            $endDate = Carbon::parse($data['rental_end_date']);
            $rentalDays = $startDate->diffInDays($endDate) ?: 1;

            // FASE 1 audit sewa: lock baris kendaraan + enforcement overlap/buffer (anti double-booking)
            Vehicle::where('vehicle_id', $vehicle->vehicle_id)->lockForUpdate()->first();
            $this->assertVehicleFree((int) $vehicle->vehicle_id, $startDate, $endDate);

            $baseRate = $vehicle->model->base_price_per_day;
            $totalBase = $baseRate * $rentalDays;
            $insuranceFee = $totalBase * ($vehicle->model->insurance_rate / 100);
            // driver_fee wizard = tarif HARIAN; kolom DB & fn_calculate_rental_total memakai TOTAL
            $driverFee = ($data['is_with_driver'] ?? false)
                ? max(0.0, (float) ($data['driver_fee'] ?? $settings['driver_fee_default'] ?? 0)) * $rentalDays
                : 0.0;
            $youngDriverFee = $this->youngDriverFee($customer, $settings);
            $addonsTotal = 0.0;
            foreach ((array) ($data['addons'] ?? []) as $a) {
                $addonsTotal += (float) ($a['quantity'] ?? 1) * (float) ($a['unit_price'] ?? 0);
            }

            $discountAmount = 0;
            $taxAmount = 0;
            $totalAmount = $totalBase + $insuranceFee + $driverFee + $youngDriverFee + $addonsTotal;

            if (!empty($data['promo_id'])) {
                // FASE 1 audit sewa: validasi promo UTUH (aktif, masa berlaku, min hari, kategori, kuota + row-lock)
                $promo = $this->usablePromo($data['promo_id'], $vehicle, $rentalDays);
                if ($promo) {
                    $discountAmount = $this->computeDiscount($promo, $totalAmount);
                    $totalAmount -= $discountAmount;
                } else {
                    $data['promo_id'] = null;
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
                'young_driver_fee' => $youngDriverFee,
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
            $inTx = false;

            // Notifikasi WA booking berhasil
            try { $this->sendRentalWA($rental, 'Booking Berhasil', 'Sewa *'.$rental->rental_code.'* berhasil dibuat. Periode: '.$rental->rental_start_date->format('d M Y').' s/d '.$rental->rental_end_date->format('d M Y')); } catch (\Throwable $e) {}

            $request->session()->forget(['rental_wizard_step', 'rental_wizard_data']);

            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'status' => true,
                    'msg' => 'Sewa berhasil dibuat. Kode: ' . $rental->rental_code,
                    'redirect' => route('rental.show', $rental->rental_id),
                ]);
            }

            return redirect()->route('rental.show', $rental->rental_id)
                ->withSuccess('Sewa berhasil dibuat. Kode: ' . $rental->rental_code);
        } catch (Exception $e) {
            if ($inTx) {
                DB::rollback();
            }

            if ($request->ajax() || $request->expectsJson()) {
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
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);
        $startDate = Carbon::parse($request->input('start_date'));
        $endDate = Carbon::parse($request->input('end_date'));
        // Overlap dengan buffer pembersihan (audit 2.3) — batas dihitung di PHP agar portabel
        $startMinusBuffer = $startDate->copy()->subHours(self::OVERLAP_BUFFER_HOURS);
        $rentedVehicleIds = Rental::whereIn('status', ['ongoing', 'reserved'])
            ->where('rental_start_date', '<=', $endDate)
            ->where('rental_end_date', '>=', $startMinusBuffer)
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
            $driverFee = max(0.0, (float) $request->get('driver_fee', $settings['driver_fee_default'] ?? 0)) * $rentalDays;
        }

        // Preview harus sama dengan store(): young driver + add-on (dari session wizard) + promo utuh
        $youngDriverFee = $this->youngDriverFee(
            Customer::find(session()->get('rental_wizard_data.customer_id')),
            $settings
        );
        $addonsTotal = 0.0;
        foreach ((array) (session()->get('rental_wizard_data.addons') ?? []) as $a) {
            $addonsTotal += (float) ($a['quantity'] ?? 1) * (float) ($a['unit_price'] ?? 0);
        }

        $subtotal = $totalBase + $insuranceFee + $driverFee + $youngDriverFee + $addonsTotal;
        $discountAmount = 0;

        if ($promoId) {
            $promo = $this->usablePromo($promoId, $vehicle, $rentalDays, lock: false);
            if ($promo) {
                $discountAmount = $this->computeDiscount($promo, $subtotal);
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

    /**
     * Buffer pembersihan antar-sewa (jam) — dipakai UI ketersediaan maupun enforcement backend.
     */
    private const OVERLAP_BUFFER_HOURS = 3;

    /**
     * FASE 1 audit sewa: enforcement anti double-booking di backend (tidak hanya filter UI).
     * Batas buffer dihitung di PHP (portabel MySQL/SQLite & tetap memakai index
     * idx_rental_dates): overlap bila start <= $end AND end >= $start - buffer.
     * Baris kandidat di-lock (FOR UPDATE) agar dua request paralel tidak lolos cek yang sama.
     */
    private function assertVehicleFree(int $vehicleId, Carbon $start, Carbon $end, ?int $exceptRentalId = null): void
    {
        $startMinusBuffer = $start->copy()->subHours(self::OVERLAP_BUFFER_HOURS);

        $conflict = Rental::query()
            ->where('vehicle_id', $vehicleId)
            ->whereIn('status', ['reserved', 'ongoing'])
            ->when($exceptRentalId, fn ($q) => $q->where('rental_id', '!=', $exceptRentalId))
            ->where('rental_start_date', '<=', $end)
            ->where('rental_end_date', '>=', $startMinusBuffer)
            ->lockForUpdate()
            ->exists();

        if ($conflict) {
            throw new Exception('Kendaraan tidak tersedia pada rentang tanggal tersebut (sudah dipesan periode lain, termasuk buffer pembersihan '.self::OVERLAP_BUFFER_HOURS.' jam).');
        }
    }

    /**
     * FASE 1 audit sewa: validasi promo UTUH — aktif, dalam masa berlaku, min hari,
     * kategori kendaraan, dan kuota (row-lock saat dipakai). Return null = promo gugur.
     */
    private function usablePromo($promoId, ?Vehicle $vehicle, int $rentalDays, bool $lock = true, bool $lenientQuota = false): ?Promo
    {
        if (! $promoId) return null;

        $query = Promo::where('promo_id', $promoId);
        if ($lock) $query->lockForUpdate();
        $promo = $query->first();

        if (! $promo || ! $promo->is_active) return null;
        if ($promo->valid_from && now()->lt($promo->valid_from->copy()->startOfDay())) return null;
        if ($promo->valid_to && now()->gt($promo->valid_to->copy()->endOfDay())) return null;
        if ($rentalDays < (int) ($promo->min_rental_days ?? 1)) return null;
        if (! $this->isPromoApplicable($promo, $vehicle)) return null;
        if (! $lenientQuota && $promo->max_usage !== null && $promo->usage_count >= $promo->max_usage) return null;

        return $promo;
    }

    /**
     * FASE 1 audit sewa: clamp diskon agar tidak pernah melebihi subtotal (anti total negatif).
     */
    private function computeDiscount(Promo $promo, float $subtotal): float
    {
        $discount = $promo->discount_type === 'percentage'
            ? $subtotal * ((float) $promo->discount_value / 100)
            : (float) $promo->discount_value;

        return max(0.0, min($discount, $subtotal));
    }

    /**
     * §4 audit sewa: biaya young driver — pelanggan umur < young_driver_age setting.
     */
    private function youngDriverFee(?Customer $customer, array $settings): float
    {
        if (! $customer || empty($customer->date_of_birth) || empty($settings['young_driver_fee_default'])) {
            return 0.0;
        }

        $age = Carbon::parse($customer->date_of_birth)->age;

        return $age < (int) ($settings['young_driver_age'] ?? 21) ? (float) $settings['young_driver_fee_default'] : 0.0;
    }

    private function addonsTotalFor(?int $rentalId): float
    {
        if (! $rentalId) return 0.0;

        return (float) RentalDetail::where('rental_id', $rentalId)->sum('total_price');
    }

    /**
     * Normalisasi nilai lama/alias UI ke enum DB (FASE 1-2 audit sewa).
     */
    private static function normalizeEnum($value, array $aliases, array $allowed): ?string
    {
        $value = $aliases[$value] ?? $value;

        return in_array($value, $allowed, true) ? $value : null;
    }

    private function renderStep(Request $request, $step)
    {
        $data = $request->session()->get('rental_wizard_data', []);

        // FASE 3 audit sewa: resolusi relasi ( Customer/Vehicle/Lokasi/Sopir/Promo )
        // dipindah KE controller — view tidak lagi mengakses DB langsung (N+1 di blade).
        $customer = isset($data['customer_id']) ? Customer::find($data['customer_id']) : null;
        $vehicle = isset($data['vehicle_id']) ? Vehicle::with('model.brand')->find($data['vehicle_id']) : null;
        $pickupLoc = isset($data['pickup_location_id']) ? Location::find($data['pickup_location_id']) : null;
        $returnLoc = isset($data['return_location_id']) ? Location::find($data['return_location_id']) : null;
        $driver = (! empty($data['is_with_driver']) && ! empty($data['driver_id'])) ? Driver::find($data['driver_id']) : null;
        $promo = ! empty($data['promo_id']) ? Promo::find($data['promo_id']) : null;

        $viewData = [
            'step' => $step,
            'data' => $data,
            'customers' => Customer::where('is_blacklisted', false)->orderBy('first_name')->get(),
            'locations' => Location::active()->get(),
            'drivers' => Driver::active()->licenseValid()->get(),
            'promos' => Promo::active()->get(),
            'vehicles' => Vehicle::with('model.brand')->where('status', 'available')->get(),
            'settings' => \App\Support\AppSettings::all(),
            'stepCustomer' => $customer,
            'stepVehicle' => $vehicle,
            'stepPickupLoc' => $pickupLoc,
            'stepReturnLoc' => $returnLoc,
            'stepDriver' => $driver,
            'stepPromo' => $promo,
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

        // Allowlist + validasi ketat per langkah — cegah injeksi promo_id/deposit/addons via POST manual
        [$rules, $allowed] = match ((int) $step) {
            1 => [
                ['customer_id' => 'required|exists:m_customer,customer_id'],
                ['customer_id'],
            ],
            2 => [
                ['vehicle_id' => 'required|exists:m_vehicle,vehicle_id'],
                ['vehicle_id'],
            ],
            3 => [
                [
                    'rental_start_date' => 'required|date|after_or_equal:today',
                    'rental_end_date' => 'required|date|after:rental_start_date',
                    'pickup_location_id' => 'nullable|exists:m_location,location_id',
                    'return_location_id' => 'nullable|exists:m_location,location_id',
                    'is_with_driver' => 'nullable|boolean',
                    'driver_id' => 'nullable|exists:m_driver,driver_id',
                    'driver_fee' => 'nullable|numeric|min:0|max:2000000',
                    'promo_id' => 'nullable|exists:m_promo,promo_id',
                    'tax_percent' => 'nullable|numeric|min:0|max:100',
                    'deposit_amount' => 'nullable|numeric|min:0',
                    'notes' => 'nullable|string|max:500',
                    'addons' => 'nullable|array|max:10',
                    'addons.*.item_name' => 'required_with:addons|string|max:100',
                    'addons.*.quantity' => 'required_with:addons|integer|min:1|max:99',
                    'addons.*.unit_price' => 'required_with:addons|numeric|min:0|max:99999999',
                    'addons.*.item_type' => 'nullable|string|in:other,gps,child_seat,extra_driver',
                ],
                ['rental_start_date','rental_end_date','pickup_location_id','return_location_id','is_with_driver','driver_id','driver_fee','promo_id','tax_percent','deposit_amount','notes','addons'],
            ],
            default => [['customer_id' => 'required|exists:m_customer,customer_id'], ['customer_id']],
        };

        $request->validate($rules);

        // Blokir pelanggan blacklist di step 1
        if ((int)$step === 1 && $request->filled('customer_id')) {
            $cust = Customer::find($request->customer_id);
            if ($cust && $cust->is_blacklisted) {
                return response()->json(['status' => false, 'msg' => 'Pelanggan ini diblacklist: '.($cust->blacklist_reason ?? 'tanpa alasan').'. Tidak bisa membuat sewa.'], 422);
            }
        }

        $validated = $request->only($allowed);
        // Normalisasi boolean checkbox
        if (array_key_exists('is_with_driver', $validated)) {
            $validated['is_with_driver'] = (bool) $request->boolean('is_with_driver');
        }
        $data = array_merge($data, $validated);
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
            'overdue' => Rental::where('status', 'overdue')->count(),
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
        $request->validate([
            'status' => 'nullable|string|in:all,reserved,ongoing,completed,cancelled,overdue',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $status = $request->get('status', 'all');
        $start = $request->get('start_date');
        $end = $request->get('end_date');

        $rentals = Rental::with(['customer', 'vehicle.model.brand', 'pickupLocation'])
            ->when($start, fn($q) => $q->where('rental_start_date', '>=', \Carbon\Carbon::parse($start)->startOfDay()))
            ->when($end, fn($q) => $q->where('rental_end_date', '<=', \Carbon\Carbon::parse($end)->endOfDay()));

        match ($status) {
            'reserved' => $rentals->where('status', 'reserved'),
            'ongoing' => $rentals->where('status', 'ongoing'),
            'completed' => $rentals->where('status', 'completed'),
            'cancelled' => $rentals->where('status', 'cancelled'),
            'overdue' => $rentals->where(function ($q) {
                $q->where('status', 'overdue')
                    ->orWhere(fn ($qq) => $qq->where('status', 'ongoing')->where('rental_end_date', '<', now()));
            }),
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
            $rental = $this->model->whereIn('status', ['reserved', 'ongoing', 'overdue'])->findOrFail($id);
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
        $inTx = false;

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
                'status' => 'required|in:reserved,ongoing,completed,cancelled,overdue',
                'notes' => 'nullable|string',
            ]);

            // FASE 1 audit sewa: state machine — form edit TIDAK boleh mengubah status
            // melewati alur resmi (confirm/return/cancel punya aksi + permission sendiri).
            $allowedTransitions = [
                'reserved' => ['reserved', 'ongoing'],
                'ongoing' => ['ongoing'],
                'completed' => ['completed'],
                'cancelled' => ['cancelled'],
                'overdue' => ['overdue', 'ongoing'],
            ];
            if (! in_array($validated['status'], $allowedTransitions[$rental->status] ?? [$rental->status], true)) {
                throw new Exception(sprintf(
                    'Transisi status %s → %s tidak diizinkan dari form edit. Gunakan aksi Konfirmasi/Pengembalian/Pembatalan.',
                    $rental->status,
                    $validated['status']
                ));
            }

            $accounting = app(\App\Services\AccountingService::class);
            // Periode terkunci: cek tanggal LAMA dan tanggal BARU (audit sewa §9)
            $accounting->assertPeriodOpen($rental->rental_start_date);
            $accounting->assertPeriodOpen($validated['rental_start_date']);

            DB::beginTransaction();
            $inTx = true;

            $startDate = Carbon::parse($validated['rental_start_date']);
            $endDate = Carbon::parse($validated['rental_end_date']);
            $days = $startDate->diffInDays($endDate) ?: 1;

            // Anti double-booking saat tanggal dipindahkan (exclude rental ini sendiri)
            Vehicle::where('vehicle_id', $rental->vehicle_id)->lockForUpdate()->first();
            $this->assertVehicleFree((int) $rental->vehicle_id, $startDate, $endDate, (int) $rental->rental_id);

            $baseRate = $rental->base_rate_per_day;
            $totalBase = $baseRate * $days;
            $insuranceFee = $rental->vehicle?->model ? $totalBase * ($rental->vehicle->model->insurance_rate / 100) : $rental->insurance_fee;
            // driver_fee tersimpan = TOTAL; jika 0 (dulu flat/hilang) fallback default × hari (precedence diperbaiki)
            $driverFee = ($validated['is_with_driver'] ?? false)
                ? max(0.0, (float) ($rental->driver_fee ?: (float) (\App\Support\AppSettings::get('driver_fee_default') ?? 150000) * $days))
                : 0.0;
            $youngDriverFee = $this->youngDriverFee($rental->customer, \App\Support\AppSettings::all());
            $addonsTotal = $this->addonsTotalFor($rental->rental_id);

            $subtotal = $totalBase + $insuranceFee + $driverFee + $youngDriverFee + $addonsTotal;
            $discountAmount = 0;
            // Audit M-08: lacak konsumsi kuota promo sebelum/sesudah edit
            $oldPromoId = $rental->promo_id;
            $effectivePromoId = null;

            if (!empty($validated['promo_id'])) {
                // Rental ini sendiri sudahconsume 1 slot promo yang sama → kuota dimaafkan
                $promo = $this->usablePromo(
                    $validated['promo_id'],
                    $rental->vehicle,
                    $days,
                    lenientQuota: (int) $oldPromoId === (int) $validated['promo_id']
                );
                if ($promo) {
                    $discountAmount = $this->computeDiscount($promo, $subtotal);
                    $effectivePromoId = $promo->promo_id;
                }
            } elseif ($rental->promo_id) {
                // promo lama dipertahankan: tetap validasi tapi tanpa consume kuota baru (sudah dihitung saat create)
                $promo = Promo::find($rental->promo_id);
                if ($promo && $this->isPromoApplicable($promo, $rental->vehicle)) {
                    $discountAmount = $this->computeDiscount($promo, $subtotal);
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
                'young_driver_fee' => $youngDriverFee,
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
            $inTx = false;

            return redirect()->route('rental.show', $rental->rental_id)
                ->withSuccess('Data sewa berhasil diperbarui.');
        } catch (ValidationException $e) {
            if ($inTx) {
                DB::rollback();
            }
            throw $e;
        } catch (Exception $e) {
            if ($inTx) {
                DB::rollback();
            }
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
        $request->validate([
            'status' => 'nullable|string|in:all,reserved,ongoing,completed,cancelled,overdue',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $status = $request->get('status');
        $start = $request->get('start_date');
        $end = $request->get('end_date');

        $query = Rental::with(['customer', 'vehicle.model.brand', 'pickupLocation', 'returnLocation'])
            ->when($status && $status !== 'all', fn($q) => $q->where('status', $status))
            ->when($start, fn($q) => $q->where('rental_start_date', '>=', \Carbon\Carbon::parse($start)->startOfDay()))
            ->when($end, fn($q) => $q->where('rental_end_date', '<=', \Carbon\Carbon::parse($end)->endOfDay()))
            ->orderBy('rental_start_date', 'desc');

        $filename = 'rental_export_'.now()->format('Ymd_His').'.csv';
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="'.$filename.'"'];

        $callback = function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Kode Sewa','Pelanggan','Telepon','Kendaraan','Plat','Penjemputan','Pengembalian','Mulai','Selesai','Hari','Status','Pembayaran','Total (Rp)']);
            foreach ($query->cursor() as $r) {
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
        $inTx = false;

        try {
            DB::beginTransaction();
            $inTx = true;

            $rental = Rental::whereIn('status', ['reserved', 'ongoing', 'overdue'])->lockForUpdate()->findOrFail($rental);

            $validated = $request->validate([
                'new_end_date' => 'required|date|after:' . $rental->rental_end_date,
                'notes' => 'nullable|string',
            ]);

            $newEnd = Carbon::parse($validated['new_end_date']);
            $oldEnd = Carbon::parse($rental->rental_end_date);
            $days = $oldEnd->diffInDays($newEnd);

            if ($days < 1) {
                throw new Exception('Perpanjangan minimal 1 hari.');
            }

            // FASE 1 audit sewa: jendela perpanjangan tidak boleh menabrak booking kendaraan lain
            $this->assertVehicleFree((int) $rental->vehicle_id, $oldEnd, $newEnd, (int) $rental->rental_id);

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

            DB::commit();
            $inTx = false;

            return response()->json(['status' => true, 'msg' => 'Permintaan perpanjangan dibuat.', 'data' => $extension]);
        } catch (ValidationException $e) {
            if ($inTx) {
                DB::rollback();
            }
            throw $e;
        } catch (Exception $e) {
            if ($inTx) {
                DB::rollback();
            }
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function extensionApprove(Request $request, $rental, $extensionId)
    {
        try {
            DB::beginTransaction();

            $extension = RentalExtension::where('rental_id', $rental)->where('status', 'pending')->lockForUpdate()->firstOrFail();
            $rental = Rental::lockForUpdate()->findOrFail($rental);

            // FASE 1 audit sewa: overlap check saat approve (jendela perpanjangan vs booking lain)
            $this->assertVehicleFree(
                (int) $rental->vehicle_id,
                Carbon::parse($rental->rental_end_date),
                Carbon::parse($extension->new_end_date),
                (int) $rental->rental_id
            );

            $extension->update([
                'status' => 'approved',
                'approved_by' => auth()->user()->employee_id ?? null,
                'approved_at' => now(),
            ]);

            $rental->update([
                'rental_end_date' => $extension->new_end_date,
                // §3.5 audit sewa: rental_days ikut diperbarui (sebelumnya drift)
                'rental_days' => Carbon::parse($rental->rental_start_date)->diffInDays($extension->new_end_date) ?: $rental->rental_days,
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
        $rental = Rental::with(['customer', 'vehicle.model.brand'])->whereIn('status', ['ongoing', 'overdue'])->findOrFail($rental);

        return view('rental.return')->with([
            'title' => 'Form Pengembalian',
            'subtitle' => 'Pengembalian ' . $rental->rental_code,
            'rental' => $rental,
        ]);
    }

    public function returnStore(Request $request, $rental)
    {
        $inTx = false;

        try {
            $rental = Rental::whereIn('status', ['ongoing', 'overdue'])->lockForUpdate()->findOrFail($rental);

            // FASE 1-2 audit sewa: enum DB = excellent|good|fair|damaged; map nama lama UI
            $conditionAliases = ['minor_damage' => 'fair', 'damage' => 'damaged'];
            $incoming = (string) $request->input('vehicle_condition', '');
            $request->merge([
                'vehicle_condition' => $conditionAliases[$incoming] ?? $incoming,
            ]);

            // §3.4 audit sewa: patokan odometer = hasil inspeksi serah terima (handover_out),
            // fallback mileage kendaraan jika inspeksi awal tidak diisi.
            $pickupOdo = (int) ($rental->handoverOut?->odometer ?? $rental->vehicle?->mileage ?? 0);
            $deposit = (float) ($rental->deposit_amount ?? 0);

            $validated = $request->validate([
                'return_date' => ['required', 'date', function ($attr, $val, $fail) use ($rental) {
                    if (Carbon::parse($val)->lt(Carbon::parse($rental->rental_start_date))) {
                        $fail('Tanggal pengembalian tidak boleh sebelum awal sewa.');
                    }
                }],
                'return_mileage' => 'nullable|integer|min:'.$pickupOdo,
                'fuel_level' => 'nullable|in:full,three_quarter,half,quarter,empty',
                'vehicle_condition' => 'required|in:excellent,good,fair,damaged',
                'damage_description' => 'nullable|string|max:1000',
                'extra_charge' => 'nullable|numeric|min:0|max:999999999',
                'deposit_refund' => ['nullable', 'numeric', 'min:0', 'max:999999999', function ($attr, $val, $fail) use ($deposit) {
                    if ((float) $val > $deposit + 0.01) {
                        $fail('Refund deposit (Rp '.number_format((float) $val, 0, ',', '.').') melebihi deposit yang dipegang (Rp '.number_format($deposit, 0, ',', '.').').');
                    }
                }],
            ], [
                'return_mileage.min' => 'Odometer pengembalian harus >= odometer saat berangkat (:min km). Nilai yang dimasukkan: :input.',
            ]);

            app(\App\Services\AccountingService::class)->assertPeriodOpen($validated['return_date']);

            DB::beginTransaction();
            $inTx = true;

            $return = ReturnCar::create(array_merge($validated, ['rental_id' => $rental->rental_id]));

            $rental->update([
                'status' => 'completed',
                'actual_return_date' => $validated['return_date'],
            ]);

            // §3.4 audit sewa: kendaraan rusak → maintenance (tidak langsung disewakan lagi)
            if ($rental->vehicle) {
                $vehicleStatus = in_array($validated['vehicle_condition'], ['damaged', 'fair'], true) ? 'maintenance' : 'available';
                $rental->vehicle->update([
                    'status' => $vehicleStatus,
                    'mileage' => $validated['return_mileage'] ?? $rental->vehicle->mileage,
                ]);
            }

            // Auto jurnal untuk extra charge (pendapatan tambahan) — FASE 2-10: hard post,
            // gagal jurnal = rollback transaksi (integritas operasional ↔ akuntansi)
            if (! empty($validated['extra_charge']) && $validated['extra_charge'] > 0) {
                app(\App\Services\AccountingService::class)->post($validated['return_date'], 'RET-'.$rental->rental_code, 'Extra charge pengembalian '.$rental->rental_code, 'rental', [
                    ['account' => $this->resolveCoa('1-2100'), 'debit' => $validated['extra_charge'], 'credit' => 0],
                    ['account' => $this->resolveCoa('4-1200'), 'debit' => 0, 'credit' => $validated['extra_charge']],
                ]);
            }

            DB::commit();
            $inTx = false;

            // Notifikasi WA pengembalian
            try { $this->sendRentalWA($rental, 'Pengembalian Selesai', 'Kendaraan *'.$rental->vehicle->license_plate.'* telah dikembalikan. Terima kasih!'); } catch (\Throwable $e) {}

            return redirect()->route('rental.show', $rental->rental_id)
                ->withSuccess('Pengembalian berhasil diproses.');
        } catch (ValidationException $e) {
            if ($inTx) {
                DB::rollback();
            }
            throw $e;
        } catch (Exception $e) {
            if ($inTx) {
                DB::rollback();
            }
            return redirect()->back()->withInput()->withErrors($e->getMessage());
        }
    }

    public function fineStore(Request $request, $rental)
    {
        try {
            $rental = Rental::findOrFail($rental);

            $validated = $request->validate([
                // §3.6 audit sewa: fine_type harus enum DB
                'fine_type' => 'required|in:late_return,damage,cleaning,fuel,lost_item,other',
                'description' => 'required|string|max:500',
                'amount' => 'required|numeric|min:0|max:999999999',
                'notes' => 'nullable|string|max:500',
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
        } catch (ValidationException $e) {
            throw $e;
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
        $inTx = false;

        try {
            $rental = Rental::findOrFail($rental);

            if (Invoice::where('rental_id', $rental->rental_id)->exists()) {
                return redirect()->route('rental.show', $rental->rental_id)
                    ->withErrors('Invoice untuk sewa ini sudah ada.');
            }

            $validated = $request->validate([
                // §3.6 audit sewa: jatuh tempo minimal hari ini
                'due_date' => 'required|date|after_or_equal:today',
                'notes' => 'nullable|string|max:500',
            ]);

            app(\App\Services\AccountingService::class)->assertPeriodOpen(now()->toDateString());

            DB::beginTransaction();
            $inTx = true;

            $invoiceNumber = $this->gen_number(Invoice::class, 'invoice_number', 'INV-$/#####', now(), 'created_at', true);

            // FASE 1-6 audit sewa: sub_total = komponen kotor SEBELUM diskon (base+asuransi+sopir+
            // young driver+add-on) supaya tidak double diskon saat dirender (sub - disc + tax = total)
            $subTotal = (float) $rental->total_base_price
                + (float) ($rental->insurance_fee ?? 0)
                + (float) ($rental->driver_fee ?? 0)
                + (float) ($rental->young_driver_fee ?? 0)
                + $this->addonsTotalFor($rental->rental_id);

            Invoice::create([
                'rental_id' => $rental->rental_id,
                'invoice_number' => $invoiceNumber,
                'issue_date' => now(),
                'due_date' => $validated['due_date'],
                'sub_total' => $subTotal,
                'tax' => $rental->tax_amount ?? 0,
                'discount' => $rental->discount_amount ?? 0,
                'total_amount' => $rental->total_amount,
                'paid_amount' => $rental->payments()->where('status', 'completed')->sum('amount'),
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
            ]);

            DB::commit();
            $inTx = false;

            try { $this->sendRentalWA($rental, 'Invoice Terbit', 'Invoice *'.$invoiceNumber.'* untuk sewa *'.$rental->rental_code.'* telah terbit. Jatuh tempo: '.Carbon::parse($validated['due_date'])->format('d M Y')); } catch (\Throwable $e) {}

            return redirect()->route('finance.index')->withSuccess('Invoice berhasil dibuat.');
        } catch (ValidationException $e) {
            if ($inTx) {
                DB::rollback();
            }
            throw $e;
        } catch (Exception $e) {
            if ($inTx) {
                DB::rollback();
            }
            return redirect()->back()->withInput()->withErrors($e->getMessage());
        }
    }

    public function invoicePrint($rental)
    {
        // FASE 3 tech-debt: render PDF invoice langsung (dulu cuma redirect ke finance).
        $invoice = Invoice::with([
            'rental.customer',
            'rental.vehicle.model.brand',
            'rental.driver',
            'rental.details',
            'rental.pickupLocation',
            'rental.returnLocation',
            'payments',
        ])->where('rental_id', $rental)->firstOrFail();

        return \App\Support\PdfDocument::download(
            'finance.invoice.print',
            ['item' => $invoice, 'settings' => \App\Support\AppSettings::all()],
            'Invoice-' . ($invoice->invoice_number ?? $invoice->invoice_id)
        );
    }

    public function paymentStore(Request $request, $rental)
    {
        $inTx = false;

        try {
            $rental = Rental::lockForUpdate()->findOrFail($rental);

            // FASE 1-2 audit sewa: enum DB = cash|bank_transfer|...; normalize alias lama 'transfer'
            $methodAliases = ['transfer' => 'bank_transfer'];
            $incomingMethod = (string) $request->input('payment_method', '');
            $request->merge(['payment_method' => $methodAliases[$incomingMethod] ?? $incomingMethod]);

            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01|max:99999999999',
                'payment_method' => 'required|in:cash,bank_transfer,credit_card,debit_card,e_wallet,other',
                'reference_number' => 'nullable|string|max:100',
                'notes' => 'nullable|string|max:500',
            ]);

            app(\App\Services\AccountingService::class)->assertPeriodOpen(now()->toDateString());

            DB::beginTransaction();
            $inTx = true;

            // FASE 1-4 audit sewa: lock invoice + total pembayaran yang sudah tercatat
            $invoice = Invoice::where('rental_id', $rental->rental_id)->lockForUpdate()->first();
            $alreadyPaid = (float) Payment::where('rental_id', $rental->rental_id)
                ->where('status', 'completed')
                ->sum('amount');
            $newlyPaid = $alreadyPaid + (float) $validated['amount'];
            $billTotal = (float) ($rental->total_amount ?? 0);

            // Cegah overpay terhadap tagihan
            if ($billTotal > 0 && $newlyPaid > $billTotal + 0.01) {
                throw new Exception(sprintf(
                    'Pembayaran melebihi sisa tagihan. Sisa: Rp %s.',
                    number_format(max(0, $billTotal - $alreadyPaid), 0, ',', '.')
                ));
            }

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
                $invoicePaid = (float) $invoice->paid_amount + (float) $validated['amount'];
                $invoice->update([
                    'paid_amount' => $invoicePaid,
                    'status' => $invoicePaid >= (float) $invoice->total_amount - 0.01 ? 'paid' : 'partially_paid',
                ]);
            }

            // §3.6 audit sewa: partial vs paid (enum tr_rental: unpaid|partial|paid|refunded)
            $rental->update([
                'payment_status' => ($billTotal > 0 && $newlyPaid >= $billTotal - 0.01) ? 'paid' : 'partial',
            ]);

            // Auto jurnal: Dr Kas/Bank, Cr Piutang — FASE 2-10: hard post (gagal = rollback)
            $cashAccount = $validated['payment_method'] === 'cash' ? $this->resolveCoa('1-1100') : $this->resolveCoa('1-1200');
            app(\App\Services\AccountingService::class)->post(now()->toDateString(), 'PAY-'.$payment->payment_id, 'Pembayaran sewa '.$rental->rental_code, 'payment', [
                ['account' => $cashAccount, 'debit' => $validated['amount'], 'credit' => 0],
                ['account' => $this->resolveCoa('1-2100'), 'debit' => 0, 'credit' => $validated['amount']],
            ]);

            DB::commit();
            $inTx = false;

            try { $this->sendRentalWA($rental, 'Pembayaran Diterima', 'Pembayaran *Rp '.number_format($validated['amount'],0,',','.').'* untuk sewa *'.$rental->rental_code.'* telah diterima. Terima kasih!'); } catch (\Throwable $e) {}

            return response()->json(['status' => true, 'msg' => 'Pembayaran berhasil dicatat.', 'data' => $payment]);
        } catch (ValidationException $e) {
            if ($inTx) {
                DB::rollback();
            }
            throw $e;
        } catch (Exception $e) {
            if ($inTx) {
                DB::rollback();
            }
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function refundStore(Request $request, $rental)
    {
        $inTx = false;

        try {
            $rental = Rental::findOrFail($rental);

            // FASE 1-2 audit sewa: enum DB = deposit_return|overpayment|cancellation|damage_deposit
            $typeAliases = ['deposit' => 'deposit_return', 'cancelled' => 'cancellation', 'canceled' => 'cancellation'];
            $incomingType = (string) $request->input('refund_type', '');
            $request->merge(['refund_type' => $typeAliases[$incomingType] ?? $incomingType]);

            $validated = $request->validate([
                'payment_id' => 'required|exists:tr_payment,payment_id',
                'amount' => 'required|numeric|min:0.01',
                'refund_type' => 'required|in:deposit_return,overpayment,cancellation,damage_deposit',
                'notes' => 'nullable|string|max:500',
            ]);

            app(\App\Services\AccountingService::class)->assertPeriodOpen(now()->toDateString());

            DB::beginTransaction();
            $inTx = true;

            // §3.6 audit sewa: refund tidak boleh melebihi pembayaran (dikurangi refund sebelumnya)
            $payment = Payment::where('rental_id', $rental->rental_id)->lockForUpdate()->findOrFail($validated['payment_id']);
            $alreadyRefunded = (float) Refund::where('payment_id', $payment->payment_id)
                ->where('status', '!=', 'failed')
                ->sum('amount');
            if ($alreadyRefunded + (float) $validated['amount'] > (float) $payment->amount + 0.01) {
                throw new Exception(sprintf(
                    'Refund melebihi nilai pembayaran. Sisa dapat direfund: Rp %s.',
                    number_format(max(0, (float) $payment->amount - $alreadyRefunded), 0, ',', '.')
                ));
            }

            $refund = Refund::create([
                'payment_id' => $validated['payment_id'],
                'rental_id' => $rental->rental_id,
                'refund_date' => now(),
                'amount' => $validated['amount'],
                'refund_type' => $validated['refund_type'],
                'status' => 'processed',
                'notes' => $validated['notes'] ?? null,
            ]);

            // Payment dianggap refunded bila seluruh nilainya sudah kembali
            if ($alreadyRefunded + (float) $validated['amount'] >= (float) $payment->amount - 0.01) {
                $payment->update(['status' => 'refunded']);
            }

            DB::commit();
            $inTx = false;

            return response()->json(['status' => true, 'msg' => 'Refund berhasil dicatat.', 'data' => $refund]);
        } catch (ValidationException $e) {
            if ($inTx) {
                DB::rollback();
            }
            throw $e;
        } catch (Exception $e) {
            if ($inTx) {
                DB::rollback();
            }
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function handoverForm($rental, $type)
    {
        $rental = $this->model->with(['customer', 'vehicle.model.brand', 'inspections'])->findOrFail($rental);
        $isIn = $type === 'in';
        $inspection = $rental->inspections()->where('inspection_type', $isIn ? 'handover_in' : 'handover_out')->latest()->first();

        // FASE: inspeksi AWAL (handover_out) di-prefill dari kondisi kendaraan terakhir
        // (inspeksi sewa sebelumnya) bila sewa ini belum punya catatan keluar.
        $inherited = null;
        if (! $inspection && ! $isIn) {
            $inherited = RentalInspection::where('vehicle_id', $rental->vehicle_id)
                ->where('rental_id', '!=', $rental->rental_id)
                ->latest('created_at')
                ->first();
        }
        $seed = $inspection ?: $inherited;

        return view('rental.handover')->with([
            'title' => $type === 'out' ? 'Inspeksi Serah Terima Awal' : 'Inspeksi Pengembalian',
            'subtitle' => $rental->rental_code.' - '.($type === 'out' ? 'Checklist Awal' : 'Checklist Akhir'),
            'rental' => $rental,
            'type' => $type,
            'inspection' => $inspection,
            'seed' => $seed,
            'inherited' => $inherited,
        ]);
    }

    public function handoverStore(Request $request, $rental, $type)
    {
        try {
            $rental = Rental::findOrFail($rental);

            $isIn = $type === 'in';
            $handoverOut = $rental->handoverOut;

            // §3.7 audit sewa: inspeksi akhir butuh inspeksi awal; odometer tidak boleh turun
            if ($isIn && ! $handoverOut) {
                return redirect()->back()->withErrors('Isi inspeksi serah terima AWAL (handover_out) terlebih dahulu sebelum inspeksi pengembalian.');
            }

            $validated = $request->validate([
                'odometer' => ['nullable', 'integer', 'min:0', function ($attr, $val, $fail) use ($isIn, $handoverOut) {
                    if ($isIn && $handoverOut?->odometer !== null && $val !== null && (int) $val < (int) $handoverOut->odometer) {
                        $fail('Odometer pengembalian harus >= odometer serah terima awal ('.$handoverOut->odometer.' km).');
                    }
                }],
                'fuel_level' => 'nullable|in:full,three_quarter,half,quarter,empty',
                'body_damage_points' => 'nullable|json',
                'checklist' => 'nullable|array',
                'exterior_notes' => 'nullable|string|max:1000',
                'interior_notes' => 'nullable|string|max:1000',
                'notes' => 'nullable|string|max:1000',
            ]);

            // §3.7 audit sewa: validasi eksplisit struktur damage points (bukan null diam-diam)
            $damagePoints = null;
            if (! empty($validated['body_damage_points'])) {
                $damagePoints = json_decode($validated['body_damage_points'], true);
                if (! is_array($damagePoints)) {
                    throw new Exception('Format titik kerusakan bodi tidak valid (harus array JSON).');
                }
            }

            // §3.7 audit sewa: satu inspeksi per jenis per sewa — updateOrCreate mencegah duplikat
            $inspection = RentalInspection::updateOrCreate(
                [
                    'rental_id' => $rental->rental_id,
                    'inspection_type' => $isIn ? 'handover_in' : 'handover_out',
                ],
                [
                    'vehicle_id' => $rental->vehicle_id,
                    'odometer' => $validated['odometer'] ?? null,
                    'fuel_level' => $validated['fuel_level'] ?? null,
                    'body_damage_points' => $damagePoints,
                    'checklist' => $validated['checklist'] ?? null,
                    'exterior_notes' => $validated['exterior_notes'] ?? null,
                    'interior_notes' => $validated['interior_notes'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => auth()->id(),
                ]
            );

            // Sinkronkan mileage kendaraan dari inspeksi awal
            if (! $isIn && ! empty($validated['odometer'])) {
                $rental->vehicle?->update(['mileage' => max((int) $rental->vehicle->mileage, (int) $validated['odometer'])]);
            }

            return redirect()->route('rental.show', $rental->rental_id)->withSuccess('Inspeksi '.($type === 'out' ? 'awal' : 'akhir').' berhasil disimpan.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            return redirect()->back()->withInput()->withErrors($e->getMessage());
        }
    }

    private function resolveCoa(string $code): ?int
    {
        return app(\App\Services\AccountingService::class)->resolveCoa($code);
    }

    private function sendRentalWA(Rental $rental, string $subject, string $text): void
    {
        if (empty($rental->customer?->phone)) {
            return;
        }

        // FASE 3 audit sewa: via queue + retry, kegagalan tercatat (bukan silent swallow).
        // Dispatch di luar transaksi (pemanggil sudah commit lebih dulu).
        \App\Jobs\SendWhatsAppNotification::dispatch(
            $rental->customer->phone,
            config('app.name').' — '.$subject,
            $text
        );
    }
}
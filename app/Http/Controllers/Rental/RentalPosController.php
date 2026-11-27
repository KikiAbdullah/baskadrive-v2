<?php

namespace App\Http\Controllers\Rental;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Traits\NumberTrait;
use App\Jobs\SendWhatsAppNotification;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Location;
use App\Models\Promo;
use App\Models\Rental;
use App\Models\UserLog;
use App\Models\Vehicle;
use App\Services\RentalPricing;
use App\Support\AppSettings;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * POS Booking — kasir sewa satu layar (hasil AUDIT_BOOKING.md §5).
 *
 * Prinsip: tanggal dulu → unit bebas → struk live → 1 klik simpan.
 * Semua harga via RentalPricing (sumber tunggal, identik dengan mobile).
 * Transaksi + row-lock + buffer 3 jam (paritas RentalController::store).
 */
class RentalPosController extends ApiController
{
    use NumberTrait;

    private const OVERLAP_BUFFER_HOURS = 3;

    public function __construct()
    {
        $this->middleware(['auth', 'can:rental_add'])->only(['index', 'store']);
        $this->middleware(['auth', 'can:rental_view'])->except(['index', 'store']);
    }

    public function index()
    {
        return view('rental.pos', [
            'title' => 'Booking POS',
            'subtitle' => 'Kasir Sewa Satu Layar',
        ]);
    }

    /** GET /rental/pos/options — referensi POS. */
    public function options(): JsonResponse
    {
        $settings = AppSettings::all();

        return response()->json(responseSuccess([
            'locations' => Location::where('is_active', true)
                ->orderBy('location_name')
                ->get(['location_id', 'location_name', 'city']),
            'promos' => Promo::where('is_active', true)
                ->orderBy('promo_code')
                ->get(['promo_id', 'promo_code', 'description', 'discount_type', 'discount_value', 'min_rental_days']),
            'drivers' => Driver::where('is_active', true)
                ->orderBy('first_name')
                ->limit(50)
                ->get(['driver_id', 'first_name', 'last_name', 'phone'])
                ->map(fn (Driver $d) => [
                    'driver_id' => (int) $d->driver_id,
                    'name' => trim($d->first_name.' '.($d->last_name ?? '')),
                    'phone' => $d->phone,
                ]),
            'tax_percent' => RentalPricing::defaultTaxPercent($settings),
            'deposit_enabled' => (bool) ($settings['deposit_enabled'] ?? true),
        ], 'Opsi POS'));
    }

    /** GET /rental/pos/customers?q= — cari pelanggan (nama/HP/KTP/korporat). */
    public function customers(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(responseSuccess(['customers' => []], 'Kosong'));
        }

        $list = Customer::query()
            ->where('is_blacklisted', false)
            ->where(function ($w) use ($q) {
                $w->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('id_card_number', 'like', "%{$q}%")
                    ->orWhere('company_name', 'like', "%{$q}%");
            })
            ->orderBy('first_name')
            ->limit(10)
            ->get()
            ->map(fn (Customer $c) => $this->customerRow($c));

        return response()->json(responseSuccess(['customers' => $list], 'Hasil pencarian'));
    }

    /**
     * POST /rental/pos/customers — pelanggan walk-in cepat.
     * Nama + HP wajib; sisanya opsional → pelanggan individual baru.
     */
    public function storeCustomer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'id_card_number' => ['nullable', 'string', 'max:30'],
        ], [
            'name.required' => 'Nama pelanggan wajib diisi.',
            'phone.required' => 'Nomor HP wajib diisi.',
        ]);

        $customer = Customer::create([
            'customer_type' => 'individual',
            'first_name' => $data['name'],
            'last_name' => '',
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'id_card_number' => $data['id_card_number'] ?? null,
            'is_verified' => false,
            'is_blacklisted' => false,
        ]);

        $this->logUser('create', 'customer', "Walk-in POS: pelanggan #{$customer->customer_id} {$customer->first_name}");

        return response()->json(responseSuccess([
            'customer' => $this->customerRow($customer->fresh()),
        ], 'Pelanggan walk-in dibuat'));
    }

    /** GET /rental/pos/vehicles?start=&end=&q= — unit bebas untuk rentang. */
    public function vehicles(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'q' => ['nullable', 'string', 'max:30'],
        ]);

        [$start, $end] = $this->parseRange($data['start'], $data['end']);
        $vehicles = $this->freeVehicles($start, $end, $data['q'] ?? null);

        return response()->json(responseSuccess(['vehicles' => $vehicles], 'Unit tersedia'));
    }

    /**
     * POST /rental/pos/quote — struk hidup (stateless, tanpa session).
     * Promo divalidasi usable; gugur → promo_applied=false + promo_note.
     */
    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:m_vehicle,vehicle_id'],
            'customer_id' => ['nullable', 'integer', 'exists:m_customer,customer_id'],
            'rental_start_date' => ['required', 'date'],
            'rental_end_date' => ['required', 'date', 'after:rental_start_date'],
            'is_with_driver' => ['nullable', 'boolean'],
            'driver_daily_fee' => ['nullable', 'numeric', 'min:0', 'max:2000000'],
            'promo_id' => ['nullable', 'integer'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $vehicle = Vehicle::with('model')->findOrFail($data['vehicle_id']);
        $customer = ! empty($data['customer_id']) ? Customer::find($data['customer_id']) : null;
        [$start, $end] = $this->parseRange($data['rental_start_date'], $data['rental_end_date']);
        $rentalDays = $this->rentalDays($start, $end);

        $settings = AppSettings::all();
        $promo = null;
        $promoNote = null;
        if (! empty($data['promo_id'])) {
            $promo = $this->usablePromo((int) $data['promo_id'], $vehicle, $rentalDays, lock: false);
            if (! $promo) {
                $promoNote = 'Promo tidak dapat dipakai (kedaluwarsa/kuota habis/syarat hari/kategori).';
            }
        }

        $pricing = RentalPricing::quote(
            $vehicle,
            $customer,
            $settings,
            $rentalDays,
            (bool) ($data['is_with_driver'] ?? false),
            isset($data['driver_daily_fee']) ? (float) $data['driver_daily_fee'] : null,
            $promo,
            [],
            isset($data['tax_percent']) ? (float) $data['tax_percent'] : null,
        );
        $pricing['promo_applied'] = $promo !== null;
        $pricing['promo_note'] = $promoNote;
        $pricing['rental_days'] = $rentalDays;

        return response()->json(responseSuccess($pricing, 'Struk dihitung'));
    }

    /**
     * POST /rental/pos/store — simpan booking 1 klik.
     * Paritas penuh RentalController::store: tx + row-lock unit + buffer,
     * kode RNT, kuota promo dalam transaksi, unit → reserved, WA queue.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:m_customer,customer_id'],
            'vehicle_id' => ['required', 'integer', 'exists:m_vehicle,vehicle_id'],
            'rental_start_date' => ['required', 'date', 'after_or_equal:today'],
            'rental_end_date' => ['required', 'date', 'after:rental_start_date'],
            'pickup_location_id' => ['required', 'integer', 'exists:m_location,location_id'],
            'return_location_id' => ['required', 'integer', 'exists:m_location,location_id'],
            'is_with_driver' => ['nullable', 'boolean'],
            'driver_id' => ['nullable', 'integer', 'exists:m_driver,driver_id'],
            'driver_daily_fee' => ['nullable', 'numeric', 'min:0', 'max:2000000'],
            'promo_id' => ['nullable', 'integer'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        // Validasi bisnis (blacklist, konflik unit) → 422 JSON, bukan 500.
        try {
            return $this->storeInTransaction($request, $data);
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if ($e instanceof Exception && ! str_contains($msg, 'SQLSTATE') && ! str_contains($msg, 'route(')) {
                return response()->json(responseFailed($msg), 422);
            }
            throw $e;
        }
    }

    private function storeInTransaction(Request $request, array $data): JsonResponse
    {
        DB::beginTransaction();

        try {
            $settings = AppSettings::all();

            $vehicle = Vehicle::with('model')->where('vehicle_id', $data['vehicle_id'])->lockForUpdate()->firstOrFail();
            $customer = Customer::findOrFail($data['customer_id']);
            if ($customer->is_blacklisted) {
                throw new Exception('Pelanggan diblacklist: '.($customer->blacklist_reason ?? 'tanpa alasan').'. Tidak dapat membuat sewa.');
            }

            [$start, $end] = $this->parseRange($data['rental_start_date'], $data['rental_end_date']);
            $rentalDays = $this->rentalDays($start, $end);

            // Anti double-booking (paritas wizard): row-lock + buffer 3 jam.
            $this->assertVehicleFree((int) $vehicle->vehicle_id, $start, $end);

            $promo = null;
            if (! empty($data['promo_id'])) {
                $promo = $this->usablePromo((int) $data['promo_id'], $vehicle, $rentalDays, lock: true);
            }

            $pricing = RentalPricing::quote(
                $vehicle,
                $customer,
                $settings,
                $rentalDays,
                (bool) ($data['is_with_driver'] ?? false),
                isset($data['driver_daily_fee']) ? (float) $data['driver_daily_fee'] : null,
                $promo,
                [],
                isset($data['tax_percent']) ? (float) $data['tax_percent'] : null,
            );

            $rental = Rental::create([
                'rental_code' => $this->gen_number(new Rental, 'rental_code', 'RNT-$/#####', now(), 'created_at', true),
                'customer_id' => $customer->customer_id,
                'vehicle_id' => $vehicle->vehicle_id,
                'employee_id' => auth()->user()->employee_id ?? null,
                'pickup_location_id' => $data['pickup_location_id'],
                'return_location_id' => $data['return_location_id'],
                'promo_id' => $promo?->promo_id,
                'rental_start_date' => $start,
                'rental_end_date' => $end,
                'rental_days' => $rentalDays,
                'is_with_driver' => (bool) ($data['is_with_driver'] ?? false),
                'driver_id' => $data['driver_id'] ?? null,
                'base_rate_per_day' => $pricing['base_rate_per_day'],
                'total_base_price' => $pricing['total_base_price'],
                'insurance_fee' => $pricing['insurance_fee'],
                'driver_fee' => $pricing['driver_fee'],
                'young_driver_fee' => $pricing['young_driver_fee'],
                'discount_amount' => $pricing['discount_amount'],
                'tax_amount' => $pricing['tax_amount'],
                'tax_percent' => $pricing['tax_percent'],
                'deposit_amount' => $pricing['deposit_amount'],
                'total_amount' => $pricing['total_amount'],
                'status' => 'reserved',
                'payment_status' => 'unpaid',
                'notes' => $data['notes'] ?? null,
            ]);

            if ($promo) {
                Promo::where('promo_id', $promo->promo_id)->increment('usage_count');
            }

            $vehicle->update(['status' => 'reserved']);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            if ($e instanceof Exception) {
                throw $e;
            }
            throw new Exception($e->getMessage());
        }

        // Notifikasi WA — di luar transaksi (paritas web).
        try {
            if (! empty($customer->phone)) {
                SendWhatsAppNotification::dispatch(
                    $customer->phone,
                    config('app.name').' — Booking Berhasil',
                    'Sewa *'.$rental->rental_code.'* berhasil dibuat. Periode: '
                        .$start->format('d M Y').' s/d '.$end->format('d M Y'),
                );
            }
        } catch (Throwable) {
        }

        $this->logUser('create', 'rental', "POS booking {$rental->rental_code} — total Rp ".number_format($pricing['total_amount'], 0, ',', '.'));

        return response()->json(responseSuccess([
            'rental_id' => (int) $rental->rental_id,
            'rental_code' => $rental->rental_code,
            'total_amount' => $pricing['total_amount'],
            'redirect_url' => route('rental.show', $rental->rental_id),
        ], "Sewa {$rental->rental_code} berhasil dibuat"));
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    private function customerRow(Customer $c): array
    {
        $name = $c->customer_type === 'corporate'
            ? ($c->company_name ?: trim(($c->first_name ?? '').' '.($c->last_name ?? '')))
            : trim(($c->first_name ?? '').' '.($c->last_name ?? ''));

        return [
            'customer_id' => (int) $c->customer_id,
            'name' => $name,
            'phone' => $c->phone,
            'id_card_number' => $c->id_card_number,
            'label' => $name.' · '.$c->phone,
        ];
    }

    private function parseRange(string $startDate, string $endDate): array
    {
        return [
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->startOfDay(),
        ];
    }

    private function rentalDays(Carbon $start, Carbon $end): int
    {
        return max(1, $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()));
    }

    /** Unit bebas: status available + tidak bentrok sewa aktif + buffer. */
    private function freeVehicles(Carbon $start, Carbon $end, ?string $q): array
    {
        $startMinusBuffer = $start->copy()->subHours(self::OVERLAP_BUFFER_HOURS);

        $busy = Rental::whereIn('status', ['reserved', 'ongoing'])
            ->where('rental_start_date', '<=', $end)
            ->where('rental_end_date', '>=', $startMinusBuffer)
            ->pluck('vehicle_id');

        $query = Vehicle::with(['model.brand', 'location'])
            ->where('status', 'available')
            ->whereNotIn('vehicle_id', $busy);

        if ($q) {
            $normalized = str_replace(' ', '', strtoupper($q));
            $query->where(function ($w) use ($normalized, $q) {
                $w->whereRaw('REPLACE(UPPER(license_plate), " ", "") LIKE ?', ["%{$normalized}%"])
                    ->orWhereHas('model', fn ($m) => $m->where('model_name', 'like', "%{$q}%"));
            });
        }

        return $query->orderBy('license_plate')->limit(30)->get()
            ->map(fn (Vehicle $v) => [
                'vehicle_id' => (int) $v->vehicle_id,
                'plate' => $v->license_plate,
                'model' => trim(($v->model?->brand?->brand_name ?? '').' '.($v->model?->model_name ?? '')),
                'category' => $v->model?->category,
                'year' => $v->year,
                'color' => $v->color,
                'location' => $v->location?->location_name,
                'base_rate_per_day' => (float) $v->model?->base_price_per_day,
                'deposit_amount' => (float) $v->model?->deposit_amount,
            ])
            ->values()
            ->all();
    }

    private function usablePromo(int $promoId, Vehicle $vehicle, int $rentalDays, bool $lock = false): ?Promo
    {
        $query = Promo::where('promo_id', $promoId);
        if ($lock) {
            $query->lockForUpdate();
        }
        $promo = $query->first();

        if (! $promo || ! $promo->is_active) {
            return null;
        }
        if ($promo->valid_from && now()->lt($promo->valid_from->copy()->startOfDay())) {
            return null;
        }
        if ($promo->valid_to && now()->gt($promo->valid_to->copy()->endOfDay())) {
            return null;
        }
        if ($promo->min_rental_days && $rentalDays < (int) $promo->min_rental_days) {
            return null;
        }
        $allowed = array_map('strtolower', (array) $promo->applicable_categories);
        if ($allowed !== []) {
            $cat = strtolower($vehicle->model->category ?? '');
            if (! in_array($cat, $allowed, true)) {
                return null;
            }
        }
        if ($promo->max_usage !== null && (int) $promo->usage_count >= (int) $promo->max_usage) {
            return null;
        }

        return $promo;
    }

    private function assertVehicleFree(int $vehicleId, Carbon $start, Carbon $end): void
    {
        $startMinusBuffer = $start->copy()->subHours(self::OVERLAP_BUFFER_HOURS);

        $conflict = Rental::query()
            ->where('vehicle_id', $vehicleId)
            ->whereIn('status', ['reserved', 'ongoing'])
            ->where('rental_start_date', '<=', $end)
            ->where('rental_end_date', '>=', $startMinusBuffer)
            ->lockForUpdate()
            ->exists();

        if ($conflict) {
            throw new Exception('Unit sudah dipesan pada rentang tanggal tersebut (termasuk buffer pembersihan '.self::OVERLAP_BUFFER_HOURS.' jam).');
        }
    }

    private function logUser(string $action, string $menu, string $message): void
    {
        try {
            UserLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'menu' => $menu,
                'message' => $message,
            ]);
        } catch (Throwable) {
        }
    }
}

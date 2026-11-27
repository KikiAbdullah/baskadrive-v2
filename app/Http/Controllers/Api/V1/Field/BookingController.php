<?php

namespace App\Http\Controllers\Api\V1\Field;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\V1\Field\Concerns\InteractsWithFieldApi;
use App\Http\Controllers\Traits\NumberTrait;
use App\Jobs\SendWhatsAppNotification;
use App\Models\ApiIdempotencyKey;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Promo;
use App\Models\Rental;
use App\Models\RentalDetail;
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
 * Booking oleh operator (konsep F-01 — "pembuatan sewa" dibuka terbatas untuk
 * mobile): operator lapangan dapat membuat reservasi sewa baru langsung dari
 * aplikasi, dengan harga & aturan **satu sumber kebenaran** yang sama dengan
 * wizard web (RentalController::store):
 * - Anti double-booking + buffer pembersihan 3 jam (enforcement backend).
 * - Harga: base × hari, asuransi, sopir harian, young-driver, addon, promo, PPN.
 * - Promo divalidasi utuh (aktif, masa berlaku, min hari, kategori, kuota).
 * - Unit langsung `reserved`, audit user_logs, idempotensi client_uuid.
 *
 * Endpoint:
 * - GET  /booking/options — referensi (cabang, pelanggan, promo aktif)
 * - POST /booking         — buat booking (idempotent via client_uuid)
 * - GET  /booking         — riwayat booking yang dibuat dari mobile (operator ini)
 */
class BookingController extends ApiController
{
    use InteractsWithFieldApi;
    use NumberTrait;

    /** Buffer pembersihan antar-sewa — sama dengan RentalController. */
    private const OVERLAP_BUFFER_HOURS = 3;

    // ------------------------------------------------------------------
    // GET /booking/options
    // ------------------------------------------------------------------

    public function options(): JsonResponse
    {
        $locations = Location::where('is_active', true)
            ->orderBy('location_name')
            ->get(['location_id', 'location_name', 'city', 'address']);

        $customers = Customer::query()
            ->where('is_blacklisted', false)
            ->orderBy('first_name')
            ->limit(200)
            ->get([
                'customer_id', 'customer_type', 'first_name', 'last_name',
                'company_name', 'phone', 'id_card_number', 'date_of_birth',
            ])
            ->map(fn (Customer $c) => [
                'customer_id' => (int) $c->customer_id,
                'name' => $c->customer_type === 'corporate'
                    ? ($c->company_name ?? trim(($c->first_name ?? '').' '.($c->last_name ?? '')))
                    : trim(($c->first_name ?? '').' '.($c->last_name ?? '')),
                'phone' => $c->phone,
                'id_card_number' => $c->id_card_number,
                'date_of_birth' => $c->date_of_birth?->toDateString(),
            ]);

        $promos = Promo::where('is_active', true)
            ->orderBy('promo_code')
            ->get(['promo_id', 'promo_code', 'description', 'discount_type', 'discount_value', 'min_rental_days'])
            ->map(fn (Promo $p) => [
                'promo_code' => $p->promo_code,
                'description' => $p->description,
                'discount_type' => $p->discount_type,
                'discount_value' => (float) $p->discount_value,
                'min_rental_days' => (int) $p->min_rental_days,
            ]);

        return $this->ok([
            'locations' => $locations,
            'customers' => $customers,
            'promos' => $promos,
        ], 'Referensi booking');
    }

    // ------------------------------------------------------------------
    // POST /booking
    // ------------------------------------------------------------------

    public function store(Request $request): JsonResponse
    {
        // Validasi bentuk dulu (422 field-level), tulis idempotent setelahnya.
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:m_customer,customer_id'],
            'vehicle_id' => ['required', 'integer', 'exists:m_vehicle,vehicle_id'],
            'pickup_location_id' => ['required', 'integer', 'exists:m_location,location_id'],
            'return_location_id' => ['required', 'integer', 'exists:m_location,location_id'],
            'rental_start_date' => ['required', 'date', 'after_or_equal:today'],
            'rental_end_date' => ['required', 'date', 'after:rental_start_date'],
            'is_with_driver' => ['nullable', 'boolean'],
            'promo_code' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
            'addons' => ['nullable', 'array', 'max:10'],
            'addons.*.item_name' => ['required_with:addons', 'string', 'max:100'],
            'addons.*.quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'addons.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ], [
            'customer_id.required' => 'Pelanggan wajib dipilih.',
            'vehicle_id.required' => 'Unit wajib dipilih.',
            'pickup_location_id.required' => 'Lokasi penjemputan wajib dipilih.',
            'return_location_id.required' => 'Lokasi pengembalian wajib dipilih.',
            'rental_start_date.after_or_equal' => 'Tanggal mulai tidak boleh lampau.',
            'rental_end_date.after' => 'Tanggal selesai harus setelah tanggal mulai.',
        ]);

        return $this->idempotent($request, 'booking.store', function () use ($request, $data) {
            $result = $this->createBooking($request, $data);

            $this->audit(
                $request,
                'create',
                'rental',
                "Booking mobile {$result['rental_code']} — total Rp ".number_format($result['total_amount'], 0, ',', '.'),
            );

            return $result;
        });
    }

    // ------------------------------------------------------------------
    // GET /booking/vehicles?rental_start_date=&rental_end_date=&q=
    // ------------------------------------------------------------------

    /** Unit yang bebas untuk rentang tanggal (status available + tidak bentrok + buffer). */
    public function vehicles(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rental_start_date' => ['required', 'date'],
            'rental_end_date' => ['required', 'date', 'after:rental_start_date'],
            'q' => ['nullable', 'string', 'max:30'],
        ]);

        $start = Carbon::parse($data['rental_start_date'])->startOfDay();
        $end = Carbon::parse($data['rental_end_date'])->startOfDay();
        $startMinusBuffer = $start->copy()->subHours(self::OVERLAP_BUFFER_HOURS);

        $busyVehicleIds = Rental::whereIn('status', ['reserved', 'ongoing'])
            ->where('rental_start_date', '<=', $end)
            ->where('rental_end_date', '>=', $startMinusBuffer)
            ->pluck('vehicle_id');

        $query = Vehicle::with(['model.brand', 'location'])
            ->where('status', 'available')
            ->whereNotIn('vehicle_id', $busyVehicleIds);

        if (! empty($data['q'])) {
            $normalized = str_replace(' ', '', strtoupper($data['q']));
            $query->where(function ($w) use ($normalized, $data) {
                $w->whereRaw('REPLACE(UPPER(license_plate), " ", "") LIKE ?', ["%{$normalized}%"])
                    ->orWhereHas('model', fn ($m) => $m->where('model_name', 'like', "%{$data['q']}%"));
            });
        }

        $vehicles = $query->orderBy('license_plate')->limit(50)->get()->map(fn (Vehicle $v) => [
            'vehicle_id' => (int) $v->vehicle_id,
            'plate' => $v->license_plate,
            'model' => trim(($v->model?->brand?->brand_name ?? '').' '.($v->model?->model_name ?? '')),
            'category' => $v->model?->category,
            'year' => $v->year,
            'color' => $v->color,
            'location' => $v->location?->location_name,
            'base_rate_per_day' => (float) $v->model?->base_price_per_day,
            'deposit_amount' => (float) $v->model?->deposit_amount,
        ])->values();

        return $this->ok(['vehicles' => $vehicles], 'Unit tersedia');
    }

    // ------------------------------------------------------------------
    // GET /booking — riwayat booking dari mobile (operator ini)
    // ------------------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        $rows = ApiIdempotencyKey::where('user_id', $request->user()?->id)
            ->where('endpoint', 'booking.store')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (ApiIdempotencyKey $k) => $k->result ?? null)
            ->filter()
            ->values();

        return $this->ok(['bookings' => $rows], 'Riwayat booking mobile');
    }

    // ------------------------------------------------------------------
    // Inti bisnis — paritas penuh dengan RentalController::store (web)
    // ------------------------------------------------------------------

    private function createBooking(Request $request, array $data): array
    {
        DB::beginTransaction();

        try {
            $settings = AppSettings::all();

            $vehicle = Vehicle::with('model.brand')->where('vehicle_id', $data['vehicle_id'])->lockForUpdate()->first();
            if (! $vehicle) {
                throw new Exception('Unit tidak ditemukan.');
            }

            $customer = Customer::find($data['customer_id']);
            if (! $customer) {
                throw new Exception('Pelanggan tidak ditemukan.');
            }
            if ($customer->is_blacklisted) {
                throw new Exception('Pelanggan diblacklist: '.($customer->blacklist_reason ?? 'tanpa alasan').'. Tidak dapat membuat sewa.');
            }

            $startDate = Carbon::parse($data['rental_start_date'])->startOfDay();
            $endDate = Carbon::parse($data['rental_end_date'])->startOfDay();
            $rentalDays = max(1, (int) $startDate->copy()->startOfDay()->diffInDays($endDate->copy()->startOfDay()));

            // FASE 1 audit sewa: enforcement anti double-booking (row-lock + buffer 3 jam).
            $this->assertVehicleFree((int) $vehicle->vehicle_id, $startDate, $endDate);

            $baseRate = (float) $vehicle->model->base_price_per_day;

            // Promo: kode → validasi utuh; gugur = tidak dipakai + catatan.
            $promo = null;
            $promoNote = null;
            $promoId = null;
            if (! empty($data['promo_code'])) {
                $promo = $this->usablePromo($data['promo_code'], $vehicle, $rentalDays);
                if (! $promo) {
                    $promoNote = 'Promo "'.$data['promo_code'].'" tidak dapat dipakai (kedaluwarsa/kuota habis/syarat hari).';
                }
            }

            // Sumber harga TUNGGAL (paritas POS & wizard web) — RentalPricing.
            $pricing = RentalPricing::quote(
                $vehicle,
                $customer,
                $settings,
                $rentalDays,
                (bool) ($data['is_with_driver'] ?? false),
                null,
                $promo,
                (array) ($data['addons'] ?? []),
                null,
            );

            $totalBase = $pricing['total_base_price'];
            $insuranceFee = $pricing['insurance_fee'];
            $driverFee = $pricing['driver_fee'];
            $youngDriverFee = $pricing['young_driver_fee'];
            $addonsTotal = $pricing['addons_total'];
            $discountAmount = $pricing['discount_amount'];
            $taxPercent = $pricing['tax_percent'];
            $taxAmount = $pricing['tax_amount'];
            $depositAmount = $pricing['deposit_amount'];
            $totalAmount = $pricing['total_amount'];

            $rental = Rental::create([
                'rental_code' => $this->gen_number(new Rental, 'rental_code', 'RNT-$/#####', now(), 'created_at', true),
                'customer_id' => $customer->customer_id,
                'vehicle_id' => $vehicle->vehicle_id,
                'employee_id' => $request->user()?->employee_id,
                'pickup_location_id' => $data['pickup_location_id'],
                'return_location_id' => $data['return_location_id'],
                'promo_id' => $promoId,
                'rental_start_date' => $startDate,
                'rental_end_date' => $endDate,
                'rental_days' => $rentalDays,
                'is_with_driver' => (bool) ($data['is_with_driver'] ?? false),
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

            foreach ((array) ($data['addons'] ?? []) as $a) {
                RentalDetail::create([
                    'rental_id' => $rental->rental_id,
                    'item_type' => $a['item_type'] ?? 'other',
                    'item_name' => $a['item_name'],
                    'quantity' => (int) ($a['quantity'] ?? 1),
                    'unit_price' => (float) ($a['unit_price'] ?? 0),
                    'total_price' => (int) ($a['quantity'] ?? 1) * (float) ($a['unit_price'] ?? 0),
                ]);
            }

            if ($promoId) {
                Promo::where('promo_id', $promoId)->increment('usage_count');
            }

            $vehicle->update(['status' => 'reserved']);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        // Notifikasi WA booking berhasil — di luar transaksi (paritas web).
        try {
            if (! empty($customer->phone)) {
                SendWhatsAppNotification::dispatch(
                    $customer->phone,
                    config('app.name').' — Booking Berhasil',
                    'Sewa *'.$rental->rental_code.'* berhasil dibuat. Periode: '.$rental->rental_start_date->format('d M Y').' s/d '.$rental->rental_end_date->format('d M Y'),
                );
            }
        } catch (Throwable) {
            // Notifikasi tidak boleh menggagalkan booking.
        }

        return [
            'rental_id' => (int) $rental->rental_id,
            'rental_code' => $rental->rental_code,
            'status' => $rental->status,
            'payment_status' => $rental->payment_status,
            'customer_name' => $customer->customer_type === 'corporate'
                ? ($customer->company_name ?? trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')))
                : trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')),
            'vehicle_plate' => $vehicle->license_plate,
            'vehicle_model' => trim(($vehicle->model?->brand?->brand_name ?? '').' '.($vehicle->model?->model_name ?? '')),
            'rental_start_date' => $rental->rental_start_date->toDateString(),
            'rental_end_date' => $rental->rental_end_date->toDateString(),
            'rental_days' => $rentalDays,
            'base_rate_per_day' => $baseRate,
            'total_base_price' => $totalBase,
            'insurance_fee' => $insuranceFee,
            'driver_fee' => $driverFee,
            'young_driver_fee' => $youngDriverFee,
            'addons_total' => $addonsTotal,
            'discount_amount' => $discountAmount,
            'promo_applied' => $promo !== null,
            'promo_note' => $promoNote,
            'tax_percent' => $taxPercent,
            'tax_amount' => $taxAmount,
            'deposit_amount' => $depositAmount,
            'total_amount' => $totalAmount,
            'created_at' => $rental->created_at?->toIso8601String(),
        ];
    }

    /** Sama dengan RentalController::assertVehicleFree — anti double-booking + buffer. */
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

    /** Validasi promo utuh — paritas RentalController::usablePromo. */
    private function usablePromo(string $promoCode, Vehicle $vehicle, int $rentalDays): ?Promo
    {
        $promo = Promo::where('promo_code', $promoCode)->lockForUpdate()->first();

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
        if (! $this->promoMatchesCategory($promo, $vehicle)) {
            return null;
        }
        if ($promo->max_usage !== null && (int) $promo->usage_count >= (int) $promo->max_usage) {
            return null;
        }

        return $promo;
    }

    private function promoMatchesCategory(Promo $promo, Vehicle $vehicle): bool
    {
        $allowed = array_map('strtolower', (array) $promo->applicable_categories);
        if ($allowed === []) {
            return true;
        }

        $cat = strtolower($vehicle->model->category ?? '');

        return in_array($cat, $allowed, true);
    }
}

<?php

namespace Tests\Feature\Api;

use App\Models\Rental;
use App\Models\User;
use App\Support\AppSettings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Booking oleh operator dari aplikasi mobile (konsep F-01):
 * - POST /api/v1/field/booking membuat sewa `reserved` dengan harga paritas
 *   wizard web (base, asuransi, sopir, young-driver, PPN, promo).
 * - Anti double-booking: unit bentrok rentang (termasuk buffer) ditolak 422.
 * - Idempotensi client_uuid: kirim ulang → 409 ALREADY_PROCESSED, tanpa duplikat.
 * - Referensi & unit tersedia via GET /booking/options & /booking/vehicles.
 */
class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->operator = User::where('username', 'staff')->first();
    }

    protected function actingAsOperator(): string
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => $this->operator->username,
            'password' => 'staff',
        ]);

        $response->assertOk();

        return 'Bearer '.$response->json('data.access_token');
    }

    /** Unit bebas (available + tidak ada sewa aktif) untuk rentang uji. */
    protected function takeFreeVehicle(): object
    {
        $busy = Rental::whereIn('status', ['reserved', 'ongoing'])->pluck('vehicle_id');

        return \App\Models\Vehicle::with('model')
            ->where('status', 'available')
            ->whereNotIn('vehicle_id', $busy)
            ->firstOrFail();
    }

    protected function bookingPayload(int $vehicleId, int $customerId, array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $customerId,
            'vehicle_id' => $vehicleId,
            'pickup_location_id' => 1,
            'return_location_id' => 1,
            'rental_start_date' => now()->addDay()->toDateString(),
            'rental_end_date' => now()->addDays(3)->toDateString(),
            'client_uuid' => '11111111-1111-4111-8111-111111111111',
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // Tests
    // ------------------------------------------------------------------

    public function test_booking_requires_auth(): void
    {
        $this->postJson('/api/v1/field/booking', [])->assertStatus(401);
    }

    public function test_options_returns_references(): void
    {
        $res = $this->withHeaders(['Authorization' => $this->actingAsOperator()])
            ->getJson('/api/v1/field/booking/options');

        $res->assertOk();
        $data = $res->json('data');
        $this->assertNotEmpty($data['locations']);
        $this->assertNotEmpty($data['customers']);
        $this->assertArrayHasKey('promos', $data);

        // Tidak ada pelanggan blacklisted di referensi.
        $this->assertSame([], array_filter(
            \App\Models\Customer::whereIn('customer_id', array_column($data['customers'], 'customer_id'))
                ->pluck('is_blacklisted')->toArray(),
        ));
    }

    public function test_vehicles_endpoint_excludes_booked_vehicles(): void
    {
        $reserved = Rental::where('status', 'reserved')->whereNotNull('vehicle_id')->firstOrFail();

        $res = $this->withHeaders(['Authorization' => $this->actingAsOperator()])
            ->getJson('/api/v1/field/booking?'.http_build_query([
                'rental_start_date' => $reserved->rental_start_date->toDateString(),
                'rental_end_date' => $reserved->rental_end_date->copy()->addDay()->toDateString(),
            ]));
        $res->assertOk();

        $this->getJson('/api/v1/field/booking/vehicles?'.http_build_query([
            'rental_start_date' => $reserved->rental_start_date->toDateString(),
            'rental_end_date' => $reserved->rental_end_date->copy()->addDay()->toDateString(),
        ]))
            ->assertOk()
            ->assertJsonMissing(['plate' => $reserved->vehicle->license_plate]);
    }

    public function test_operator_can_create_booking_with_parity_pricing(): void
    {
        $vehicle = $this->takeFreeVehicle();
        $customer = \App\Models\Customer::where('is_blacklisted', false)->firstOrFail();
        $settings = AppSettings::all();

        $start = now()->addDay()->startOfDay();
        $days = 2;
        $payload = $this->bookingPayload((int) $vehicle->vehicle_id, (int) $customer->customer_id, [
            'rental_end_date' => $start->copy()->addDays($days)->toDateString(),
            'is_with_driver' => true,
        ]);

        $res = $this->withHeaders(['Authorization' => $this->actingAsOperator()])
            ->postJson('/api/v1/field/booking', $payload);

        $res->assertOk()->assertJsonPath('status', true);
        $data = $res->json('data');
        $this->assertSame('reserved', $data['status']);

        // Harga paritas web: base×hari + asuransi + sopir(harian×hari) + PPN.
        $base = (float) $vehicle->model->base_price_per_day;
        $totalBase = $base * $days;
        $insurance = $totalBase * ((float) $vehicle->model->insurance_rate / 100);
        $driver = ($settings['driver_fee_default'] ?? 0) * $days;
        $subtotal = $totalBase + $insurance + $driver;
        $taxPercent = ($settings['tax_enabled'] ?? true) ? (float) ($settings['tax_percent'] ?? 11) : 0.0;
        $expectedTotal = $subtotal + $subtotal * ($taxPercent / 100);

        $this->assertEqualsWithDelta($expectedTotal, (float) $data['total_amount'], 1.0);
        $this->assertEqualsWithDelta($taxPercent, (float) $data['tax_percent'], 0.01);
        $this->assertSame($days, (int) $data['rental_days']);

        // DB: sewa reserved, unit reserved, tercatat sebagai mobile.
        $rental = Rental::where('rental_code', $data['rental_code'])->firstOrFail();
        $this->assertSame('reserved', $rental->status);
        $this->assertSame('unpaid', $rental->payment_status);
        $this->assertEqualsWithDelta($expectedTotal, (float) $rental->total_amount, 1.0);
        $this->assertDatabaseHas('m_vehicle', [
            'vehicle_id' => $vehicle->vehicle_id,
            'status' => 'reserved',
        ]);
        $this->assertDatabaseHas('user_logs', [
            'user_id' => $this->operator->id,
            'menu' => 'rental',
        ]);
    }

    public function test_booking_rejects_conflicting_vehicle_range(): void
    {
        $reserved = Rental::where('status', 'reserved')->whereNotNull('vehicle_id')->firstOrFail();
        $customer = \App\Models\Customer::where('is_blacklisted', false)->firstOrFail();

        $res = $this->withHeaders(['Authorization' => $this->actingAsOperator()])
            ->postJson('/api/v1/field/booking', $this->bookingPayload(
                (int) $reserved->vehicle_id,
                (int) $customer->customer_id,
                [
                    'rental_start_date' => $reserved->rental_start_date->toDateString(),
                    'rental_end_date' => $reserved->rental_end_date->copy()->addDay()->toDateString(),
                ],
            ));

        $res->assertStatus(422);
        $this->assertSame('VALIDATION_ERROR', $res->headers->get('X-Error-Code'));
        $this->assertStringContainsString('dipesan', $res->json('msg'));
    }

    public function test_booking_is_idempotent_via_client_uuid(): void
    {
        $vehicle = $this->takeFreeVehicle();
        $customer = \App\Models\Customer::where('is_blacklisted', false)->firstOrFail();
        $payload = $this->bookingPayload((int) $vehicle->vehicle_id, (int) $customer->customer_id);

        $first = $this->withHeaders(['Authorization' => $this->actingAsOperator()])
            ->postJson('/api/v1/field/booking', $payload);
        $first->assertOk();

        $second = $this->withHeaders(['Authorization' => $this->actingAsOperator()])
            ->postJson('/api/v1/field/booking', $payload);

        $second->assertStatus(409);
        $this->assertSame('ALREADY_PROCESSED', $second->headers->get('X-Error-Code'));
        $this->assertSame(
            $first->json('data.rental_code'),
            $second->json('data.rental_code'),
        );

        // Tidak ada sewa duplikat untuk unit ini pada rentang yang sama.
        $this->assertSame(1, Rental::where('vehicle_id', $vehicle->vehicle_id)
            ->whereIn('status', ['reserved', 'ongoing'])->count());
    }

    public function test_booking_validates_input(): void
    {
        $customer = \App\Models\Customer::where('is_blacklisted', false)->firstOrFail();

        // Tanggal akhir sebelum mulai → 422 field-level.
        $this->withHeaders(['Authorization' => $this->actingAsOperator()])
            ->postJson('/api/v1/field/booking', $this->bookingPayload(1, (int) $customer->customer_id, [
                'rental_start_date' => now()->addDays(5)->toDateString(),
                'rental_end_date' => now()->addDays(2)->toDateString(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rental_end_date']);
    }
}

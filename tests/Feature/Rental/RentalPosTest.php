<?php

namespace Tests\Feature\Rental;

use App\Models\Rental;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POS Booking — kasir sewa satu layar (AUDIT_BOOKING.md §5):
 * - Opsi & pencarian pelanggan + walk-in cepat.
 * - Unit bebas per rentang (buffer 3 jam).
 * - Quote paritas RentalPricing (= wizard web & mobile).
 * - Store 1 klik: tx + row-lock + buffer + unit reserved + kode RNT.
 * - Konflik rentang ditolak (anti double-booking).
 */
class RentalPosTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->cashier = User::where('username', 'staff')->first();
        $this->actingAs($this->cashier);
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

    public function test_pos_page_renders(): void
    {
        $this->get(route('rental.pos'))
            ->assertOk()
            ->assertSee('SIMPAN BOOKING')
            ->assertSee('Walk-in');
    }

    public function test_options_returns_references(): void
    {
        $res = $this->getJson(route('rental.pos.options'));

        $res->assertOk();
        $data = $res->json('data');
        $this->assertNotEmpty($data['locations']);
        $this->assertArrayHasKey('promos', $data);
        $this->assertArrayHasKey('tax_percent', $data);
    }

    public function test_customer_search_and_walkin(): void
    {
        $customer = \App\Models\Customer::where('is_blacklisted', false)->firstOrFail();

        // Pencarian by nama & HP.
        $this->getJson(route('rental.pos.customers', ['q' => substr($customer->first_name, 0, 3)]))
            ->assertOk()
            ->assertJsonFragment(['customer_id' => (int) $customer->customer_id]);

        // Blacklisted tidak muncul.
        $blacklisted = \App\Models\Customer::where('is_blacklisted', true)->first();
        if ($blacklisted) {
            $found = $this->getJson(route('rental.pos.customers', ['q' => $blacklisted->first_name]))
                ->json('data.customers');
            $this->assertNotContains((int) $blacklisted->customer_id, array_column($found, 'customer_id'));
        }

        // Walk-in: nama + HP → pelanggan individual baru.
        $res = $this->postJson(route('rental.pos.customers.store'), [
            'name' => 'Walkin Kasir Test',
            'phone' => '081234567890',
        ]);
        $res->assertOk()->assertJsonPath('status', true);
        $this->assertDatabaseHas('m_customer', [
            'first_name' => 'Walkin Kasir Test',
            'phone' => '081234567890',
            'customer_type' => 'individual',
        ]);

        // Validasi: nama & HP wajib.
        $this->postJson(route('rental.pos.customers.store'), ['phone' => '0812'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_vehicles_excludes_booked_range(): void
    {
        $reserved = Rental::where('status', 'reserved')->whereNotNull('vehicle_id')->firstOrFail();

        $this->getJson(route('rental.pos.vehicles', [
            'start' => $reserved->rental_start_date->toDateString(),
            'end' => $reserved->rental_end_date->copy()->addDay()->toDateString(),
        ]))
            ->assertOk()
            ->assertJsonMissing(['plate' => $reserved->vehicle->license_plate]);
    }

    public function test_quote_matches_parity_pricing(): void
    {
        $vehicle = $this->takeFreeVehicle();
        $customer = \App\Models\Customer::where('is_blacklisted', false)->firstOrFail();
        $settings = \App\Support\AppSettings::all();
        $days = 2;
        $start = now()->addDay()->startOfDay();

        $res = $this->postJson(route('rental.pos.quote'), [
            'vehicle_id' => $vehicle->vehicle_id,
            'customer_id' => $customer->customer_id,
            'rental_start_date' => $start->toDateString(),
            'rental_end_date' => $start->copy()->addDays($days)->toDateString(),
            'is_with_driver' => true,
        ]);
        $res->assertOk();

        $q = $res->json('data');
        $base = (float) $vehicle->model->base_price_per_day;
        $insurance = $base * $days * ((float) $vehicle->model->insurance_rate / 100);
        $driver = (float) ($settings['driver_fee_default'] ?? 0) * $days;
        $subtotal = $base * $days + $insurance + $driver;
        $taxPercent = ($settings['tax_enabled'] ?? true) ? (float) ($settings['tax_percent'] ?? 11) : 0.0;

        $this->assertSame($days, (int) $q['rental_days']);
        $this->assertEqualsWithDelta($subtotal + $subtotal * ($taxPercent / 100), (float) $q['total_amount'], 1.0);
        $this->assertEqualsWithDelta($taxPercent, (float) $q['tax_percent'], 0.01);
    }

    public function test_store_creates_reserved_rental_one_click(): void
    {
        $vehicle = $this->takeFreeVehicle();
        $customer = \App\Models\Customer::where('is_blacklisted', false)->firstOrFail();
        $start = now()->addDay()->startOfDay();
        $days = 2;

        $res = $this->postJson(route('rental.pos.store'), [
            'customer_id' => $customer->customer_id,
            'vehicle_id' => $vehicle->vehicle_id,
            'rental_start_date' => $start->toDateString(),
            'rental_end_date' => $start->copy()->addDays($days)->toDateString(),
            'pickup_location_id' => 1,
            'return_location_id' => 1,
        ]);
        $res->assertOk()->assertJsonPath('status', true);

        $data = $res->json('data');
        $this->assertSame('reserved', Rental::where('rental_code', $data['rental_code'])->value('status'));
        $this->assertDatabaseHas('m_vehicle', ['vehicle_id' => $vehicle->vehicle_id, 'status' => 'reserved']);
        $this->assertDatabaseHas('user_logs', ['user_id' => $this->cashier->id, 'menu' => 'rental']);
    }

    public function test_store_rejects_conflicting_range(): void
    {
        $reserved = Rental::where('status', 'reserved')->whereNotNull('vehicle_id')->firstOrFail();
        $customer = \App\Models\Customer::where('is_blacklisted', false)->firstOrFail();

        $this->postJson(route('rental.pos.store'), [
            'customer_id' => $customer->customer_id,
            'vehicle_id' => $reserved->vehicle_id,
            'rental_start_date' => $reserved->rental_start_date->toDateString(),
            'rental_end_date' => $reserved->rental_end_date->copy()->addDay()->toDateString(),
            'pickup_location_id' => 1,
            'return_location_id' => 1,
        ])->assertStatus(422);
    }
}

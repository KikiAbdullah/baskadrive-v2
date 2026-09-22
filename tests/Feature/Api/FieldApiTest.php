<?php

namespace Tests\Feature\Api;

use App\Models\ApiIdempotencyKey;
use App\Models\DamageReport;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi API mobile `/api/v1/field` (konsep BaskaDrive Operator, Lampiran B):
 * - Semua endpoint terlindungi JWT (guard api).
 * - Alur tugas → handover-out → return mengubah status sewa seperti web.
 * - Tulis idempotent via client_uuid (409 ALREADY_PROCESSED).
 * - Pembayaran melewati RentalSettlementService (jurnal & rekonsiliasi web).
 */
class FieldApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->operator = User::where('username', 'staff')->first();

        config(['field.operator_payment_limit' => 5000000]);
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    /** Login JWT sebagai operator dan kembalikan header bearer. */
    protected function actingAsOperator(): string
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => $this->operator->username,
            'password' => 'staff',
        ]);

        $response->assertOk();

        return 'Bearer '.$response->json('data.access_token');
    }

    /** Ambil satu sewa reserved & satu ongoing dari seeder. */
    protected function takeRentals(): array
    {
        $reserved = Rental::where('status', 'reserved')->inRandomOrder()->first();
        $ongoing = Rental::where('status', 'ongoing')->inRandomOrder()->first();

        if (! $reserved || ! $ongoing) {
            $this->markTestSkipped('Seeder tidak memiliki sewa reserved/ongoing.');
        }

        return [$reserved, $ongoing];
    }

    // ------------------------------------------------------------------
    // Auth guard
    // ------------------------------------------------------------------

    public function test_field_endpoints_require_jwt(): void
    {
        $this->getJson('/api/v1/field/tasks')->assertStatus(401);
        $this->getJson('/api/v1/field/meta')->assertStatus(401);
        $this->postJson('/api/v1/field/damages')->assertStatus(401);
    }

    // ------------------------------------------------------------------
    // Tugas (B.1)
    // ------------------------------------------------------------------

    public function test_operator_lists_today_tasks(): void
    {
        $token = $this->actingAsOperator();

        $response = $this->getJson('/api/v1/field/tasks?scope=all', ['Authorization' => $token]);
        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['data' => ['summary' => ['handover_out', 'handover_in', 'overdue'], 'tasks']]);

        $tasks = $response->json('data.tasks');
        $this->assertNotEmpty($tasks);

        $first = $tasks[0];
        $this->assertArrayHasKey('rental_code', $first);
        $this->assertArrayHasKey('task_type', $first);
        $this->assertContains($first['task_type'], ['handover_out', 'handover_in']);
        $this->assertArrayHasKey('customer', $first);
        $this->assertArrayHasKey('vehicle', $first);
    }

    public function test_task_detail_returns_full_context(): void
    {
        [$reserved] = $this->takeRentals();
        $token = $this->actingAsOperator();

        $response = $this->getJson("/api/v1/field/tasks/{$reserved->rental_id}", ['Authorization' => $token]);

        $response->assertOk()
            ->assertJsonPath('data.rental_code', $reserved->rental_code)
            ->assertJsonPath('data.task_type', 'handover_out')
            ->assertJsonStructure(['data' => ['customer', 'vehicle', 'pricing' => ['total_amount', 'deposit_amount']]]);
    }

    // ------------------------------------------------------------------
    // Handover Out (B.2)
    // ------------------------------------------------------------------

    public function test_handover_out_moves_rental_to_ongoing(): void
    {
        // Pilih sewa reserved yang BELUM punya inspeksi awal — kalau sudah ada,
        // endpoint berperilaku idempotent business-level (status tetap, bukan error).
        $reserved = Rental::where('status', 'reserved')->whereDoesntHave('handoverOut')->first();
        if (! $reserved) {
            $this->markTestSkipped('Seeder tidak memiliki sewa reserved tanpa inspeksi awal.');
        }
        $token = $this->actingAsOperator();
        $odo = (int) ($reserved->vehicle->mileage ?? 0) + 10;

        $response = $this->postJson("/api/v1/field/rentals/{$reserved->rental_id}/handover-out", [
            'client_uuid' => '11111111-1111-4111-8111-111111111111',
            'odometer' => $odo,
            'fuel_level' => 'full',
            'checklist' => [
                ['key' => 'stnk', 'present' => true],
                ['key' => 'spare_tire', 'present' => false],
            ],
            'body_damage_points' => [
                ['side' => 'front', 'x' => 0.3, 'y' => 0.5, 'note' => 'baret kecil'],
            ],
            'notes' => 'Serah terima via test.',
        ], ['Authorization' => $token]);

        $response->assertOk()->assertJsonPath('status', true);

        $reserved->refresh();
        $this->assertEquals('ongoing', $reserved->status);
        $this->assertEquals('rented', $reserved->vehicle->refresh()->status);

        $inspection = $reserved->handoverOut;
        $this->assertNotNull($inspection);
        $this->assertEquals($odo, $inspection->odometer);
        $this->assertEquals(['stnk' => true, 'spare_tire' => false], $inspection->checklist);
    }

    public function test_handover_out_is_idempotent_on_duplicate_client_uuid(): void
    {
        $reserved = Rental::where('status', 'reserved')->whereDoesntHave('handoverOut')->first();
        if (! $reserved) {
            $this->markTestSkipped('Seeder tidak memiliki sewa reserved tanpa inspeksi awal.');
        }
        $token = $this->actingAsOperator();
        $uuid = '22222222-2222-4222-8222-222222222222';
        $payload = [
            'client_uuid' => $uuid,
            'odometer' => (int) ($reserved->vehicle->mileage ?? 0) + 5,
            'fuel_level' => 'half',
        ];

        $first = $this->postJson("/api/v1/field/rentals/{$reserved->rental_id}/handover-out", $payload, ['Authorization' => $token]);
        $first->assertOk();

        // Kirim ulang persis — harus 409 ALREADY_PROCESSED, bukan error 500.
        $second = $this->postJson("/api/v1/field/rentals/{$reserved->rental_id}/handover-out", $payload, ['Authorization' => $token]);
        $second->assertStatus(409)
            ->assertHeader('X-Error-Code', 'ALREADY_PROCESSED')
            ->assertJsonPath('code', 'ALREADY_PROCESSED');

        $this->assertDatabaseHas('api_idempotency_keys', ['client_uuid' => $uuid]);
    }

    // ------------------------------------------------------------------
    // Handover In / Return (B.3)
    // ------------------------------------------------------------------

    public function test_return_calculate_estimates_additional_charges(): void
    {
        [$reserved, $ongoing] = $this->takeRentals();
        $token = $this->actingAsOperator();

        $response = $this->postJson("/api/v1/field/rentals/{$ongoing->rental_id}/return/calculate", [
            'odometer_end' => (int) ($ongoing->handoverOut?->odometer ?? $ongoing->vehicle->mileage) + 100,
            'fuel_level_end' => 'half',
            'new_damages' => [
                ['severity' => 'moderate', 'estimate' => 500000],
            ],
        ], ['Authorization' => $token]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.damage_estimate', 500000);

        $this->assertArrayHasKey('total_additional', $response->json('data'));
    }

    public function test_return_rejects_odometer_below_pickup(): void
    {
        [$reserved, $ongoing] = $this->takeRentals();
        $token = $this->actingAsOperator();

        $pickupOdo = (int) ($ongoing->handoverOut?->odometer ?? $ongoing->vehicle->mileage ?? 0);

        $response = $this->postJson("/api/v1/field/rentals/{$ongoing->rental_id}/return", [
            'odometer_end' => max(0, $pickupOdo - 100),
            'fuel_level_end' => 'full',
        ], ['Authorization' => $token]);

        $response->assertStatus(422)->assertHeader('X-Error-Code', 'VALIDATION_ERROR');
    }

    public function test_return_completes_rental_with_damage_and_fine(): void
    {
        [$reserved, $ongoing] = $this->takeRentals();
        $token = $this->actingAsOperator();
        $odoEnd = (int) ($ongoing->handoverOut?->odometer ?? $ongoing->vehicle->mileage) + 250;

        // Pastikan sewa ongoing punya inspeksi awal (syarat kontrak audit).
        if (! $ongoing->handoverOut) {
            $this->postJson("/api/v1/field/rentals/{$ongoing->rental_id}/handover-out", [
                'client_uuid' => '33333333-3333-4333-8333-333333333333',
                'odometer' => (int) $ongoing->vehicle->mileage,
                'fuel_level' => 'full',
            ], ['Authorization' => $token])->assertStatus(200);
            $odoEnd = (int) $ongoing->vehicle->mileage + 250;
        }

        $response = $this->postJson("/api/v1/field/rentals/{$ongoing->rental_id}/return", [
            'client_uuid' => '44444444-4444-4444-8444-444444444444',
            // Pilih sewa tanpa invoice terbit agar extra charge tidak menabrak
            // aturan web "invoice terbit tidak direkalkulasi".
            'odometer_end' => $odoEnd,
            'fuel_level_end' => 'quarter',
            'vehicle_condition' => 'damaged',
            'new_damages' => [
                ['side' => 'rear', 'severity' => 'minor', 'estimate' => 300000, 'description' => 'baret bumper'],
            ],
        ], ['Authorization' => $token]);

        $response->assertOk()->assertJsonPath('data.rental_status', 'completed');

        $ongoing->refresh();
        $this->assertEquals('completed', $ongoing->status);
        // Kontrak audit: unit rusak → maintenance (bukan available).
        $this->assertEquals('maintenance', $ongoing->vehicle->refresh()->status);

        // Kerusakan baru → damage report (reported) + fine unpaid terkait.
        $damage = DamageReport::where('rental_id', $ongoing->rental_id)->where('return_id', $response->json('data.return_id'))->first();
        $this->assertNotNull($damage);
        $this->assertEquals('reported', $damage->status);
        $this->assertDatabaseHas('tr_fine', [
            'damage_id' => $damage->damage_id,
            'fine_type' => 'damage',
            'status' => 'unpaid',
            'amount' => 300000,
        ]);
    }

    // ------------------------------------------------------------------
    // Kerusakan (B.4)
    // ------------------------------------------------------------------

    public function test_report_damage_creates_reported_record(): void
    {
        [$reserved, $ongoing] = $this->takeRentals();
        $token = $this->actingAsOperator();

        $response = $this->postJson('/api/v1/field/damages', [
            'client_uuid' => '55555555-5555-4555-8555-555555555555',
            'vehicle_id' => $ongoing->vehicle_id,
            'severity' => 'minor',
            'damage_type' => 'exterior',
            'body_points' => [['side' => 'kiri']],
            'description' => 'Baret sisi kiri saat parkir.',
            'estimate' => 150000,
        ], ['Authorization' => $token]);

        $response->assertOk()->assertJsonPath('status', true);

        $this->assertDatabaseHas('tr_damage_report', [
            'vehicle_id' => $ongoing->vehicle_id,
            'severity' => 'minor',
            'status' => 'reported',
        ]);
    }

    // ------------------------------------------------------------------
    // Pembayaran (B.5)
    // ------------------------------------------------------------------

    public function test_field_payment_within_authority_completes_via_settlement_service(): void
    {
        [$reserved, $ongoing] = $this->takeRentals();
        $token = $this->actingAsOperator();

        // Ambil sewa yang benar-benar masih punya sisa tagihan (total - terbayar).
        $rental = Rental::where('payment_status', '!=', 'paid')->where('total_amount', '>', 0)->get()
            ->first(fn (Rental $r) => (float) $r->total_amount - (float) $r->payments()->where('allocation', 'rental')->where('status', 'completed')->sum('amount') >= 100000)
            ?? Rental::where('payment_status', '!=', 'paid')->where('total_amount', '>', 0)->firstOrFail();
        $this->assertNotNull($rental);

        $alreadyPaid = (float) $rental->payments()->where('allocation', 'rental')->where('status', 'completed')->sum('amount');
        $remaining = (float) $rental->total_amount - $alreadyPaid;
        $this->assertGreaterThan(0, $remaining, 'Test butuh sewa dengan sisa tagihan.');
        $amount = min(100000, $remaining);

        $response = $this->postJson('/api/v1/field/payments', [
            'client_uuid' => '66666666-6666-4666-8666-666666666666',
            'rental_id' => $rental->rental_id,
            'amount' => $amount,
            'method' => 'cash',
            'reference_no' => 'TEST-001',
        ], ['Authorization' => $token]);

        $response->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('tr_payment', [
            'rental_id' => $rental->rental_id,
            'amount' => $amount,
            'allocation' => 'rental',
            'status' => 'completed',
        ]);

        // Jurnal hard-post dari RentalSettlementService (ref = PAY-{id}).
        $this->assertDatabaseHas('tr_journal', ['reference_number' => 'PAY-'.$response->json('data.payment_id')]);
    }

    public function test_field_payment_above_authority_becomes_pending_approval(): void
    {
        [$reserved, $ongoing] = $this->takeRentals();
        $token = $this->actingAsOperator();
        $rental = Rental::where('payment_status', '!=', 'paid')->where('total_amount', '>', 0)->firstOrFail();

        $response = $this->postJson('/api/v1/field/payments', [
            'client_uuid' => '77777777-7777-4777-8777-777777777777',
            'rental_id' => $rental->rental_id,
            'amount' => 9999999, // di atas limit default 5 juta
            'method' => 'transfer',
        ], ['Authorization' => $token]);

        $response->assertOk()->assertJsonPath('data.status', 'pending_approval');

        $this->assertDatabaseHas('tr_payment', [
            'rental_id' => $rental->rental_id,
            'amount' => 9999999,
            'status' => 'pending',
        ]);
    }

    // ------------------------------------------------------------------
    // Unit & Meta (B.4/B.6)
    // ------------------------------------------------------------------

    public function test_vehicle_search_finds_by_plate(): void
    {
        $token = $this->actingAsOperator();
        $vehicle = \App\Models\Vehicle::first();

        $response = $this->getJson('/api/v1/field/vehicles/search?q='.urlencode($vehicle->license_plate), ['Authorization' => $token]);

        $response->assertOk()->assertJsonPath('status', true);
        $this->assertNotEmpty($response->json('data.vehicles'));
    }

    public function test_meta_returns_reference_payload(): void
    {
        $token = $this->actingAsOperator();

        $response = $this->getJson('/api/v1/field/meta', ['Authorization' => $token]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['data' => [
                'min_app_version', 'checklist_template', 'fine_rates',
                'operator_payment_limit', 'damage_types', 'fuel_policy',
            ]]);
    }

    public function test_device_registration_is_upsert(): void
    {
        $token = $this->actingAsOperator();

        $this->postJson('/api/v1/field/devices', [
            'device_id' => 'device-test-001',
            'fcm_token' => 'token-abc',
            'app_version' => '1.0.0',
        ], ['Authorization' => $token])->assertOk();

        $this->assertTrue(cache()->has('field_device:device-test-001'));
    }
}

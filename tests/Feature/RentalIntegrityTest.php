<?php

namespace Tests\Feature;

use App\Models\Rental;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function user(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    public function test_write_gates_per_permission(): void
    {
        $staff = $this->user('staff');
        // STAFF punya rental_add → boleh buka wizard
        $this->actingAs($staff)->get('/rental/create')->assertOk();
        // STAFF tidak punya rental_export → ekspor ditolak
        $this->actingAs($staff)->get('/rental/export')->assertForbidden();
        // VIEWER tidak punya rental_view sama sekali
        $this->actingAs($this->user('viewer'))->get('/rental/create')->assertForbidden();
    }

    public function test_double_booking_with_overlap_is_rejected(): void
    {
        $ongoing = Rental::where('status', 'ongoing')->firstOrFail();

        $before = Rental::count();

        $response = $this->actingAs($this->user('staff'))
            ->withSession([
                'rental_wizard_data' => [
                    'customer_id' => $ongoing->customer_id,
                    'vehicle_id' => $ongoing->vehicle_id,
                    'rental_start_date' => $ongoing->rental_start_date->toDateString(),
                    'rental_end_date' => $ongoing->rental_end_date->toDateString(),
                ],
            ])
            ->postJson(route('rental.create.store'));

        $response->assertOk()->assertJson(['status' => false]);
        $this->assertStringContainsString('tidak tersedia', $response->json('msg'));
        $this->assertSame($before, Rental::count(), 'tidak boleh ada rental baru saat overlap');
    }

    public function test_overpayment_blocked_and_partial_status(): void
    {
        $rental = Rental::where('status', 'completed')
            ->where('total_amount', '>', 0)
            ->whereDoesntHave('payments')
            ->firstOrFail();
        $half = round($rental->total_amount * 0.6, 2);

        // pembayaran pertama (60%) → sukses, status jadi partial
        $first = $this->actingAs($this->user('staff'))->postJson(
            route('rental.detail.payment.store', $rental->rental_id),
            ['amount' => $half, 'payment_method' => 'cash']
        );
        $first->assertOk()->assertJson(['status' => true]);
        $this->assertSame('partial', $rental->fresh()->payment_status);

        // pembayaran kedua (60% lagi) → melebihi tagihan → ditolak
        $second = $this->actingAs($this->user('staff'))->postJson(
            route('rental.detail.payment.store', $rental->rental_id),
            ['amount' => $half, 'payment_method' => 'transfer'] // alias lama → dinormalisasi
        );
        $second->assertOk()->assertJson(['status' => false]);
        $this->assertStringContainsString('melebihi sisa tagihan', $second->json('msg'));

        // pelunasan → paid
        $reste = round($rental->total_amount - $half, 2);
        $third = $this->actingAs($this->user('staff'))->postJson(
            route('rental.detail.payment.store', $rental->rental_id),
            ['amount' => $reste, 'payment_method' => 'bank_transfer']
        );
        $third->assertOk()->assertJson(['status' => true]);
        $this->assertSame('paid', $rental->fresh()->payment_status);

        // jurnal pembayaran tercatat
        $this->assertDatabaseHas('tr_journal', ['journal_type' => 'payment']);
    }

    public function test_mark_overdue_sweep_flags_late_rentals(): void
    {
        // ongoing yang lewat grace dibuat sintetis agar deterministik
        $ongoing = Rental::where('status', 'ongoing')->first();
        $this->assertNotNull($ongoing, 'data seed harus punya rental ongoing');
        $ongoing->update(['rental_end_date' => now()->subHours(3)]);
        $rental = $ongoing->fresh();
        $vehicleBefore = $rental->vehicle?->status;

        $this->artisan('rentals:mark-overdue')->assertSuccessful();

        $this->assertSame('overdue', $rental->fresh()->status, 'perubahan status ke overdue');

        // IDEMPOTEN: dijalankan lagi tidak mengubah apa pun & tetap sukses
        $this->artisan('rentals:mark-overdue')->assertSuccessful();
        $this->assertSame('overdue', $rental->fresh()->status);

        // trigger DB TIDAK boleh me-reset kendaraan ke available saat overdue:
        // status kendaraan harus tetap seperti semula (biasanya 'rented')
        $this->assertSame($vehicleBefore, $rental->fresh()->vehicle->status, 'sweep tidak menyentuh status kendaraan');
    }

    public function test_state_machine_blocks_reserved_to_completed_via_edit(): void
    {
        $rental = Rental::where('status', 'reserved')->firstOrFail();

        $response = $this->actingAs($this->user('staff'))->put(
            route('rental.update', $rental->rental_id),
            [
                'rental_start_date' => $rental->rental_start_date->format('Y-m-d\TH:i'),
                'rental_end_date' => $rental->rental_end_date->format('Y-m-d\TH:i'),
                'status' => 'completed',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('errors');
        $this->assertStringContainsString(
            'Transisi status',
            implode(' ', app('session.store')->get('errors')->all() ?? [])
        );
        $this->assertSame('reserved', $rental->fresh()->status);
    }
}

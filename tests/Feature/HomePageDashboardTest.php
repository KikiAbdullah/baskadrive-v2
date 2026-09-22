<?php

namespace Tests\Feature;

use App\Models\DamageReport;
use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi beranda (rute `/`) — dashboard eksekutif data-driven AppController.
 * Memastikan setiap peran melihat seksi yang tepat dan angka yang ditampilkan
 * dihitung dari database, bukan angka statis.
 */
class HomePageDashboardTest extends TestCase
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

    public function test_renders_greeting_and_activity_for_all_roles(): void
    {
        foreach (['superadmin', 'manager', 'supervisor', 'staff', 'viewer'] as $username) {
            $response = $this->actingAs($this->user($username))->get('/');

            $response->assertOk()
                ->assertViewIs('home')
                ->assertViewHas('greeting')
                ->assertViewHas('userName')
                ->assertViewHas('roleLabel')
                ->assertViewHas('recentLogs')
                ->assertSee('Aktivitas Pengguna Terbaru');

            // Sapaan memuat nama user — bukan lagi "Selamat Datang, Admin!" statis.
            $response->assertSee($this->user($username)->name, false);
        }
    }

    public function test_viewer_sees_no_operational_data_and_no_privileged_links(): void
    {
        $response = $this->actingAs($this->user('viewer'))->get('/');

        $response->assertOk()
            ->assertViewMissing('vehicleStatus')
            ->assertViewMissing('receivableAmount')
            ->assertViewMissing('openDamageCount')
            ->assertViewMissing('ledger')
            // Tanpa akses modul: tidak boleh ada tautan aksi operasional.
            ->assertDontSee('href="'.e(route('rental.index')).'"', false)
            ->assertDontSee('href="'.e(route('finance.index')).'"', false)
            ->assertDontSee('href="'.e(route('accounting.journal.index')).'"', false)
            // Kartu tetap informatif, bukan kosong/crash.
            ->assertSee('Ringkasan sewa butuh akses modul Sewa');
    }

    public function test_superadmin_sees_all_module_sections(): void
    {
        $response = $this->actingAs($this->user('superadmin'))->get('/');

        $response->assertOk()
            ->assertViewHas('vehicleStatus')
            ->assertViewHas('rentalStatus')
            ->assertViewHas('trend')
            ->assertViewHas('pickupToday')
            ->assertViewHas('returnToday')
            ->assertViewHas('dueSoon')
            ->assertViewHas('overdueRentals')
            ->assertViewHas('receivableAmount')
            ->assertViewHas('receivedToday')
            ->assertViewHas('receivedMonth')
            ->assertViewHas('methodBreakdown')
            ->assertViewHas('scheduledMaintenance')
            ->assertViewHas('openDamage')
            ->assertViewHas('claimsInProgress')
            ->assertViewHas('ledger')
            ->assertViewHas('recentJournals')
            ->assertSee('Tren Sewa &amp; Penerimaan', false)
            ->assertSee('Serah Terima Hari Ini')
            ->assertSee('Pengembalian Hari Ini')
            ->assertSee('Invoice Jatuh Tempo')
            ->assertSee('Maintenance Terjadwal')
            ->assertSee('Kerusakan &amp; Klaim Asuransi Aktif', false)
            ->assertSee('Neraca Ringkas')
            ->assertSee('Jurnal Terakhir');
    }

    public function test_kpi_numbers_are_computed_from_database_not_static(): void
    {
        // Seeder menghasilkan sewa berjalan (ongoing+overdue) — ingat hitungannya,
        // lalu tambahkan satu sewa berjalan baru; KPI harus berubah.
        $before = $this->actingAs($this->user('superadmin'))->get('/')->assertOk()
            ->assertViewHas('activeRentals');

        $countBefore = $before->viewData('activeRentals');

        $template = Rental::query()->where('status', 'ongoing')->firstOrFail();
        $rental = $template->replicate()->unsetRelations();
        $rental->forceFill([
            'rental_code' => 'HOME-TEST-001',
            'status' => 'overdue',
        ])->save();

        $after = $this->actingAs($this->user('superadmin'))->get('/')->assertOk();
        $this->assertSame($countBefore + 1, $after->viewData('activeRentals'));
        $after->assertSee('1 terlambat');
    }

    public function test_finance_cards_reflect_payment_and_invoice_changes(): void
    {
        $this->actingAs($this->user('manager'));

        $before = $this->get('/')->assertOk()->assertViewHas('receivedToday');
        $receivedBefore = (float) $before->viewData('receivedToday');

        Payment::create([
            'rental_id' => Rental::query()->firstOrFail()->getKey(),
            'payment_date' => now(),
            'amount' => 234567,
            'payment_method' => 'cash',
            'status' => 'completed',
            'allocation' => Payment::ALLOCATION_RENTAL,
        ]);

        $after = $this->get('/')->assertOk();
        $this->assertEquals($receivedBefore + 234567.0, (float) $after->viewData('receivedToday'));

        // Piutang: invoice belum lunas menambah piutang.
        $receivableBefore = (float) $this->get('/')->assertOk()->viewData('receivableAmount');

        $rental = Rental::query()
            ->whereDoesntHave('invoices')
            ->firstOrFail();
        Invoice::create([
            'rental_id' => $rental->getKey(),
            'invoice_number' => 'HOME-INV-001',
            'issue_date' => now(),
            'due_date' => now()->subDay(),
            'sub_total' => 1000,
            'total_amount' => 1000,
            'paid_amount' => 0,
            'status' => 'sent',
        ]);

        $afterReceivable = $this->get('/')->assertOk();
        $this->assertEquals($receivableBefore + 1000.0, (float) $afterReceivable->viewData('receivableAmount'));
        $afterReceivable->assertSee('jatuh tempo');
    }

    public function test_damage_and_maintenance_panels_reflect_fleet_state(): void
    {
        $this->actingAs($this->user('staff'));

        $template = Maintenance::query()->firstOrFail();
        $scheduledBefore = $this->get('/')->assertOk()->viewData('scheduledMaintenanceCount');

        $m = $template->replicate()->unsetRelations();
        $m->forceFill([
            'vehicle_id' => $template->vehicle_id,
            'workshop_id' => $template->workshop_id,
            'maintenance_type_id' => $template->maintenance_type_id,
            'scheduled_date' => now()->addDays(3),
            'status' => 'scheduled',
            'cost' => 500000,
        ])->save();

        $this->get('/')->assertOk()->assertViewHas(
            'scheduledMaintenanceCount',
            fn ($count) => $count === $scheduledBefore + 1
        );

        // Kerusakan aktif baru muncul di panel dan menaikkan hitungan.
        $rental = Rental::query()->firstOrFail();
        $damageBefore = $this->get('/')->assertOk()->viewData('openDamageCount');
        DamageReport::create([
            'rental_id' => $rental->getKey(),
            'vehicle_id' => $rental->vehicle_id,
            'reported_date' => now(),
            'damage_type' => 'exterior',
            'severity' => 'severe',
            'status' => 'reported',
            'repair_cost_estimate' => 1500000,
        ]);

        $page = $this->get('/')->assertOk();
        $this->assertSame($damageBefore + 1, $page->viewData('openDamageCount'));
        // Kerusakan berat menandai kartu armada.
        $page->assertSee('berat');
    }

    public function test_accounting_panel_checks_balance_health(): void
    {
        $response = $this->actingAs($this->user('supervisor'))->get('/')->assertOk();

        // Seeder menjaga jurnal berpasangan — invarian neraca harus sehat.
        $this->assertTrue($response->viewData('balanceHealthy'));
        $response->assertViewHas('ledger', fn ($ledger) => $ledger->count() === 3)
            ->assertViewHas('recentJournals');
    }

    public function test_7day_trend_counts_today_rentals(): void
    {
        $this->actingAs($this->user('superadmin'));

        $todayKey = now()->toDateString();
        $before = $this->get('/')->assertOk()->assertViewHas('trend');
        $rentalsBefore = $before->viewData('trend')[$todayKey]['rentals'] ?? null;
        $this->assertNotNull($rentalsBefore);

        $template = Rental::query()->firstOrFail();
        $rental = $template->replicate()->unsetRelations();
        $rental->forceFill(['rental_code' => 'HOME-TREND-001'])->save();

        $after = $this->get('/')->assertOk();
        $this->assertSame($rentalsBefore + 1, $after->viewData('trend')[$todayKey]['rentals']);
    }
}

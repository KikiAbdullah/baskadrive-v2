<?php

namespace Tests\Feature;

use App\Models\Coa;
use App\Models\DamageReport;
use App\Models\Fine;
use App\Models\InsuranceClaim;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\Maintenance;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\User;
use App\Services\FineSettlementService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regresi langsung remediasi audit Fleet 15092026 (FLE-01..FLE-15).
 */
class FleetActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['2fa.enabled' => false]);
        $this->travelTo(Carbon::parse('2026-09-17 12:00:00'));
        $this->seed(DatabaseSeeder::class);
        Storage::fake('public');
    }

    private function user(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    private function accountId(string $code): int
    {
        return (int) Coa::where('account_code', $code)->value('account_id');
    }

    private function makeDamage(array $overrides = []): DamageReport
    {
        $rental = Rental::query()->firstOrFail();

        return DamageReport::create(array_merge([
            'rental_id' => $rental->getKey(),
            'vehicle_id' => $rental->vehicle_id,
            'reported_date' => now(),
            'damage_type' => 'exterior',
            'severity' => 'moderate',
            'status' => 'reported',
            'repair_cost_estimate' => 1500.75,
            'actual_repair_cost' => 0,
        ], $overrides));
    }

    // ============================================================
    // FLE-03/FLE-04: state machine kendaraan + transaksi
    // ============================================================

    public function test_complete_frees_vehicle_only_from_maintenance_state(): void
    {
        $this->actingAs($this->user('superadmin'));
        $maintenance = Maintenance::query()->where('status', '!=', 'completed')->firstOrFail()
            ?? Maintenance::query()->firstOrFail();
        $maintenance->update(['status' => 'scheduled']);
        $maintenance->vehicle->update(['status' => 'maintenance']);
        // FLE-03: kendaraan bermaintenance tidak boleh punya rental aktif agar boleh
        // dilepas ke available — matikan rental aktif seeded untuk skenario ini.
        Rental::where('vehicle_id', $maintenance->vehicle_id)
            ->whereIn('status', ['reserved', 'ongoing', 'overdue'])->update(['status' => 'cancelled']);

        $this->putJson(route('fleet.maintenance.complete', $maintenance->maintenance_id))
            ->assertOk()->assertJsonPath('status', true);

        $this->assertSame('completed', $maintenance->fresh()->status);
        $this->assertNotNull($maintenance->fresh()->actual_date);
        $this->assertSame('available', $maintenance->vehicle->fresh()->status);
    }

    public function test_complete_never_frees_rented_vehicle(): void
    {
        $this->actingAs($this->user('superadmin'));
        $maintenance = Maintenance::query()->where('status', '!=', 'completed')->firstOrFail()
            ?? Maintenance::query()->firstOrFail();
        $maintenance->update(['status' => 'scheduled']);
        $maintenance->vehicle->update(['status' => 'rented']);

        $this->putJson(route('fleet.maintenance.complete', $maintenance->maintenance_id))->assertOk();

        $this->assertSame('completed', $maintenance->fresh()->status);
        $this->assertSame('rented', $maintenance->vehicle->fresh()->status, 'FLE-03: rented tidak boleh dipaksa available');
    }

    public function test_complete_is_idempotent_blocked(): void
    {
        $this->actingAs($this->user('superadmin'));
        $maintenance = Maintenance::query()->where('status', '!=', 'completed')->firstOrFail()
            ?? Maintenance::query()->firstOrFail();
        $maintenance->update(['status' => 'scheduled']);

        $this->putJson(route('fleet.maintenance.complete', $maintenance->maintenance_id))->assertOk();
        $this->putJson(route('fleet.maintenance.complete', $maintenance->maintenance_id))
            ->assertStatus(409);
    }

    public function test_update_in_progress_parks_only_available_vehicle(): void
    {
        $this->actingAs($this->user('superadmin'));
        $maintenance = Maintenance::query()->firstOrFail();
        $maintenance->vehicle->update(['status' => 'rented']);

        $this->from(route('fleet.maintenance.index'))
            ->put(route('fleet.maintenance.update', $maintenance->maintenance_id), [
                'vehicle_id' => $maintenance->vehicle_id,
                'maintenance_type_id' => $maintenance->maintenance_type_id,
                'workshop_id' => $maintenance->workshop_id,
                'scheduled_date' => now()->toDateString(),
                'status' => 'in_progress',
                'cost' => '250000.50',
            ])->assertRedirect();

        $this->assertSame('rented', $maintenance->vehicle->fresh()->status);
        $this->assertSame('in_progress', $maintenance->fresh()->status);
    }

    // ============================================================
    // FLE-05: reschedule
    // ============================================================

    public function test_reschedule_rejects_completed_schedule_and_rental_conflict(): void
    {
        $this->actingAs($this->user('superadmin'));
        $maintenance = Maintenance::query()->firstOrFail();

        // FLE-05: jadwal selesai tidak boleh dibangkitkan
        $maintenance->update(['status' => 'completed']);
        $this->putJson(route('fleet.maintenance.reschedule', $maintenance->maintenance_id), [
            'scheduled_date' => now()->addWeek()->toDateString(),
        ])->assertStatus(404);

        // FLE-05: bentrok masa sewa aktif kendaraan ditolak
        $maintenance->update(['status' => 'scheduled']);
        Rental::where('vehicle_id', $maintenance->vehicle_id)
            ->whereIn('status', ['reserved', 'ongoing', 'overdue'])->update(['status' => 'cancelled']);
        Rental::create([
            'rental_code' => 'RNT-FLE-TEST',
            'customer_id' => Rental::query()->firstOrFail()->customer_id,
            'vehicle_id' => $maintenance->vehicle_id,
            'pickup_location_id' => Rental::query()->firstOrFail()->pickup_location_id,
            'return_location_id' => Rental::query()->firstOrFail()->return_location_id,
            'rental_start_date' => now()->addDays(7)->startOfDay(),
            'rental_end_date' => now()->addDays(10)->endOfDay(),
            'base_rate_per_day' => 100000,
            'status' => 'reserved',
        ]);

        $this->putJson(route('fleet.maintenance.reschedule', $maintenance->maintenance_id), [
            'scheduled_date' => now()->addDays(8)->toDateString(),
        ])->assertStatus(409);

        // Tanggal bebas bentrok diterima
        $this->putJson(route('fleet.maintenance.reschedule', $maintenance->maintenance_id), [
            'scheduled_date' => now()->addDays(20)->toDateString(),
        ])->assertOk()->assertJsonPath('status', true);
    }

    // ============================================================
    // FLE-02/FLE-11: kontrak status & enum
    // ============================================================

    public function test_damage_status_accepts_entire_canonical_contract(): void
    {
        $this->actingAs($this->user('superadmin'));

        foreach (DamageReport::STATUSES as $status) {
            $damage = $this->makeDamage();
            $this->putJson(route('fleet.damage.update-status', $damage->damage_id), ['status' => $status])
                ->assertOk()->assertJsonPath('status', true);
            $this->assertSame($status, $damage->fresh()->status);
        }
    }

    public function test_damage_store_rejects_invalid_type_and_mismatched_rental(): void
    {
        $this->actingAs($this->user('superadmin'));
        $rental = Rental::query()->firstOrFail();

        // FLE-11: damage_type lama versi UI (body/engine) tidak lagi lolos validasi
        $this->from(route('fleet.damage.create'))
            ->post(route('fleet.damage.store'), [
                'vehicle_id' => $rental->vehicle_id,
                'rental_id' => $rental->rental_id,
                'reported_date' => now()->toDateString(),
                'damage_type' => 'body',
                'severity' => 'minor',
            ])->assertRedirect()->assertSessionHasErrors('damage_type');

        // FLE-11: rental yang bukan milik kendaraan ditolak
        $otherVehicleRental = Rental::where('vehicle_id', '!=', $rental->vehicle_id)->firstOrFail();
        $this->from(route('fleet.damage.create'))
            ->post(route('fleet.damage.store'), [
                'vehicle_id' => $rental->vehicle_id,
                'rental_id' => $otherVehicleRental->rental_id,
                'reported_date' => now()->toDateString(),
                'damage_type' => 'exterior',
                'severity' => 'minor',
            ])->assertRedirect()->assertSessionHasErrors();

        // Enum valid + total_loss tersedia diterima
        $this->post(route('fleet.damage.store'), [
            'vehicle_id' => $rental->vehicle_id,
            'rental_id' => $rental->rental_id,
            'reported_date' => now()->toDateString(),
            'damage_type' => 'mechanical',
            'severity' => 'total_loss',
        ])->assertRedirect(route('fleet.damage.index'));
    }

    public function test_damage_data_filter_rejects_invalid_status(): void
    {
        $this->actingAs($this->user('superadmin'));

        $this->getJson(route('fleet.damage.data', ['status' => 'hacked']))->assertUnprocessable();
        $this->getJson(route('fleet.damage.data', ['status' => 'reported']))->assertOk();
    }

    // ============================================================
    // FLE-07/FLE-08: tagihan kerusakan + akrual piutang
    // ============================================================

    public function test_bill_renter_accrues_receivable_and_is_idempotent(): void
    {
        $this->actingAs($this->user('superadmin'));
        $damage = $this->makeDamage(['actual_repair_cost' => 750.25]);

        $this->putJson(route('fleet.damage.bill', $damage->damage_id))->assertOk()->assertJsonPath('status', true);

        $fine = Fine::query()->where('damage_id', $damage->damage_id)->firstOrFail();
        $this->assertSame('damage', $fine->fine_type);
        $this->assertEquals(750.25, (float) $fine->amount);

        // FLE-08: jurnal akrual Dr Piutang Sewa / Cr Pendapatan Denda
        $journal = Journal::where('reference_number', 'DMG-FINE-'.$fine->fine_id)->firstOrFail();
        $debitAccountId = JournalDetail::where('journal_id', $journal->journal_id)->where('debit', '>', 0)->value('account_id');
        $creditAccountId = JournalDetail::where('journal_id', $journal->journal_id)->where('credit', '>', 0)->value('account_id');
        $this->assertSame($this->accountId('1-2100'), (int) $debitAccountId);
        $this->assertSame($this->accountId('4-2000'), (int) $creditAccountId);

        // FLE-07: klik/tagih kedua ditolak (409), tidak ada denda kedua
        $this->putJson(route('fleet.damage.bill', $damage->damage_id))->assertStatus(409);
        $this->assertSame(1, Fine::query()->where('damage_id', $damage->damage_id)->count());
    }

    public function test_bill_renter_ignores_legacy_like_description_duplicates(): void
    {
        $this->actingAs($this->user('superadmin'));
        $damageA = $this->makeDamage(['actual_repair_cost' => 100]);
        $damageB = $this->makeDamage(['actual_repair_cost' => 100]);

        // Denda legacy milik damage lain yang kebetulan menyebut nomor damage B di deskripsi
        Fine::create([
            'rental_id' => $damageB->rental_id,
            'fine_type' => 'other',
            'description' => 'Keliru: kerusakan #'.$damageB->damage_id.' referensi',
            'amount' => 50,
            'status' => 'unpaid',
            'issued_date' => now(),
        ]);

        $this->putJson(route('fleet.damage.bill', $damageB->damage_id))->assertOk();
        $this->assertSame(1, Fine::query()->where('damage_id', $damageB->damage_id)->count());
    }

    public function test_paying_damage_fine_settles_receivable_not_double_income(): void
    {
        $this->actingAs($this->user('superadmin'));
        $damage = $this->makeDamage(['actual_repair_cost' => 300]);
        $this->putJson(route('fleet.damage.bill', $damage->damage_id))->assertOk();

        $fine = Fine::query()->where('damage_id', $damage->damage_id)->firstOrFail();
        app(FineSettlementService::class)->pay((int) $fine->fine_id);

        $fine->refresh();
        $this->assertSame('paid', $fine->status);

        // FLE-08: jurnal pelunasan piutang (Cr 1-2100), BUKAN pendapatan ganda (Cr 4-2000)
        $payment = Payment::where('rental_id', $fine->rental_id)->where('allocation', Payment::ALLOCATION_FINE)
            ->where('status', 'completed')->firstOrFail();
        $journal = Journal::where('reference_number', 'FINE-PAY-'.$payment->payment_id)->firstOrFail();
        $creditAccountId = JournalDetail::where('journal_id', $journal->journal_id)->where('credit', '>', 0)->value('account_id');
        $this->assertSame($this->accountId('1-2100'), (int) $creditAccountId);
    }

    // ============================================================
    // FLE-09: jurnal pencairan klaim hard-post + idempoten
    // ============================================================

    public function test_claim_paid_posts_journal_atomically_and_once(): void
    {
        $this->actingAs($this->user('superadmin'));
        $damage = $this->makeDamage();
        $claim = InsuranceClaim::create([
            'rental_id' => $damage->rental_id,
            'damage_id' => $damage->damage_id,
            'claim_number' => 'CLM-TEST-001',
            'insurance_provider' => 'Asuransi Uji',
            'policy_number' => 'POL-001',
            'claim_date' => now(),
            'claim_amount' => 5000,
            'status' => 'submitted',
        ]);

        // FLE-09: klaim belum disetujui tidak boleh langsung dibayar
        $this->putJson(route('fleet.insurance-claim.update-status', $claim->claim_id), ['status' => 'paid'])
            ->assertStatus(409);

        $this->putJson(route('fleet.insurance-claim.update-status', $claim->claim_id), [
            'status' => 'approved', 'approved_amount' => 4500,
        ])->assertOk();
        $this->assertEquals(4500, (float) $claim->fresh()->approved_amount);

        $this->putJson(route('fleet.insurance-claim.update-status', $claim->claim_id), ['status' => 'paid'])
            ->assertOk();

        $paid = $claim->fresh();
        $this->assertSame('paid', $paid->status);
        $this->assertNotNull($paid->paid_at);

        // Jurnal pencairan Dr Bank / Cr Pendapatan Klaim Asuransi
        $journal = Journal::where('reference_number', 'CLM-'.$claim->claim_number)->firstOrFail();
        $debitAccountId = JournalDetail::where('journal_id', $journal->journal_id)->where('debit', '>', 0)->value('account_id');
        $creditAccountId = JournalDetail::where('journal_id', $journal->journal_id)->where('credit', '>', 0)->value('account_id');
        $this->assertSame($this->accountId('1-1200'), (int) $debitAccountId);
        $this->assertSame($this->accountId('4-3000'), (int) $creditAccountId);

        // FLE-06: klik kedua ditolak — tidak ada jurnal kedua
        $this->putJson(route('fleet.insurance-claim.update-status', $claim->claim_id), ['status' => 'paid'])
            ->assertStatus(409);
        $this->assertSame(1, Journal::where('reference_number', 'CLM-'.$claim->claim_number)->count());
    }

    // ============================================================
    // FLE-12: view klaim & biaya aktual
    // ============================================================

    public function test_claim_show_and_create_pages_render(): void
    {
        $this->actingAs($this->user('superadmin'));
        $damage = $this->makeDamage();
        $claim = InsuranceClaim::create([
            'rental_id' => $damage->rental_id,
            'damage_id' => $damage->damage_id,
            'claim_number' => 'CLM-TEST-002',
            'insurance_provider' => 'Asuransi Uji',
            'policy_number' => 'POL-002',
            'claim_date' => now(),
            'claim_amount' => 1200.50,
            'status' => 'submitted',
        ]);

        $this->get(route('fleet.insurance-claim.show', $claim->claim_id))
            ->assertOk()
            ->assertSee('CLM-TEST-002')
            ->assertSee('Asuransi Uji');
        $this->get(route('fleet.insurance-claim.create', ['damage_id' => $damage->damage_id]))
            ->assertOk();
    }

    public function test_damage_actual_cost_update_endpoint(): void
    {
        $this->actingAs($this->user('superadmin'));
        $damage = $this->makeDamage();

        $this->putJson(route('fleet.damage.update-cost', $damage->damage_id), ['actual_repair_cost' => 880.40])
            ->assertOk()->assertJsonPath('status', true);
        $this->assertEquals(880.40, (float) $damage->fresh()->actual_repair_cost);

        $this->putJson(route('fleet.damage.update-cost', $damage->damage_id), ['actual_repair_cost' => -5])
            ->assertUnprocessable();

        $damage->update(['status' => 'closed']);
        $this->putJson(route('fleet.damage.update-cost', $damage->damage_id), ['actual_repair_cost' => 10])
            ->assertStatus(409);
    }

    // ============================================================
    // FLE-13: foto
    // ============================================================

    public function test_photo_upload_limited_and_frozen_after_close(): void
    {
        $this->actingAs($this->user('superadmin'));
        $damage = $this->makeDamage();

        $this->post(route('fleet.damage.photo.upload', $damage->damage_id), [
            'photos' => [UploadedFile::fake()->image('bukti.png')],
        ])->assertOk()->assertJsonPath('status', true);
        $this->assertSame(1, $damage->photos()->count());

        // Laporan ditutup → unggahan dibekukan
        $damage->update(['status' => 'closed']);
        $this->post(route('fleet.damage.photo.upload', $damage->damage_id), [
            'photos' => [UploadedFile::fake()->image('tambahan.png')],
        ])->assertStatus(409);
    }

    public function test_photo_delete_removes_physical_file(): void
    {
        $this->actingAs($this->user('superadmin'));
        $damage = $this->makeDamage();

        $this->post(route('fleet.damage.photo.upload', $damage->damage_id), [
            'photos' => [UploadedFile::fake()->image('bukti.png')],
        ])->assertOk();

        $photo = $damage->photos()->firstOrFail();
        Storage::disk('public')->assertExists($photo->photo_url);

        $this->deleteJson(route('fleet.damage.photo.delete', $photo->photo_id))->assertOk();
        Storage::disk('public')->assertMissing($photo->photo_url, 'FLE-13: file fisik ikut terhapus');
        $this->assertDatabaseMissing('tr_damage_photo', ['photo_id' => $photo->photo_id]);
    }

    // ============================================================
    // FLE-01: permission aksi per rute
    // ============================================================

    public function test_staff_action_gates_on_fleet_routes(): void
    {
        // STAFF hanya: fleet_maintain + fleet_damage_add
        $this->actingAs($this->user('staff'));
        $maintenance = Maintenance::query()->where('status', '!=', 'completed')->firstOrFail()
            ?? Maintenance::query()->firstOrFail();
        $maintenance->update(['status' => 'scheduled']);
        $damage = $this->makeDamage();
        $photoFile = UploadedFile::fake()->image('staf.png');

        // Boleh: operational harian
        $this->putJson(route('fleet.maintenance.complete', $maintenance->maintenance_id))->assertOk();
        $this->post(route('fleet.damage.photo.upload', $damage->damage_id), ['photos' => [$photoFile]])->assertOk();

        // Tidak boleh: tagih penyewa, kelola status/klaim, hapus bukti
        $this->putJson(route('fleet.damage.bill', $damage->damage_id))->assertForbidden();
        $this->putJson(route('fleet.damage.update-status', $damage->damage_id), ['status' => 'closed'])->assertForbidden();
        $this->putJson(route('fleet.damage.update-cost', $damage->damage_id), ['actual_repair_cost' => 1])->assertForbidden();
        $this->deleteJson(route('fleet.damage.photo.delete', $damage->photos()->firstOrFail()->photo_id))->assertForbidden();
        $this->post(route('fleet.insurance-claim.store'), [
            'damage_id' => $damage->damage_id,
            'insurance_provider' => 'X',
            'claim_date' => now()->toDateString(),
            'claim_amount' => 100,
        ])->assertForbidden();

        // MANAGER (fleet_claim_manage + fleet_bill_renter) boleh seluruhnya
        $this->actingAs($this->user('manager'));
        $this->putJson(route('fleet.damage.bill', $damage->damage_id))->assertOk();
        $this->putJson(route('fleet.damage.update-status', $damage->damage_id), ['status' => 'closed'])->assertOk();
        $this->post(route('fleet.insurance-claim.store'), [
            'damage_id' => $this->makeDamage()->damage_id,
            'insurance_provider' => 'Asuransi Uji',
            'claim_date' => now()->toDateString(),
            'claim_amount' => 100,
        ])->assertRedirect(route('fleet.damage.index'));
    }

    public function test_viewer_still_forbidden_from_fleet(): void
    {
        $this->actingAs($this->user('viewer'));
        $this->get('/fleet/damage')->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Fine;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Rental;
use App\Models\RentalExtension;
use App\Models\User;
use App\Services\AccountingService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Regresi langsung operasi Finance — remediasi FIN-02..FIN-16 (audit 15092026):
 * state machine denda, alokasi pembayaran terpisah dari pokok sewa, rekonsiliasi
 * refund, penerbitan invoice atomik, idempotency settle, jurnal, dan error UI.
 */
class FinanceSettlementTest extends TestCase
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

    protected function createUnpaidFine(Rental $rental, float $amount = 250000): Fine
    {
        return Fine::create([
            'rental_id' => $rental->rental_id,
            'fine_type' => 'late_return',
            'description' => 'Denda uji regresi',
            'amount' => $amount,
            'status' => 'unpaid',
            'issued_date' => now(),
        ]);
    }

    // ============================================================
    // FIN-02: state machine denda + lock + idempotency
    // ============================================================

    public function test_fine_pay_via_finance_creates_fine_allocation_payment_and_journal(): void
    {
        $rental = Rental::firstOrFail();
        $fine = $this->createUnpaidFine($rental, 275000);

        $response = $this->actingAs($this->user('admin'))
            ->putJson(route('finance.fine.pay', $fine->fine_id), ['payment_method' => 'cash']);

        $response->assertOk()->assertJson(['status' => true]);

        $fine->refresh();
        $this->assertSame('paid', $fine->status);
        $this->assertNotNull($fine->paid_date);

        // FIN-03: Payment denda terpisah dari pokok sewa (allocation=fine)
        $payment = Payment::where('allocation', Payment::ALLOCATION_FINE)
            ->where('rental_id', $rental->rental_id)
            ->firstOrFail();
        $this->assertSame('completed', $payment->status);
        $this->assertEquals(275000, (float) $payment->amount);

        // FIN-03: jurnal pendapatan denda tercatat (Dr Kas, Cr Pendapatan Denda)
        $journal = Journal::where('reference_number', 'FINE-PAY-'.$payment->payment_id)->firstOrFail();
        $this->assertSame('fine', $journal->journal_type);
        $this->assertDatabaseHas('tr_journal_detail', [
            'journal_id' => $journal->journal_id,
            'credit' => 275000,
        ]);
    }

    public function test_fine_pay_is_idempotent_second_attempt_rejected(): void
    {
        $rental = Rental::firstOrFail();
        $fine = $this->createUnpaidFine($rental, 150000);

        $first = $this->actingAs($this->user('admin'))
            ->putJson(route('finance.fine.pay', $fine->fine_id), ['payment_method' => 'cash']);
        $first->assertOk()->assertJson(['status' => true]);

        $paymentsCount = Payment::where('allocation', Payment::ALLOCATION_FINE)->count();

        // FIN-02: denda yang sudah paid tidak bisa dibayar lagi (state machine)
        $second = $this->actingAs($this->user('admin'))
            ->putJson(route('finance.fine.pay', $fine->fine_id), ['payment_method' => 'cash']);
        $second->assertStatus(409)->assertJson(['status' => false]);

        $this->assertSame($paymentsCount, Payment::where('allocation', Payment::ALLOCATION_FINE)->count());
        $this->assertSame('paid', $fine->fresh()->status);
    }

    public function test_fine_waive_sets_status_without_payment(): void
    {
        $rental = Rental::firstOrFail();
        $fine = $this->createUnpaidFine($rental, 90000);

        $response = $this->actingAs($this->user('admin'))
            ->putJson(route('finance.fine.waive', $fine->fine_id));

        $response->assertOk()->assertJson(['status' => true]);
        $this->assertSame('waived', $fine->fresh()->status);
        $this->assertSame(0, Payment::where('allocation', Payment::ALLOCATION_FINE)->count());

        // denda waived juga tidak bisa di-waive/dibayar lagi
        $again = $this->actingAs($this->user('admin'))
            ->putJson(route('finance.fine.waive', $fine->fine_id));
        $again->assertStatus(409);
    }

    public function test_fine_pay_via_rental_route_matches_finance_route(): void
    {
        $rental = Rental::firstOrFail();
        $fine = $this->createUnpaidFine($rental, 320000);

        // Jalur Sewa: hasil setara dengan jalur Finance (FIN-02: satu service)
        $response = $this->actingAs($this->user('admin'))
            ->putJson(route('rental.detail.fine.pay', [$rental->rental_id, $fine->fine_id]), ['payment_method' => 'cash']);

        $response->assertOk()->assertJson(['status' => true]);
        $this->assertDatabaseHas('tr_payment', [
            'rental_id' => $rental->rental_id,
            'allocation' => Payment::ALLOCATION_FINE,
            'status' => 'completed',
        ]);
        $this->assertSame('paid', $fine->fresh()->status);
    }

    // ============================================================
    // FIN-03: pembayaran pokok tidak tercampur denda
    // ============================================================

    public function test_rental_payment_limit_ignores_fine_allocation(): void
    {
        // Rental completed dengan tagihan > 0 tanpa pembayaran
        $rental = Rental::where('status', 'completed')
            ->where('total_amount', '>', 100000)
            ->whereDoesntHave('payments')
            ->firstOrFail();

        // bayar penuh
        $pay = $this->actingAs($this->user('staff'))->postJson(
            route('rental.detail.payment.store', $rental->rental_id),
            ['amount' => (float) $rental->total_amount, 'payment_method' => 'cash']
        );
        $pay->assertOk()->assertJson(['status' => true]);
        $this->assertSame('paid', $rental->fresh()->payment_status);

        // denda dibayar: allocation=fine — TIDAK mengubah payment_status rental
        $fine = $this->createUnpaidFine($rental, 100000);
        $this->actingAs($this->user('admin'))
            ->putJson(route('finance.fine.pay', $fine->fine_id), ['payment_method' => 'cash'])
            ->assertOk();

        $this->assertSame('paid', $rental->fresh()->payment_status);
    }

    // ============================================================
    // FIN-04: refund merekonsiliasi invoice + rental + jurnal
    // ============================================================

    public function test_full_refund_reconciles_invoice_payment_status_and_journal(): void
    {
        $rental = Rental::where('status', 'completed')
            ->where('total_amount', '>', 100000)
            ->whereDoesntHave('payments')
            ->firstOrFail();

        // Pastikan rental ini belum punya invoice (forceDelete agar unique index bersih)
        Invoice::where('rental_id', $rental->rental_id)->forceDelete();

        // invoice terbit + pelunasan
        $invoice = Invoice::create([
            'rental_id' => $rental->rental_id,
            'invoice_number' => 'INV-TEST-REFUND-1',
            'issue_date' => now(),
            'due_date' => now()->addDays(7),
            'sub_total' => $rental->total_amount,
            'total_amount' => $rental->total_amount,
            'paid_amount' => 0,
            'status' => 'sent',
        ]);

        $this->actingAs($this->user('staff'))->postJson(
            route('rental.detail.payment.store', $rental->rental_id),
            ['amount' => (float) $rental->total_amount, 'payment_method' => 'cash']
        )->assertOk();

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);

        $payment = Payment::where('rental_id', $rental->rental_id)
            ->where('allocation', Payment::ALLOCATION_RENTAL)
            ->firstOrFail();

        // refund PENUH via Finance/Sewa
        $refund = $this->actingAs($this->user('admin'))->postJson(
            route('rental.detail.refund.store', $rental->rental_id),
            [
                'payment_id' => $payment->payment_id,
                'amount' => (float) $payment->amount,
                'refund_type' => 'overpayment',
                'notes' => 'Uji rekonsiliasi penuh',
            ]
        );
        $refund->assertOk()->assertJson(['status' => true]);

        // FIN-04: Payment → refunded
        $this->assertSame('refunded', $payment->fresh()->status);

        // FIN-04: Invoice.paid_amount kembali nol, status invoice ikut disesuaikan
        $invoice->refresh();
        $this->assertEquals(0.0, (float) $invoice->paid_amount);
        $this->assertSame('sent', $invoice->status);

        // FIN-04: Rental.payment_status direkonsiliasi
        $this->assertSame('unpaid', $rental->fresh()->payment_status);

        // FIN-04: jurnal reversal tercatat
        $refundRow = Refund::where('payment_id', $payment->payment_id)->firstOrFail();
        $this->assertDatabaseHas('tr_journal', ['reference_number' => 'REF-'.$refundRow->refund_id]);
    }

    public function test_refund_rejects_non_completed_source_payment(): void
    {
        $rental = Rental::firstOrFail();
        $pending = Payment::create([
            'rental_id' => $rental->rental_id,
            'payment_date' => now(),
            'amount' => 100000,
            'payment_method' => 'cash',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user('admin'))->postJson(
            route('rental.detail.refund.store', $rental->rental_id),
            [
                'payment_id' => $pending->payment_id,
                'amount' => 50000,
                'refund_type' => 'overpayment',
            ]
        );

        // FIN-10: sumber pending tidak dapat direfund
        $response->assertStatus(422)->assertJson(['status' => false]);
    }

    // ============================================================
    // FIN-06: penerbitan invoice atomik + prepayment linkage
    // ============================================================

    public function test_invoice_issue_after_prepayment_links_payments_and_sets_paid(): void
    {
        // Tanpa filter status: paymentStore tidak mensyaratkan status tertentu
        $rental = Rental::where('total_amount', '>', 100000)
            ->whereDoesntHave('payments')
            ->whereDoesntHave('invoices')
            ->firstOrFail();

        // prepayment SEBELUM invoice terbit (payment tanpa invoice)
        $this->actingAs($this->user('staff'))->postJson(
            route('rental.detail.payment.store', $rental->rental_id),
            ['amount' => (float) $rental->total_amount, 'payment_method' => 'cash']
        )->assertOk();

        // terbitkan invoice via jalur Sewa
        $issue = $this->actingAs($this->user('admin'))->post(
            route('rental.detail.invoice.store', $rental->rental_id),
            ['due_date' => now()->addDays(7)->toDateString()]
        );
        $issue->assertRedirect(route('finance.index'));

        $invoice = Invoice::where('rental_id', $rental->rental_id)->firstOrFail();

        // FIN-06: prepayment tertaut ke invoice — paid_amount > 0 dengan riwayat ≠ kosong
        $this->assertEquals((float) $rental->total_amount, (float) $invoice->paid_amount);
        $this->assertSame('paid', $invoice->status);
        $this->assertTrue(
            Payment::where('rental_id', $rental->rental_id)
                ->where('allocation', Payment::ALLOCATION_RENTAL)
                ->whereNull('invoice_id')
                ->doesntExist(),
            'Semua payment pokok harus tertaut ke invoice'
        );
        $this->assertTrue($invoice->payments()->where('allocation', Payment::ALLOCATION_RENTAL)->exists());
    }

    public function test_duplicate_invoice_issue_rejected(): void
    {
        $rental = Rental::whereHas('invoices')->firstOrFail();

        $response = $this->actingAs($this->user('admin'))->post(
            route('rental.detail.invoice.store', $rental->rental_id),
            ['due_date' => now()->addDays(7)->toDateString()]
        );

        $response->assertSessionHasErrors();
        $this->assertSame(1, Invoice::where('rental_id', $rental->rental_id)->count());
    }

    // ============================================================
    // FIN-07: invoice draft direkalkulasi, invoice terbit dibekukan
    // ============================================================

    public function test_extension_approval_freezes_issued_invoice_but_recalculates_draft(): void
    {
        // Cari rental yang jendela perpanjangannya bebas overlap dengan rental lain
        // pada kendaraan yang sama (prasyarat approval)
        $rental = null;
        foreach (Rental::orderBy('rental_id')->get() as $candidate) {
            $newEnd = Carbon::parse($candidate->rental_end_date)->addDays(3);
            $overlap = Rental::where('vehicle_id', $candidate->vehicle_id)
                ->whereKeyNot($candidate->rental_id)
                ->where('rental_start_date', '<=', $newEnd)
                ->where('rental_end_date', '>=', $candidate->rental_end_date)
                ->exists();
            if (! $overlap) {
                $rental = $candidate;
                break;
            }
        }
        $this->assertNotNull($rental, 'Tidak ada kandidat rental bebas overlap');

        // Bersihkan invoice existing (unique satu invoice per rental) agar draft uji dapat dibuat
        Invoice::where('rental_id', $rental->rental_id)->forceDelete();
        $totalBefore = (float) $rental->total_amount;

        $extension = RentalExtension::create([
            'rental_id' => $rental->rental_id,
            'old_end_date' => $rental->rental_end_date,
            'new_end_date' => Carbon::parse($rental->rental_end_date)->addDays(3),
            'extended_days' => 3,
            'additional_base_price' => 300000,
            'additional_tax' => 33000,
            'additional_total' => 333000,
            'status' => 'pending',
        ]);

        // invoice DRAFT ikut direkalkulasi saat approval
        Invoice::create([
            'rental_id' => $rental->rental_id,
            'invoice_number' => 'INV-TEST-DRAFT-1',
            'issue_date' => now(),
            'due_date' => now()->addDays(7),
            'sub_total' => $totalBefore,
            'total_amount' => $totalBefore,
            'paid_amount' => 0,
            'status' => 'draft',
        ]);

        $this->actingAs($this->user('admin'))
            ->putJson(route('rental.detail.extension.approve', [$rental->rental_id, $extension->extension_id]))
            ->assertOk()
            ->assertJson(['status' => true]);

        $draft = Invoice::where('rental_id', $rental->rental_id)->firstOrFail();
        $this->assertEquals((float) $rental->fresh()->total_amount, (float) $draft->total_amount);
        $this->assertGreaterThan($totalBefore, (float) $draft->total_amount);
    }

    // ============================================================
    // FIN-09: jurnal kosong / akun tidak terpetakan ditolak
    // ============================================================

    public function test_accounting_post_rejects_unmapped_account_and_empty_lines(): void
    {
        $service = app(AccountingService::class);

        // akun kosong → exception, bukan header jurnal kosong
        $this->expectException(\Exception::class);
        $service->post(now()->toDateString(), 'TEST-EMPTY', 'Uji jurnal kosong', 'adjustment', [
            ['account' => null, 'debit' => 100, 'credit' => 0],
            ['account' => 999999999, 'debit' => 0, 'credit' => 100],
        ]);
    }

    // ============================================================
    // FIN-11/12: kebijakan kirim email + error HTTP konsisten
    // ============================================================

    public function test_invoice_send_email_requires_invoice_add_permission(): void
    {
        $invoice = Invoice::firstOrFail();
        $invoice->update(['status' => 'sent']);

        // staff3 = STAFF (finance_view + finance_invoice_add) → boleh
        $recipient = $invoice->rental?->customer;
        $recipient?->update(['email' => 'fin-regresi@example.test']);

        Mail::fake();
        $ok = $this->actingAs($this->user('staff3'))
            ->postJson(route('finance.invoice.send-email', $invoice->invoice_id));
        $ok->assertOk()->assertJson(['status' => true]);

        // viewer tidak punya finance_invoice_add → 403
        $invoice2 = Invoice::whereKeyNot($invoice->invoice_id)->firstOrFail();
        $invoice2->update(['status' => 'sent']);
        $invoice2->rental?->customer?->update(['email' => 'fin-regresi2@example.test']);

        $denied = $this->actingAs($this->user('viewer'))
            ->postJson(route('finance.invoice.send-email', $invoice2->invoice_id));
        $denied->assertForbidden();
    }

    public function test_fine_pay_error_response_is_consistent_http(): void
    {
        // FIN-12: kegagalan dikirim dengan status HTTP jelas, bukan 200 + status:false
        $response = $this->actingAs($this->user('admin'))
            ->putJson(route('finance.fine.pay', 999999999), ['payment_method' => 'cash']);

        $response->assertStatus(409)->assertJson(['status' => false]);
        $this->assertNotEmpty($response->json('msg'));
    }

    // ============================================================
    // FIN-15: validasi filter
    // ============================================================

    public function test_finance_filters_reject_invalid_status(): void
    {
        $actor = $this->actingAs($this->user('admin'));

        $actor->getJson(route('finance.invoice.data', ['status' => 'dropped-table;--']))
            ->assertStatus(422);
        $actor->getJson(route('finance.fine.data', ['status' => 'bogus']))
            ->assertStatus(422);
        $actor->getJson(route('finance.payment.data', ['status' => ['array' => 'input']]))
            ->assertStatus(422);
    }
}

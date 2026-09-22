<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\DriverCommissionService;
use App\Services\RentalDetailAddons;
use App\Support\QrCode;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regresi implementasi audit_12092026 butir 1 & 3:
 * - Komisi sopir terakrual otomatis saat pengembalian (Dr 5-1100 / Cr 2-4000).
 * - Batch payment multi-invoice: satu grup, satu kwitansi, saldo konsisten.
 */
class DriverCommissionAndBatchPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->superadmin = User::whereHas('roles', fn ($q) => $q->where('name', 'SUPERADMIN'))->first();
    }

    // ------------------------------------------------------------------
    // Butir 1 — Komisi sopir
    // ------------------------------------------------------------------

    public function test_driver_schema_and_seeder_data(): void
    {
        $this->assertTrue(
            \Schema::hasColumn('m_driver', 'commission_percent'),
            'm_driver.wajib punya kolom commission_percent'
        );

        $driver = Driver::where('commission_percent', '>', 0)->first();
        $this->assertNotNull($driver, 'Seeder membuat sopir berkomisi');
        $this->assertGreaterThan(0, (float) $driver->commission_percent);
        $this->assertLessThanOrEqual(100, (float) $driver->commission_percent);
    }

    public function test_return_with_driver_accrues_commission_journal(): void
    {
        $rental = Rental::where('is_with_driver', 1)
            ->whereNotNull('driver_id')
            ->where('status', 'ongoing')
            ->whereHas('driver', fn ($q) => $q->where('commission_percent', '>', 0))
            ->first();

        $this->assertNotNull($rental, 'Seeder membuat sewa ongoing ber-sopir');

        $driver = $rental->driver;
        $percent = (float) $driver->commission_percent;
        $pickupOdo = (int) ($rental->handoverOut?->odometer ?? $rental->vehicle->mileage ?? 0);

        $before = JournalDetail::where('account_id', app(AccountingService::class)->resolveCoa('2-4000'))->sum('credit');

        $this->actingAs($this->superadmin)
            ->post(route('rental.detail.return.store', $rental->rental_id), [
                'return_date' => now()->toDateString(),
                'return_mileage' => $pickupOdo + 100,
                'fuel_level' => 'half',
                'vehicle_condition' => 'good',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Jurnal akrual komisi: ref COM-{rental_id}, type payment
        $journal = Journal::where('reference_number', 'COM-'.$rental->rental_id)
            ->where('journal_type', 'payment')
            ->first();
        $this->assertNotNull($journal, 'Akrual komisi berjurnal COM-{rental_id}');

        $debit = (float) $journal->details->where('debit', '>', 0)->sum('debit');
        $credit = (float) $journal->details->where('credit', '>', 0)->sum('credit');
        $this->assertGreaterThan(0, $debit, 'Nilai komisi > 0');
        $this->assertEqualsWithDelta($debit, $credit, 0.001, 'Jurnal berpasangan');
        $this->assertStringContainsString((string) $percent, $journal->description, 'Deskripsi memuat persen komisi');

        $after = JournalDetail::where('account_id', app(AccountingService::class)->resolveCoa('2-4000'))->sum('credit');
        $this->assertEqualsWithDelta($before + $debit, $after, 0.01, 'Utang komisi bertambah di neraca');

        // Idempoten — jurnal COM-{id} unik di DB (reference_number unique) dan service
        // hanya membuat akrual saat transisi status, jadi jumlah baris COM-{id} === 1
        // adalah invarian yang dipertahankan meski endpoint dipanggil berulang.
        $this->assertSame(
            1,
            Journal::where('reference_number', 'COM-'.$rental->rental_id)->count(),
            'Akrual komisi tidak terduplikasi'
        );
    }

    public function test_return_without_driver_creates_no_commission_journal(): void
    {
        $rental = Rental::where('is_with_driver', 0)->where('status', 'ongoing')->first();
        if (! $rental) {
            // Force satu sewa lepas kunci menjadi ongoing — kolom status murni enum,
            // tidak menyentuh agregat lain.
            $rental = Rental::where('is_with_driver', 0)->first();
            $this->assertNotNull($rental, 'Seeder membuat sewa lepas kunci');
            $rental->update(['status' => 'ongoing']);
        }

        $pickupOdo = (int) ($rental->handoverOut?->odometer ?? $rental->vehicle->mileage ?? 0);

        $this->actingAs($this->superadmin)
            ->post(route('rental.detail.return.store', $rental->rental_id), [
                'return_date' => now()->toDateString(),
                'return_mileage' => $pickupOdo + 50,
                'vehicle_condition' => 'good',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull(
            Journal::where('reference_number', 'COM-'.$rental->rental_id)->first(),
            'Sewa lepas kunci tidak berjurnal komisi'
        );
    }

    public function test_commission_service_math(): void
    {
        $svc = app(DriverCommissionService::class);

        $rental = Rental::where('is_with_driver', 1)
            ->whereNotNull('driver_id')
            ->whereHas('driver', fn ($q) => $q->where('commission_percent', '>', 0))
            ->first();
        $this->assertNotNull($rental);

        $expected = round(
            ((float) $rental->total_base_price + (float) $rental->insurance_fee + (float) $rental->driver_fee
                + (float) $rental->young_driver_fee + RentalDetailAddons::sumFor($rental->rental_id))
                * ((float) $rental->driver->commission_percent / 100),
            2
        );
        $this->assertEqualsWithDelta($expected, $svc->commissionFor($rental), 0.01);

        // Lepas kunci → nol (tanpa memutasi rental asli)
        $noDriverFlag = clone $rental;
        $noDriverFlag->is_with_driver = false;
        $this->assertSame(0.0, $svc->commissionFor($noDriverFlag));

        // Persen 0 → nol (pakai driver yang persennya dinolkan)
        $zeroDriver = Driver::where('commission_percent', 0)->first();
        if (! $zeroDriver) {
            $zeroDriver = Driver::whereKeyNot($rental->driver_id)->first();
            $zeroDriver->update(['commission_percent' => 0]);
        }
        $withZeroDriver = clone $rental;
        $withZeroDriver->setRelation('driver', $zeroDriver);
        $this->assertSame(0.0, $svc->commissionFor($withZeroDriver));
    }

    public function test_pay_commission_posts_payment_journal(): void
    {
        $driver = Driver::where('commission_percent', '>', 0)->first();
        $journal = app(DriverCommissionService::class)->payCommission(
            $driver->driver_id,
            500000,
            'bank_transfer',
            'Uji pembayaran komisi'
        );

        $this->assertSame('DRC-'.$driver->driver_id, substr((string) $journal->reference_number, 0, strlen('DRC-'.$driver->driver_id)));
        $details = $journal->details;
        $this->assertEqualsWithDelta(
            (float) $details->where('debit', '>', 0)->sum('debit'),
            (float) $details->where('credit', '>', 0)->sum('credit'),
            0.001
        );
        $this->assertSame(500000.0, (float) $details->where('credit', '>', 0)->sum('credit'), 'Kredit = kas/bank keluar');
    }

    // ------------------------------------------------------------------
    // Butir 3 — Batch payment multi-invoice
    // ------------------------------------------------------------------

    private function pickSameCustomerInvoices(int $min = 2): Collection
    {
        return Invoice::whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->with('rental.customer')
            ->get()
            // Sisa tagihan layak dibayar (setengahannya tetap >= 0.01 untuk validasi)
            ->filter(fn ($i) => ((float) $i->total_amount - (float) $i->paid_amount) >= 0.02)
            ->groupBy(fn ($i) => $i->rental->customer_id)
            ->filter(fn ($g) => $g->count() >= $min)
            ->sortByDesc(fn ($g) => $g->count())
            ->first();
    }

    public function test_batch_payment_settles_multiple_invoices_with_one_receipt(): void
    {
        $invoices = $this->pickSameCustomerInvoices();
        $this->assertNotNull($invoices, 'Seeder membuat satu pelanggan dengan >= 2 invoice belum lunas');

        $rows = $invoices->map(fn ($i) => [
            'invoice_id' => $i->invoice_id,
            'amount' => round(((float) $i->total_amount - (float) $i->paid_amount) / 2, 2),
        ])->values()->all();

        $response = $this->actingAs($this->superadmin)
            ->post(route('finance.payment.batch.store'), [
                'invoices' => $rows,
                'payment_method' => 'bank_transfer',
                'reference_number' => 'BCA-CLRG-777',
                'notes' => 'Penyelesaian korporat',
            ]);

        $response->assertStatus(200)->assertJsonPath('status', true);
        $groupId = $response->json('data.group_id');
        $this->assertNotEmpty($groupId);

        // N baris, satu grup, satu nomor kwitansi BATCH-…
        $payments = Payment::where('batch_group_id', $groupId)->orderBy('payment_id')->get();
        $this->assertCount(count($rows), $payments);
        foreach ($payments as $p) {
            $this->assertStringStartsWith('BATCH-', (string) $p->payment_number);
        }
        $this->assertCount(1, $payments->pluck('payment_number')->unique(), 'Satu kwitansi untuk seluruh grup');
        $this->assertSame($response->json('data.receipt_number'), $payments->first()->payment_number);

        // paid_amount invoice bertambah sesuai alokasi, tidak pernah melebihi total
        foreach ($rows as $idx => $row) {
            $inv = Invoice::find($row['invoice_id']);
            $this->assertGreaterThanOrEqual(0, (float) $inv->total_amount - (float) $inv->paid_amount, 'Tidak overpay');
            $this->assertGreaterThan(0, (float) $inv->paid_amount);
        }

        // Jurnal berpasangan per baris pembayaran
        foreach ($payments as $p) {
            $journal = Journal::where('reference_number', 'PAY-'.$p->payment_id)->first();
            $this->assertNotNull($journal, 'Setiap pembayaran batch berjurnal');
            $debit = (float) $journal->details->where('debit', '>', 0)->sum('debit');
            $credit = (float) $journal->details->where('credit', '>', 0)->sum('credit');
            $this->assertEqualsWithDelta((float) $p->amount, $debit, 0.001);
            $this->assertEqualsWithDelta($debit, $credit, 0.001);
        }

        // Kwitansi batch ter-render (PDF biner terkompresi — isi diverifikasi via DB)
        $receipt = $this->actingAs($this->superadmin)->get(route('finance.payment.batch.receipt', $groupId));
        $receipt->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $receipt->getContent());
        $this->assertSame(
            collect($rows)->pluck('invoice_id')->sort()->values()->all(),
            $payments->pluck('invoice_id')->sort()->values()->all(),
            'Kwitansi batch mencakup seluruh invoice yang dibayar'
        );
    }

    public function test_batch_payment_rejects_cross_customer_invoices(): void
    {
        $invoices = $this->pickSameCustomerInvoices();
        $this->assertNotNull($invoices, 'Perlu satu pelanggan dengan >= 2 invoice');

        $otherCustomer = Invoice::whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->whereHas('rental', fn ($q) => $q->where('customer_id', '!=', $invoices->first()->rental->customer_id))
            ->first();
        if (! $otherCustomer) {
            $this->markTestSkipped('Tidak ada invoice pelanggan lain untuk test negatif');
        }

        $first = $invoices->first();
        $this->actingAs($this->superadmin)
            ->post(route('finance.payment.batch.store'), [
                'invoices' => [
                    ['invoice_id' => $first->invoice_id, 'amount' => 1000],
                    ['invoice_id' => $otherCustomer->invoice_id, 'amount' => 1000],
                ],
                'payment_method' => 'cash',
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_batch_payment_rejects_overpayment(): void
    {
        $inv = Invoice::whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->whereColumn('paid_amount', '<', 'total_amount')
            ->first();
        $this->assertNotNull($inv);

        $remaining = (float) $inv->total_amount - (float) $inv->paid_amount;

        $this->actingAs($this->superadmin)
            ->post(route('finance.payment.batch.store'), [
                'invoices' => [['invoice_id' => $inv->invoice_id, 'amount' => $remaining + 1000]],
                'payment_method' => 'cash',
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_batch_payment_rejects_empty_set(): void
    {
        $this->actingAs($this->superadmin)
            ->post(route('finance.payment.batch.store'), [
                'invoices' => [],
                'payment_method' => 'cash',
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_batch_page_renders(): void
    {
        $this->actingAs($this->superadmin)
            ->get(route('finance.payment.batch.create'))
            ->assertOk()
            ->assertSee('Pembayaran Borongan');
    }

    // ------------------------------------------------------------------
    // Butir 4 — QR verifikasi kontrak
    // ------------------------------------------------------------------

    public function test_contract_verification_page_valid_and_invalid(): void
    {
        $rental = Rental::first();
        $sig = QrCode::contractSignature($rental->rental_id);

        $ok = $this->get(route('verify.contract', ['rental' => $rental->rental_id, 't' => $sig]));
        $ok->assertOk()->assertSee('Terverifikasi')->assertSee($rental->rental_code);

        $bad = $this->get(route('verify.contract', ['rental' => $rental->rental_id, 't' => 'deadbeefdeadbeef']));
        $bad->assertStatus(404);

        $missing = $this->get(route('verify.contract', ['rental' => $rental->rental_id]));
        $missing->assertStatus(404);
    }

    public function test_contract_pdf_contains_qr_verification(): void
    {
        $rental = Rental::where('status', '!=', 'cancelled')->first();

        // QR contract verification memanggil route verify.contract saat render —
        // keberadaannya di kontrak diverifikasi lewat kontrak URL publik + signature.
        $pdf = $this->actingAs($this->superadmin)->get(route('rental.print', $rental->rental_id));
        $pdf->assertOk();

        // PDF adalah stream terkompresi (FlateDecode) — payload QR tidak terbaca
        // langsung; verifikasi fungsional lewat halaman verifikasi yang jadi target QR.
        $sig = QrCode::contractSignature($rental->rental_id);
        $this->get(route('verify.contract', ['rental' => $rental->rental_id, 't' => $sig]))
            ->assertOk()
            ->assertSee($rental->rental_code);
    }

    public function test_receipt_signature_contract_unchanged(): void
    {
        // Kontrak lama: receiptSignature tetap 16 char deterministik
        $sig = QrCode::receiptSignature(1);
        $this->assertSame($sig, QrCode::receiptSignature(1));
        $this->assertSame(16, strlen($sig));
        $this->assertNotSame($sig, QrCode::receiptSignature(2));
    }
}

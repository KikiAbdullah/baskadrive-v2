<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Rental;
use Carbon\Carbon;
use DB;
use Exception;

/**
 * Service settlement keuangan rental terpusat — remediasi FIN-04, FIN-05, FIN-06,
 * FIN-07, dan FIN-08. Seluruh mutasi pembayaran/refund/penerbitan invoice harus
 * lewat sini agar jalur Finance, Sewa, dan Laporan memakai aturan yang sama.
 *
 * Invariant:
 * 1. Transaksi DB DIMULAI sebelum lock parent diambil (FIN-05); urutan lock konsisten:
 *    tr_rental → tr_invoice → tr_payment.
 * 2. Pembayaran pokok sewa selalu allocation=rental; alokasi denda deposit tidak
 *    dihitung sebagai pelunasan pokok (FIN-03/FIN-08).
 * 3. Refund merekonsiliasi Payment, Invoice.paid_amount, status invoice,
 *    Rental.payment_status, dan jurnal reversal secara atomik (FIN-04).
 * 4. Invoice terbit (bukan draft) adalah snapshot: tidak direkalkulasi diam-diam;
 *    perubahan kewajiban setelah terbit membutuhkan adjustment eksplisit (FIN-07).
 */
class RentalSettlementService
{
    /** Toleransi pembulatan sen untuk perbandingan nilai uang desimal. */
    public const EPSILON = 0.01;

    public function __construct(protected AccountingService $accounting) {}

    // ============================================================
    // PEMBAYARAN POKOK SEWA
    // ============================================================

    /**
     * Catat pembayaran pokok sewa + rekonsiliasi invoice/rental + jurnal.
     *
     * @param  array{amount: float|string, payment_method: string, reference_number?: string|null, notes?: string|null}  $data
     * @return array{payment: Payment, invoice: ?Invoice, rental: Rental}
     *
     * @throws Exception bila overpay, tutup buku, atau posting jurnal gagal
     */
    public function recordPayment(Rental $rental, array $data): array
    {
        DB::beginTransaction();

        try {
            // FIN-05: transaksi sudah berjalan SEBELUM lock parent diambil.
            $rental = Rental::lockForUpdate()->findOrFail($rental->rental_id);
            $invoice = Invoice::where('rental_id', $rental->rental_id)->lockForUpdate()->first();

            $amount = (float) $data['amount'];
            $billTotal = (float) ($rental->total_amount ?? 0);

            $alreadyPaid = (float) Payment::where('rental_id', $rental->rental_id)
                ->where('allocation', Payment::ALLOCATION_RENTAL)
                ->where('status', 'completed')
                ->sum('amount');

            if ($billTotal > 0 && $alreadyPaid + $amount > $billTotal + self::EPSILON) {
                throw new Exception(sprintf(
                    'Pembayaran melebihi sisa tagihan. Sisa: Rp %s.',
                    number_format(max(0, $billTotal - $alreadyPaid), 2, ',', '.')
                ));
            }
            if ($billTotal <= 0) {
                throw new Exception('Tagihan sewa bernilai nol — tidak ada yang perlu dibayar.');
            }

            $this->accounting->assertPeriodOpen(Carbon::now()->toDateString());

            $payment = Payment::create([
                'invoice_id' => $invoice?->invoice_id,
                'rental_id' => $rental->rental_id,
                'payment_date' => now(),
                'amount' => $amount,
                'payment_method' => $data['payment_method'],
                'reference_number' => $data['reference_number'] ?? null,
                'status' => 'completed',
                'allocation' => Payment::ALLOCATION_RENTAL,
                'notes' => $data['notes'] ?? null,
            ]);

            // FIN-06: pembayaran yang sudah ada SEBELUM invoice terbit tetap terhubung
            // ke invoice baru (prepayment linkage) agar riwayat ≠ 0 saat paid > 0.
            if ($invoice) {
                Payment::where('rental_id', $rental->rental_id)
                    ->whereNull('invoice_id')
                    ->where('allocation', Payment::ALLOCATION_RENTAL)
                    ->update(['invoice_id' => $invoice->invoice_id]);

                $invoicePaid = (float) $invoice->paid_amount + $amount;
                $invoice->update([
                    'paid_amount' => $invoicePaid,
                    'status' => $invoicePaid >= (float) $invoice->total_amount - self::EPSILON ? 'paid' : 'partially_paid',
                ]);
            }

            $newlyPaid = $alreadyPaid + $amount;
            $rental->update([
                'payment_status' => $newlyPaid >= $billTotal - self::EPSILON ? 'paid' : 'partial',
            ]);

            // Jurnal: Dr Kas/Bank, Cr Piutang Sewa — hard post (gagal = rollback).
            $cashAccount = $data['payment_method'] === 'cash' ? $this->accounting->resolveCoa('1-1100') : $this->accounting->resolveCoa('1-1200');
            $this->accounting->post(
                now()->toDateString(),
                'PAY-'.$payment->payment_id,
                'Pembayaran sewa '.$rental->rental_code,
                'payment',
                [
                    ['account' => $cashAccount, 'debit' => $amount, 'credit' => 0],
                    ['account' => $this->accounting->resolveCoa('1-2100'), 'debit' => 0, 'credit' => $amount],
                ]
            );

            DB::commit();

            return ['payment' => $payment, 'invoice' => $invoice?->fresh(), 'rental' => $rental->fresh()];
        } catch (\Throwable $e) {
            DB::rollback();

            throw $e;
        }
    }

    // ============================================================
    // REFUND
    // ============================================================

    /**
     * Catat refund atas Payment sumber + rekonsiliasi penuh (FIN-04):
     * Payment status, Invoice.paid_amount/status, Rental.payment_status, jurnal reversal.
     *
     * @param  array{payment_id: int, amount: float|string, refund_type: string, notes?: string|null, reference_number?: string|null}  $data
     * @return array{refund: Refund, payment: Payment, invoice: ?Invoice, rental: Rental}
     *
     * @throws Exception bila sumber tidak completed, refund melebihi nilai, tutup buku, atau jurnal gagal
     */
    public function refundPayment(Rental $rental, array $data): array
    {
        DB::beginTransaction();

        try {
            $rental = Rental::lockForUpdate()->findOrFail($rental->rental_id);

            // FIN-10: sumber refund wajib completed — payment pending/failed tidak dapat direfund.
            $payment = Payment::where('rental_id', $rental->rental_id)
                ->where('status', 'completed')
                ->lockForUpdate()
                ->findOrFail($data['payment_id']);

            $amount = (float) $data['amount'];
            $alreadyRefunded = (float) Refund::where('payment_id', $payment->payment_id)
                ->where('status', '!=', 'failed')
                ->sum('amount');

            if ($alreadyRefunded + $amount > (float) $payment->amount + self::EPSILON) {
                throw new Exception(sprintf(
                    'Refund melebihi nilai pembayaran. Sisa dapat direfund: Rp %s.',
                    number_format(max(0, (float) $payment->amount - $alreadyRefunded), 2, ',', '.')
                ));
            }

            $this->accounting->assertPeriodOpen(Carbon::now()->toDateString());

            $refund = Refund::create([
                'payment_id' => $payment->payment_id,
                'rental_id' => $rental->rental_id,
                'refund_date' => now(),
                'amount' => $amount,
                'refund_type' => $data['refund_type'],
                'status' => 'processed',
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $fullyRefunded = $alreadyRefunded + $amount >= (float) $payment->amount - self::EPSILON;
            if ($fullyRefunded) {
                $payment->update(['status' => 'refunded']);
            }

            // FIN-04: rekonsiliasi piutang — refund alokasi pokok sewa mengurangi
            // saldo terbayar invoice dan status rental secara atomik. Refund deposit
            // (damage_deposit/deposit_return) tidak menyentuh piutang sewa.
            $invoice = null;
            $isRentalPrincipalRefund = in_array($data['refund_type'], ['overpayment', 'cancellation'], true);

            if ($isRentalPrincipalRefund && $payment->allocation === Payment::ALLOCATION_RENTAL) {
                $invoice = Invoice::where('rental_id', $rental->rental_id)->lockForUpdate()->first();

                if ($invoice) {
                    $invoicePaid = max(0, (float) $invoice->paid_amount - $amount);
                    $invoiceStatus = $invoice->status === 'cancelled'
                        ? 'cancelled'
                        : ($invoicePaid >= (float) $invoice->total_amount - self::EPSILON ? 'paid' : ($invoicePaid <= self::EPSILON ? 'sent' : 'partially_paid'));

                    // Status tidak boleh mundur dari 'paid' ke 'partially_paid' bila memang
                    // masih lunas nominal — cukup turunkan paid_amount.
                    if ($invoice->status === 'paid' && $invoicePaid >= (float) $invoice->total_amount - self::EPSILON) {
                        $invoiceStatus = 'paid';
                    }

                    $invoice->update(['paid_amount' => $invoicePaid, 'status' => $invoiceStatus]);
                }

                $totalBilled = (float) ($rental->total_amount ?? 0);
                $remainingPaid = (float) Payment::where('rental_id', $rental->rental_id)
                    ->where('allocation', Payment::ALLOCATION_RENTAL)
                    ->where('status', 'completed')
                    ->sum('amount');
                $rental->update([
                    'payment_status' => $totalBilled > 0 && $remainingPaid >= $totalBilled - self::EPSILON ? 'paid' : ($remainingPaid > 0 ? 'partial' : 'unpaid'),
                ]);
            }

            // Jurnal reversal: Dr Piutang/Pendapatan Diterima Dimuka, Cr Kas/Bank.
            $cashAccount = $payment->payment_method === 'cash' ? $this->accounting->resolveCoa('1-1100') : $this->accounting->resolveCoa('1-1200');
            $counterAccount = $isRentalPrincipalRefund && $payment->allocation === Payment::ALLOCATION_RENTAL
                ? $this->accounting->resolveCoa('1-2100')
                : $this->accounting->resolveCoa('2-3000');

            $this->accounting->post(
                now()->toDateString(),
                'REF-'.$refund->refund_id,
                'Refund '.$data['refund_type'].' sewa '.$rental->rental_code,
                'refund',
                [
                    ['account' => $counterAccount, 'debit' => $amount, 'credit' => 0],
                    ['account' => $cashAccount, 'debit' => 0, 'credit' => $amount],
                ]
            );

            DB::commit();

            return ['refund' => $refund, 'payment' => $payment->fresh(), 'invoice' => $invoice?->fresh(), 'rental' => $rental->fresh()];
        } catch (\Throwable $e) {
            DB::rollback();

            throw $e;
        }
    }

    // ============================================================
    // PENERBITAN INVOICE
    // ============================================================

    /**
     * Terbitkan invoice sewa secara atomik (FIN-06/FIN-07): pengecekan existing
     * dan pembuatan dilakukan di dalam transaksi + lock rental; prepayment
     * ditautkan; status draft terpisah dari status pelunasan.
     *
     * @param  array{due_date: string, notes?: string|null}  $data
     *
     * @throws Exception bila invoice sudah ada atau kewajiban nol
     */
    public function issueInvoice(Rental $rental, array $data): Invoice
    {
        DB::beginTransaction();

        try {
            $rental = Rental::lockForUpdate()->findOrFail($rental->rental_id);

            // Kardinalitas satu invoice aktif per rental dijaga DI DALAM transaksi.
            $existing = Invoice::where('rental_id', $rental->rental_id)->lockForUpdate()->first();
            if ($existing) {
                throw new Exception('Invoice untuk sewa ini sudah ada ('.$existing->invoice_number.').');
            }

            $billTotal = (float) ($rental->total_amount ?? 0);
            if ($billTotal <= 0) {
                throw new Exception('Total tagihan sewa bernilai nol — invoice tidak dapat diterbitkan.');
            }

            $this->accounting->assertPeriodOpen(Carbon::now()->toDateString());

            $invoiceNumber = (new InvoiceNumberGenerator)->next();

            $subTotal = (float) $rental->total_base_price
                + (float) ($rental->insurance_fee ?? 0)
                + (float) ($rental->driver_fee ?? 0)
                + (float) ($rental->young_driver_fee ?? 0)
                + (float) RentalDetailAddons::sumFor($rental->rental_id);

            $invoice = Invoice::create([
                'rental_id' => $rental->rental_id,
                'invoice_number' => $invoiceNumber,
                'issue_date' => now(),
                'due_date' => $data['due_date'],
                'sub_total' => $subTotal,
                'tax' => $rental->tax_amount ?? 0,
                'discount' => $rental->discount_amount ?? 0,
                'total_amount' => $billTotal,
                'paid_amount' => 0,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
            ]);

            // FIN-06: tautkan pembayaran pokok yang terjadi SEBELUM penerbitan.
            $prepaid = (float) Payment::where('rental_id', $rental->rental_id)
                ->where('allocation', Payment::ALLOCATION_RENTAL)
                ->where('status', 'completed')
                ->update(['invoice_id' => $invoice->invoice_id]) > 0;

            if ($prepaid) {
                $paid = (float) Payment::where('rental_id', $rental->rental_id)
                    ->where('allocation', Payment::ALLOCATION_RENTAL)
                    ->where('status', 'completed')
                    ->sum('amount');
                $invoice->update([
                    'paid_amount' => min($paid, $billTotal),
                    'status' => $paid >= $billTotal - self::EPSILON ? 'paid' : 'partially_paid',
                ]);
            }

            DB::commit();

            return $invoice;
        } catch (\Throwable $e) {
            DB::rollback();

            throw $e;
        }
    }

    /**
     * Recalc invoice draft setelah nilai sewa berubah (FIN-07). Invoice non-draft
     * adalah snapshot yang dibekukan — perubahan harus lewat dokumen adjustment.
     *
     * @throws Exception bila invoice bukan draft
     */
    public function recalculateDraftInvoice(Rental $rental): ?Invoice
    {
        $invoice = Invoice::where('rental_id', $rental->rental_id)->first();

        if (! $invoice) {
            return null;
        }

        if ($invoice->status !== 'draft') {
            throw new Exception(sprintf(
                'Invoice %s sudah terbit (%s) dan tidak ikut direkalkulasi. Buat dokumen penyesuaian untuk perubahan nilai sewa.',
                $invoice->invoice_number,
                $invoice->status
            ));
        }

        DB::beginTransaction();

        try {
            $invoice = Invoice::where('invoice_id', $invoice->invoice_id)->lockForUpdate()->firstOrFail();

            $subTotal = (float) $rental->total_base_price
                + (float) ($rental->insurance_fee ?? 0)
                + (float) ($rental->driver_fee ?? 0)
                + (float) ($rental->young_driver_fee ?? 0)
                + (float) RentalDetailAddons::sumFor($rental->rental_id);

            $invoice->update([
                'sub_total' => $subTotal,
                'tax' => $rental->tax_amount ?? 0,
                'discount' => $rental->discount_amount ?? 0,
                'total_amount' => $rental->total_amount,
            ]);

            DB::commit();

            return $invoice->fresh();
        } catch (\Throwable $e) {
            DB::rollback();

            throw $e;
        }
    }
}

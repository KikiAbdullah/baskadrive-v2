<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Rental;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Butir 2.5 audit_12092026 — kwitansi/batch payment multi-invoice untuk pelanggan
 * korporat: SATU pembayaran borongan melunasi banyak invoice sekaligus dan
 * menghasilkan SATU kwitansi yang bisa diverifikasi QR (tanda HMAC sama dengan
 * kwitansi tunggal, identitas `batch:<groupId>`).
 *
 * Invariant (mengikuti kontrak RentalSettlementService):
 * - Transaksi DB DIMULAI sebelum lock (FIN-05); urutan lock konsisten.
 * - `amount` diterapkan secara proporsional ke tiap invoice (FIFO hingga habis),
 *   bukan wajib exact-match total — pembayaran parsial antar-invoice sah.
 * - Rekonsiliasi penuh per invoice (paid_amount/status) dan per rental
 *   (payment_status) ATAU rollback seluruh batch (tidak ada batch setengah jadi).
 * - Jurnal hard post per rental: Dr Kas|Bank, Cr Piutang Sewa 1-2100 — gagal
 *   jurnal = rollback seluruh batch.
 */
class BatchPaymentService
{
    public function __construct(protected AccountingService $accounting) {}

    /**
     * Terima pembayaran borongan untuk banyak invoice korporat.
     *
     * @param  array<int, array{amount: float|string, payment_method: string, reference_number?: string|null, notes?: string|null}>  $invoices  amount = nilai yang DIBAYAR untuk invoice tsb.
     * @param  string  $paymentMethod  metode tunggal untuk seluruh batch
     * @param  string|null  $referenceNumber  nomor transfer/kliring bersama
     * @return array{group_id: string, total: float, payments: array<int, Payment>, invoices: array<int, Invoice>}
     *
     * @throws Exception bila invoice kosong/cancelled/tutup buku/overpay/jurnal gagal
     */
    public function payMany(array $invoices, string $paymentMethod, ?string $referenceNumber = null, ?string $notes = null): array
    {
        if (count($invoices) < 1) {
            throw new Exception('Minimal satu invoice harus dipilih.');
        }

        if (count($invoices) > 50) {
            throw new Exception('Maksimal 50 invoice per batch (pecah bila lebih).');
        }

        DB::beginTransaction();

        try {
            $groupId = (string) Str::uuid();
            $payments = [];
            $invoiceResults = [];
            $total = 0.0;

            // Butir 2.5 (korporat): SATU kwitansi per batch — seluruh baris berbagi
            // nomor kwitansi BATCH-XXXXXXXX (pembayaran tunggal tidak bernomor).
            $receiptNumber = 'BATCH-'.strtoupper(substr($groupId, 0, 8));

            // Lock seluruh rental induk DI AWAL untuk menghindari deadlock urutan lock
            // bila dua batch paralel menyentuh rental yang sama.
            $invoiceIds = collect($invoices)
                ->map(fn (array $row) => (int) ($row['invoice_id'] ?? 0))
                ->filter()
                ->values();

            $lockedRentals = Rental::whereIn('rental_id', Invoice::whereIn('invoice_id', $invoiceIds)->pluck('rental_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('rental_id');

            // Butir 2.5 (korporat): batch = satu pihak pembayar. Campuran pelanggan
            // dalam satu kwitansi menyesatkan penerimaan — ditolak sebelum apa pun ditulis.
            $customerIds = Invoice::whereIn('invoice_id', $invoiceIds)
                ->with('rental:rental_id,customer_id')
                ->get()
                ->pluck('rental.customer_id')
                ->unique();
            if ($customerIds->count() > 1) {
                throw new Exception('Pembayaran borongan hanya boleh untuk invoice milik SATU pelanggan. Pisahkan per pelanggan.');
            }

            foreach ($invoices as $row) {
                $invoiceId = (int) ($row['invoice_id'] ?? 0);
                $amount = round((float) ($row['amount'] ?? 0), 2);

                if ($amount <= 0) {
                    throw new Exception('Nominal per invoice harus positif.');
                }

                // Kunci baris invoice spesifik (setelah rental induk terkunci — urutan konsisten).
                $invoice = Invoice::where('invoice_id', $invoiceId)->lockForUpdate()->first();

                if (! $invoice) {
                    throw new Exception("Invoice #{$invoiceId} tidak ditemukan.");
                }

                if ($invoice->status === 'cancelled') {
                    throw new Exception("Invoice {$invoice->invoice_number} berstatus batal — tidak dapat dibayar.");
                }

                $rental = $lockedRentals->get($invoice->rental_id);
                if (! $rental) {
                    throw new Exception("Rental induk invoice {$invoice->invoice_number} tidak ditemukan.");
                }

                $remaining = (float) $invoice->total_amount - (float) $invoice->paid_amount;

                if ($amount > $remaining + RentalSettlementService::EPSILON) {
                    throw new Exception(sprintf(
                        'Pembayaran invoice %s melebihi sisa tagihan. Sisa: Rp %s.',
                        $invoice->invoice_number,
                        number_format(max(0, $remaining), 2, ',', '.')
                    ));
                }

                $this->accounting->assertPeriodOpen(Carbon::now()->toDateString());

                $payment = Payment::create([
                    'invoice_id' => $invoice->invoice_id,
                    'rental_id' => $invoice->rental_id,
                    'payment_date' => now(),
                    'amount' => $amount,
                    'payment_method' => $paymentMethod,
                    'reference_number' => $referenceNumber,
                    'status' => 'completed',
                    'allocation' => Payment::ALLOCATION_RENTAL,
                    'batch_group_id' => $groupId,
                    'payment_number' => $receiptNumber,
                    'notes' => $notes,
                ]);

                $invoicePaid = (float) $invoice->paid_amount + $amount;
                $invoice->update([
                    'paid_amount' => $invoicePaid,
                    'status' => $invoicePaid >= (float) $invoice->total_amount - RentalSettlementService::EPSILON ? 'paid' : 'partially_paid',
                ]);

                // Rekonsiliasi payment_status rental induk (kontrak settlement).
                $billed = (float) ($rental->total_amount ?? 0);
                $paidSum = (float) Payment::where('rental_id', $rental->rental_id)
                    ->where('allocation', Payment::ALLOCATION_RENTAL)
                    ->where('status', 'completed')
                    ->sum('amount');
                $rental->update([
                    'payment_status' => $billed > 0 && $paidSum >= $billed - RentalSettlementService::EPSILON ? 'paid' : ($paidSum > 0 ? 'partial' : 'unpaid'),
                ]);

                // Jurnal per rental: Dr Kas|Bank, Cr Piutang Sewa — hard post (rollback batch bila gagal).
                $cashCode = $paymentMethod === 'cash' ? '1-1100' : '1-1200';
                $this->accounting->post(
                    now()->toDateString(),
                    'PAY-'.$payment->payment_id,
                    'Pembayaran batch '.$groupId.' sewa '.$rental->rental_code,
                    'payment',
                    [
                        ['account' => $this->accounting->resolveCoa($cashCode), 'debit' => $amount, 'credit' => 0],
                        ['account' => $this->accounting->resolveCoa('1-2100'), 'debit' => 0, 'credit' => $amount],
                    ]
                );

                $payments[] = $payment;
                $invoiceResults[] = $invoice->fresh();
                $total += $amount;
            }

            DB::commit();

            return ['group_id' => $groupId, 'receipt_number' => $receiptNumber, 'total' => $total, 'payments' => $payments, 'invoices' => $invoiceResults];
        } catch (\Throwable $e) {
            DB::rollback();

            throw $e;
        }
    }

    /**
     * Ambil seluruh payment dalam satu batch + agregat untuk kwitansi.
     */
    public static function batchPayments(string $groupId): Collection
    {
        return Payment::query()
            ->where('batch_group_id', $groupId)
            ->with(['invoice', 'rental.customer', 'rental.vehicle.model.brand'])
            ->orderBy('payment_id')
            ->get();
    }
}

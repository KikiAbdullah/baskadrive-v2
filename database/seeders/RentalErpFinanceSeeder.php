<?php

namespace Database\Seeders;

use App\Models\Coa;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Rental;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class RentalErpFinanceSeeder extends Seeder
{
    private function journalEntry(?string $reference, string $description, string $type, array $lines, ?int $createdBy): void
    {
        $journal = Journal::create([
            'transaction_date' => Carbon::now(),
            'reference_number' => $reference,
            'description' => $description,
            'journal_type' => $type,
            'created_by' => $createdBy,
        ]);

        foreach ($lines as $line) {
            JournalDetail::create([
                'journal_id' => $journal->journal_id,
                'account_id' => $line['account'],
                'debit' => $line['debit'] ?? 0,
                'credit' => $line['credit'] ?? 0,
                'description' => $line['desc'] ?? null,
            ]);
        }
    }

    public function run(): void
    {
        $rentals = Rental::pluck('total_amount', 'rental_id')->toArray();
        $rentalByCode = Rental::pluck('rental_id', 'rental_code')->toArray();
        $accountant = \App\Models\Employee::where('username', 'akuntan')->value('employee_id');

        $coa = Coa::pluck('account_id', 'account_code')->toArray();
        $kasBesar = $coa['1-1100'] ?? null;
        $piutang = $coa['1-2100'] ?? null;
        $pendSewa = $coa['4-1100'] ?? null;
        $pendDenda = $coa['4-2000'] ?? null;
        $bebanServis = $coa['5-2100'] ?? null;
        $bebanBBM = $coa['5-1200'] ?? null;

        // ===========================================
        // 1. INVOICE
        // ===========================================
        $invoices = [
            // Invoice utk sewa yang sudah selesai & lunas (RNT-2026-0004)
            ['rental' => 'RNT-2026-0004', 'num' => 'INV-2026-0001', 'due' => Carbon::now()->subDays(3), 'status' => 'paid', 'paid_amount' => 0],
            // Invoice utk sewa 5 (RNT-2026-0005)
            ['rental' => 'RNT-2026-0005', 'num' => 'INV-2026-0002', 'due' => Carbon::now()->subDays(10), 'status' => 'paid', 'paid_amount' => 0],
            // Invoice utk sewa 8 (RNT-2026-0008)
            ['rental' => 'RNT-2026-0008', 'num' => 'INV-2026-0003', 'due' => Carbon::now()->subDays(18), 'status' => 'paid', 'paid_amount' => 0],
            // Invoice utk sewa 2 (ongoing, partial) — sebagian dibayar
            ['rental' => 'RNT-2026-0002', 'num' => 'INV-2026-0004', 'due' => Carbon::now()->addDays(4), 'status' => 'partially_paid', 'paid_amount' => 0],
            // Invoice utk sewa 7 (overdue, unpaid)
            ['rental' => 'RNT-2026-0007', 'num' => 'INV-2026-0005', 'due' => Carbon::now()->subDays(3), 'status' => 'overdue', 'paid_amount' => 0],
            // Invoice utk sewa 1 (ongoing, unpaid)
            ['rental' => 'RNT-2026-0001', 'num' => 'INV-2026-0006', 'due' => Carbon::now()->addDays(1), 'status' => 'sent', 'paid_amount' => 0],
        ];

        $invoiceMap = [];
        foreach ($invoices as $inv) {
            $rentalId = $rentalByCode[$inv['rental']];
            $total = $rentals[$rentalId];
            $invoice = Invoice::create([
                'rental_id' => $rentalId,
                'invoice_number' => $inv['num'],
                'issue_date' => Carbon::now()->subDays(5),
                'due_date' => $inv['due'],
                'sub_total' => $total,
                'tax' => 0,
                'discount' => 0,
                'total_amount' => $total,
                'paid_amount' => $inv['paid_amount'],
                'status' => $inv['status'],
                'notes' => null,
            ]);
            $invoiceMap[$inv['rental']] = $invoice->invoice_id;

            // Jurnal penjualan: Dr Piutang Sewa, Cr Pendapatan Sewa
            $this->journalEntry(
                'JRNL-'.substr($inv['num'], 4),
                'Pencatatan invoice '.$inv['num'],
                'rental',
                [
                    ['account' => $piutang, 'debit' => $total, 'credit' => 0, 'desc' => 'Piutang sewa'],
                    ['account' => $pendSewa, 'debit' => 0, 'credit' => $total, 'desc' => 'Pendapatan sewa'],
                ],
                $accountant
            );
        }

        // ===========================================
        // 2. PAYMENT
        // ===========================================
        $payments = [
            ['rental' => 'RNT-2026-0004', 'inv' => 'RNT-2026-0004', 'date' => Carbon::now()->subDays(5), 'amount' => $rentals[$rentalByCode['RNT-2026-0004']], 'method' => 'bank_transfer', 'ref' => 'TRF-2026-001', 'status' => 'completed'],
            ['rental' => 'RNT-2026-0005', 'inv' => 'RNT-2026-0005', 'date' => Carbon::now()->subDays(11), 'amount' => $rentals[$rentalByCode['RNT-2026-0005']], 'method' => 'cash', 'ref' => null, 'status' => 'completed'],
            ['rental' => 'RNT-2026-0008', 'inv' => 'RNT-2026-0008', 'date' => Carbon::now()->subDays(19), 'amount' => $rentals[$rentalByCode['RNT-2026-0008']], 'method' => 'e_wallet', 'ref' => 'EW-2026-001', 'status' => 'completed'],
            ['rental' => 'RNT-2026-0002', 'inv' => 'RNT-2026-0002', 'date' => Carbon::now()->subDays(1), 'amount' => round($rentals[$rentalByCode['RNT-2026-0002']] * 0.5, 2), 'method' => 'cash', 'ref' => null, 'status' => 'completed'],
            ['rental' => 'RNT-2026-0006', 'inv' => null, 'date' => Carbon::now()->subDays(3), 'amount' => 100000, 'method' => 'cash', 'ref' => null, 'status' => 'refunded'],
        ];

        $paymentMap = [];
        $payCounter = 0;
        foreach ($payments as $p) {
            $payment = Payment::create([
                'invoice_id' => $p['inv'] ? ($invoiceMap[$p['inv']] ?? null) : null,
                'rental_id' => $rentalByCode[$p['rental']],
                'payment_date' => $p['date'],
                'amount' => $p['amount'],
                'payment_method' => $p['method'],
                'reference_number' => $p['ref'],
                'status' => $p['status'],
                'notes' => null,
            ]);
            $paymentMap[$p['rental']] = $payment->payment_id;

            $payCounter++;
            $ref = $p['ref'] ?: 'PAY-'.str_pad((string) $payCounter, 3, '0', STR_PAD_LEFT);

            // Jurnal penerimaan: Dr Kas, Cr Piutang
            $this->journalEntry(
                'JRNL-PAY-'.$ref,
                'Penerimaan pembayaran sewa '.$p['rental'],
                'payment',
                [
                    ['account' => $kasBesar, 'debit' => $p['amount'], 'credit' => 0, 'desc' => 'Kas masuk'],
                    ['account' => $piutang, 'debit' => 0, 'credit' => $p['amount'], 'desc' => 'Pengurangan piutang'],
                ],
                $accountant
            );
        }

        // Update invoice paid_amount berdasarkan total payment (approximation utk demo)
        foreach (['RNT-2026-0004', 'RNT-2026-0005', 'RNT-2026-0008'] as $code) {
            if (isset($invoiceMap[$code])) {
                $inv = Invoice::find($invoiceMap[$code]);
                $inv->update(['paid_amount' => $inv->total_amount, 'status' => 'paid']);
            }
        }
        if (isset($invoiceMap['RNT-2026-0002'])) {
            Invoice::find($invoiceMap['RNT-2026-0002'])->update(['status' => 'partially_paid']);
        }

        // ===========================================
        // 3. REFUND
        // ===========================================
        $refunds = [
            // Sewa 6 (cancelled): refund uang muka
            ['rental' => 'RNT-2026-0006', 'payment' => 'RNT-2026-0006', 'date' => Carbon::now()->subDays(2), 'amount' => 100000, 'type' => 'cancellation', 'ref' => 'REF-2026-001', 'status' => 'processed'],
        ];

        foreach ($refunds as $rf) {
            $refund = Refund::create([
                'payment_id' => $paymentMap[$rf['payment']] ?? null,
                'rental_id' => $rentalByCode[$rf['rental']],
                'refund_date' => $rf['date'],
                'amount' => $rf['amount'],
                'refund_type' => $rf['type'],
                'status' => $rf['status'],
                'reference_number' => $rf['ref'],
                'notes' => null,
            ]);
        }

        // Jurnal refund (di luar create utk clarity): Dr Kas, Cr Piutang (reversal)
        $this->journalEntry(
            'JRNL-REF-2026-001',
            'Pengembalian uang muka sewa dibatalkan',
            'refund',
            [
                ['account' => $piutang, 'debit' => 0, 'credit' => 100000, 'desc' => 'Reversal piutang'],
                ['account' => $kasBesar, 'debit' => 100000, 'credit' => 0, 'desc' => 'Kas keluar refund'],
            ],
            $accountant
        );

        // ===========================================
        // 4. JURNAL MAINTENANCE & DENDA (contoh)
        // ===========================================
        $this->journalEntry(
            'JRNL-MNT-2026-001',
            'Biaya maintenance ganti oli B 7890 MNO',
            'maintenance',
            [
                ['account' => $bebanServis, 'debit' => 550000, 'credit' => 0, 'desc' => 'Beban servis'],
                ['account' => $kasBesar, 'debit' => 0, 'credit' => 550000, 'desc' => 'Kas keluar'],
            ],
            $accountant
        );

        $this->journalEntry(
            'JRNL-FINE-2026-001',
            'Denda telat pengembalian RNT-2026-0005',
            'fine',
            [
                ['account' => $piutang, 'debit' => 350000, 'credit' => 0, 'desc' => 'Piutang denda'],
                ['account' => $pendDenda, 'debit' => 0, 'credit' => 350000, 'desc' => 'Pendapatan denda'],
            ],
            $accountant
        );

        $this->command->info(
            'RentalErpFinanceSeeder selesai: '.count($invoices).' invoice, '.count($payments).' payment, '
            .count($refunds).' refund, '.Journal::count().' journal.'
        );
    }
}
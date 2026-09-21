<?php

namespace App\Services;

use App\Models\DamageReport;
use App\Models\Fine;
use App\Models\Journal;
use App\Models\Payment;
use App\Models\Rental;
use Carbon\Carbon;
use DB;
use Exception;

/**
 * SATU service settlement denda untuk jalur Finance (/finance/fine/...) dan
 * Sewa (/rental/{rental}/fine/...) — remediasi FIN-02 & FIN-03.
 *
 * Perluasan FLE-08 (audit Fleet): denda yang lahir dari penagihan kerusakan
 * (fine_type=damage, tr_fine.damage_id terisi) diakui secara akrual — saat
 * ditagih, piutang sudah dibukukan (Dr 1-2100 / Cr 4-2000). Karena itu saat
 * dibayar, kas masuk MELUNASI piutang (Dr Kas, Cr Piutang), bukan
 * menggandakan pendapatan denda.
 *
 * Invariant:
 * 1. Satu denda hanya dapat settle sekali: transisi unpaid → paid|waived dijaga
 *    oleh predicate status pada SELECT ... FOR UPDATE di dalam transaksi.
 * 2. Denda yang sudah paid tidak boleh diubah menjadi waived tanpa reversal
 *    pembayaran terlebih dahulu (koreksi harus lewat jurnal manual/refund).
 * 3. Pembayaran denda selalu membuat Payment dengan allocation=fine sehingga
 *    tidak tercampur dengan pelunasan pokok sewa pada perhitungan settlement.
 * 4. Jurnal dibuat atomik dengan perubahan status; gagal posting = gagal transaksi.
 */
class FineSettlementService
{
    public const JOURNAL_TYPE = 'fine';

    public function __construct(protected AccountingService $accounting) {}

    /**
     * Bayar denda: unpaid → paid + Payment(allocation=fine) + jurnal.
     *
     * Denda kerusakan (akrual): Dr Kas/Bank, Cr Piutang Sewa — melunasi piutang
     * yang dibukukan saat penagihan (FLE-08).
     * Denda biasa (kas): Dr Kas/Bank, Cr Pendapatan Denda 4-2000 (FIN-03).
     *
     * @param  int  $fineId  ID denda
     * @param  int|null  $rentalId  opsional — wajib cocok bila dipanggil dari jalur Sewa
     * @param  array{payment_method?: string, reference_number?: string|null, notes?: string|null}  $data
     * @return array{fine: Fine, payment: Payment}
     *
     * @throws Exception bila state tidak valid, tutup buku, atau posting jurnal gagal
     */
    public function pay(int $fineId, ?int $rentalId = null, array $data = []): array
    {
        DB::beginTransaction();

        try {
            // Lock di DALAM transaksi + predicate state asal (FIN-02):
            // dua request paralel → satu dapat lock, satu lagi membaca status yang sudah berubah.
            $fineQuery = Fine::where('status', 'unpaid')->lockForUpdate();
            if ($rentalId !== null) {
                $fineQuery->where('rental_id', $rentalId);
            }
            $fine = $fineQuery->findOrFail($fineId);
            $rentalId = (int) $fine->rental_id;

            $amount = (float) $fine->amount;
            if ($amount <= 0) {
                throw new Exception('Nilai denda tidak valid untuk dibayar.');
            }

            $method = $data['payment_method'] ?? 'cash';
            $this->accounting->assertPeriodOpen(Carbon::now()->toDateString());

            $payment = Payment::create([
                'rental_id' => $rentalId,
                'payment_date' => now(),
                'amount' => $amount,
                'payment_method' => $method,
                'reference_number' => $data['reference_number'] ?? null,
                'status' => 'completed',
                'allocation' => Payment::ALLOCATION_FINE,
                'notes' => $data['notes'] ?? 'Pembayaran denda: '.$fine->description,
            ]);

            // Simpan identitas petugas pada paid_by — issued_by tetap penerbit denda (FIN-02).
            $fine->update([
                'status' => 'paid',
                'paid_date' => now(),
                'paid_by' => auth()->user()->employee_id ?? null,
            ]);

            $cashAccount = $method === 'cash' ? $this->accounting->resolveCoa('1-1100') : $this->accounting->resolveCoa('1-1200');

            if ($fine->damage_id !== null) {
                // FLE-08: denda kerusakan sudah diakui sebagai piutang saat ditagih —
                // pembayaran melunasi piutang, pendapatan tidak dihitung dua kali.
                $this->accounting->post(
                    now()->toDateString(),
                    'FINE-PAY-'.$payment->payment_id,
                    'Pembayaran tagihan kerusakan #'.$fine->damage_id.' sewa '.($fine->rental?->rental_code ?? '#'.$rentalId),
                    self::JOURNAL_TYPE,
                    [
                        ['account' => $cashAccount, 'debit' => $amount, 'credit' => 0],
                        ['account' => $this->accounting->resolveCoa('1-2100'), 'debit' => 0, 'credit' => $amount],
                    ]
                );
            } else {
                // Jurnal pendapatan denda: kas masuk adalah PENDAPATAN DENDA (denda ditagihkan
                // ke penyewa), bukan pengurangan piutang sewa (FIN-03).
                $this->accounting->post(
                    now()->toDateString(),
                    'FINE-PAY-'.$payment->payment_id,
                    'Pembayaran denda '.$fine->fine_type.' sewa '.($fine->rental?->rental_code ?? '#'.$rentalId),
                    self::JOURNAL_TYPE,
                    [
                        ['account' => $cashAccount, 'debit' => $amount, 'credit' => 0],
                        ['account' => $this->accounting->resolveCoa('4-2000'), 'debit' => 0, 'credit' => $amount],
                    ]
                );
            }

            DB::commit();

            return ['fine' => $fine, 'payment' => $payment];
        } catch (\Throwable $e) {
            DB::rollback();

            throw $e;
        }
    }

    /**
     * Bebaskan denda: unpaid → waived (tanpa kas).
     * FLE-08: denda kerusakan berakrual yang dibebaskan wajib membalik piutangnya
     * (Dr Pendapatan Denda, Cr Piutang) agar piutang tidak menggantung abadi.
     *
     * @param  int  $fineId  ID denda
     * @param  int|null  $rentalId  opsional — wajib cocok bila dipanggil dari jalur Sewa
     *
     * @throws Exception bila denda sudah settle atau tutup buku
     */
    public function waive(int $fineId, ?int $rentalId = null): Fine
    {
        DB::beginTransaction();

        try {
            $fineQuery = Fine::where('status', 'unpaid')->lockForUpdate();
            if ($rentalId !== null) {
                $fineQuery->where('rental_id', $rentalId);
            }
            $fine = $fineQuery->findOrFail($fineId);

            $this->accounting->assertPeriodOpen(Carbon::now()->toDateString());

            $fine->update([
                'status' => 'waived',
                'waived_by' => auth()->user()->employee_id ?? null,
                'waived_at' => now(),
            ]);

            if ($fine->damage_id !== null) {
                $amount = (float) $fine->amount;
                $this->accounting->post(
                    now()->toDateString(),
                    'FINE-WV-'.$fine->fine_id,
                    'Pembatalan tagihan kerusakan #'.$fine->damage_id.' sewa '.($fine->rental?->rental_code ?? '#'.$fine->rental_id),
                    self::JOURNAL_TYPE,
                    [
                        ['account' => $this->accounting->resolveCoa('4-2000'), 'debit' => $amount, 'credit' => 0],
                        ['account' => $this->accounting->resolveCoa('1-2100'), 'debit' => 0, 'credit' => $amount],
                    ]
                );
            }

            DB::commit();

            return $fine;
        } catch (\Throwable $e) {
            DB::rollback();

            throw $e;
        }
    }

    /**
     * Koreksi denda yang SUDAH dibayar menjadi waived: wajib mereversal Payment
     * terlebih dahulu (status failed + jurnal reversal) sebelum status diubah.
     * Taxonomi akuntansi: reversal dibuat sebagai Journal berpasangan, bukan penghapusan.
     * FLE-08: reversal denda kerusakan membalik piutang (bukan pendapatan).
     */
    public function waivePaidWithReversal(Fine $fine, string $reason): array
    {
        DB::beginTransaction();

        try {
            $fine = Fine::where('fine_id', $fine->fine_id)->lockForUpdate()->firstOrFail();

            if ($fine->status !== 'paid') {
                throw new Exception('Koreksi reversal hanya berlaku untuk denda berstatus paid.');
            }

            $payment = Payment::where('allocation', Payment::ALLOCATION_FINE)
                ->where('rental_id', $fine->rental_id)
                ->where('status', 'completed')
                ->orderByDesc('payment_id')
                ->lockForUpdate()
                ->first();

            if ($payment && (float) $payment->amount === (float) $fine->amount) {
                $payment->update(['status' => 'failed', 'notes' => trim(($payment->notes ?? '').' | Reversal: '.$reason)]);

                $cashAccount = $payment->payment_method === 'cash' ? $this->accounting->resolveCoa('1-1100') : $this->accounting->resolveCoa('1-1200');

                if ($fine->damage_id !== null) {
                    // FLE-08: kas keluar membalik pelunasan piutang.
                    $this->accounting->post(
                        now()->toDateString(),
                        'FINE-REV-'.$payment->payment_id,
                        'Reversal pembayaran tagihan kerusakan #'.$fine->damage_id.' sewa '.($fine->rental?->rental_code ?? '#'.$fine->rental_id).': '.$reason,
                        self::JOURNAL_TYPE,
                        [
                            ['account' => $this->accounting->resolveCoa('1-2100'), 'debit' => $payment->amount, 'credit' => 0],
                            ['account' => $cashAccount, 'debit' => 0, 'credit' => $payment->amount],
                        ]
                    );
                } else {
                    $this->accounting->post(
                        now()->toDateString(),
                        'FINE-REV-'.$payment->payment_id,
                        'Reversal pembayaran denda sewa '.($fine->rental?->rental_code ?? '#'.$fine->rental_id).': '.$reason,
                        self::JOURNAL_TYPE,
                        [
                            ['account' => $this->accounting->resolveCoa('4-2000'), 'debit' => $payment->amount, 'credit' => 0],
                            ['account' => $cashAccount, 'debit' => 0, 'credit' => $payment->amount],
                        ]
                    );
                }
            }

            $fine->update([
                'status' => 'waived',
                'waived_by' => auth()->user()->employee_id ?? null,
                'waived_at' => now(),
            ]);

            DB::commit();

            return ['fine' => $fine, 'payment' => $payment];
        } catch (\Throwable $e) {
            DB::rollback();

            throw $e;
        }
    }

    /**
     * FLE-08: jurnal akrual saat kerusakan ditagihkan ke penyewa — dipanggil dari
     * FleetController::damageBillRenter dalam transaksi yang sama dengan pembuatan Fine.
     *
     * Dr Piutang Sewa (1-2100), Cr Pendapatan Denda (4-2000).
     */
    public function accrueDamageCharge(Fine $fine, DamageReport $damage): Journal
    {
        $amount = (float) $fine->amount;
        if ($amount <= 0) {
            throw new Exception('Nilai tagihan kerusakan tidak valid.');
        }

        return $this->accounting->post(
            now()->toDateString(),
            'DMG-FINE-'.$fine->fine_id,
            'Akrual tagihan kerusakan #'.$damage->damage_id.' sewa '.($fine->rental?->rental_code ?? '#'.$fine->rental_id),
            self::JOURNAL_TYPE,
            [
                ['account' => $this->accounting->resolveCoa('1-2100'), 'debit' => $amount, 'credit' => 0],
                ['account' => $this->accounting->resolveCoa('4-2000'), 'debit' => 0, 'credit' => $amount],
            ]
        );
    }

    /**
     * FLE-08: jurnal pembatalan tagihan kerusakan yang belum dibayar — membalik
     * akrual piutang. Taxonomi: Dr Pendapatan Denda, Cr Piutang Sewa.
     */
    public function reverseDamageCharge(Fine $fine): Journal
    {
        $amount = (float) $fine->amount;
        if ($amount <= 0) {
            throw new Exception('Nilai tagihan kerusakan tidak valid.');
        }

        return $this->accounting->post(
            now()->toDateString(),
            'DMG-FINE-REV-'.$fine->fine_id,
            'Pembatalan tagihan kerusakan #'.$fine->damage_id.' sewa '.($fine->rental?->rental_code ?? '#'.$fine->rental_id),
            self::JOURNAL_TYPE,
            [
                ['account' => $this->accounting->resolveCoa('4-2000'), 'debit' => $amount, 'credit' => 0],
                ['account' => $this->accounting->resolveCoa('1-2100'), 'debit' => 0, 'credit' => $amount],
            ]
        );
    }
}

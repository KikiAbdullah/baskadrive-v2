<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\Rental;
use Carbon\Carbon;
use Exception;

/**
 * Butir 2.2.5 audit_12092026 — komisi/upah sopir masuk akuntansi.
 *
 * Semantik: `m_driver.commission_percent` (0-100, 10 = 10%) diproportikan ke
 * komponen sewa murni (dasar + asuransi + sopir + young driver + add-on),
 * TIDAK termasuk PPN dan tidak terpengaruh diskon promo (kepentingan sopir
 * dipertahankan penuh meski perusahaan memberi diskon).
 *
 * Akrual saat pengembalian (hari kerja sopir selesai):
 *   Dr 5-1100 Beban Gaji   / Cr 2-4000 Utang Komisi Sopir
 * Pelunasan pembayaran komisi:
 *   Dr 2-4000 / Cr Kas|Bank (type 'payment', ref DRC-*)
 *
 * Semua posting hard (gagal = rollback transaksi pemanggil) dan tunduk tutup
 * buku `assertPeriodOpen` — konsisten dengan seluruh service keuangan.
 */
class DriverCommissionService
{
    public const JOURNAL_TYPE = 'payment';

    public function __construct(protected AccountingService $accounting) {}

    /**
     * Komisi yang layak untuk satu rental — bila > 0, terutang diakrual.
     */
    public function commissionFor(Rental $rental): float
    {
        if (! $rental->is_with_driver || ! $rental->driver_id || (float) ($rental->driver_fee ?? 0) <= 0) {
            return 0.0;
        }

        $percent = (float) ($rental->driver?->commission_percent ?? 0);
        if ($percent <= 0) {
            return 0.0;
        }

        $base = (float) $rental->total_base_price
            + (float) ($rental->insurance_fee ?? 0)
            + (float) $rental->driver_fee
            + (float) ($rental->young_driver_fee ?? 0)
            + (float) RentalDetailAddons::sumFor($rental->rental_id);

        return round($base * ($percent / 100), 2);
    }

    /**
     * Akrual komisi saat pengembalian. Dipanggil dalam transaksi pengembalian.
     * Referensi COM-{rental_id} unik (index unique tr_journal) — pengembalian
     * ulang/ulang panggil tidak menggandakan akrual.
     */
    public function accrueOnReturn(Rental $rental, string $returnDate): ?Journal
    {
        $amount = $this->commissionFor($rental);

        if ($amount <= 0) {
            return null;
        }

        $driverName = $rental->driver?->full_name ?? 'Sopir';

        return $this->accounting->post(
            $returnDate,
            'COM-'.$rental->rental_id,
            sprintf('Akrual komisi sopir %s sewa %s (%.2f%%)', $driverName, $rental->rental_code, (float) ($rental->driver?->commission_percent ?? 0)),
            self::JOURNAL_TYPE,
            [
                ['account' => $this->accounting->resolveCoa('5-1100'), 'debit' => $amount, 'credit' => 0],
                ['account' => $this->accounting->resolveCoa('2-4000'), 'debit' => 0, 'credit' => $amount],
            ]
        );
    }

    /**
     * Pembayaran komisi ke sopir (fitur payroll manual): Dr 2-4000, Cr Kas|Bank.
     *
     * @throws Exception bila tutup buku atau jurnal gagal
     */
    public function payCommission(int $driverId, float $amount, string $method, ?string $note = null, ?string $date = null): Journal
    {
        if ($amount <= 0) {
            throw new Exception('Nominal pembayaran komisi harus positif.');
        }

        $driver = Driver::findOrFail($driverId);
        $cashCode = $method === 'cash' ? '1-1100' : '1-1200';

        return $this->accounting->post(
            $date ?? Carbon::now()->toDateString(),
            'DRC-'.$driver->driver_id.'-'.Carbon::parse($date ?? now())->format('Ymd').'-'.substr((string) uniqid(), -4),
            'Pembayaran komisi sopir '.$driver->full_name.($note ? ' — '.$note : ''),
            self::JOURNAL_TYPE,
            [
                ['account' => $this->cardholderAccount($driver), 'debit' => $amount, 'credit' => 0],
                ['account' => $this->accounting->resolveCoa($cashCode), 'debit' => 0, 'credit' => $amount],
            ]
        );
    }

    /**
     * Beban komisi relatif komponen sewa murni — dipakai laporan/rekonsiliasi.
     */
    public function accruedForRental(int $rentalId): float
    {
        $accrualAccount = $this->accounting->resolveCoa('2-4000');
        if (! $accrualAccount) {
            return 0.0;
        }

        return (float) JournalDetail::query()
            ->join('tr_journal', 'tr_journal.journal_id', '=', 'tr_journal_detail.journal_id')
            ->where('tr_journal.reference_number', 'COM-'.$rentalId)
            ->where('tr_journal_detail.account_id', $accrualAccount)
            ->sum('tr_journal_detail.credit');
    }

    private function cardholderAccount(Driver $driver): ?int
    {
        return $this->accounting->resolveCoa('2-4000');
    }
}

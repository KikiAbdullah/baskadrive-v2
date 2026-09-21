<?php

namespace App\Services;

use App\Models\Coa;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Support\AppSettings;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;

/**
 * Service terpusat untuk seluruh pembuatan jurnal otomatis & kalkulasi
 * laporan keuangan (audit 2.6 - Template Jurnal Otomatis).
 */
class AccountingService
{
    protected array $coaCache = [];

    /**
     * Kunci kode akun COA ke account_id.
     */
    public function resolveCoa(string $code): ?int
    {
        if (! isset($this->coaCache[$code])) {
            $this->coaCache[$code] = Coa::where('account_code', $code)->value('account_id');
        }

        return $this->coaCache[$code];
    }

    /**
     * Buat jurnal double-entry. $lines: [['account' => accountId, 'debit' => x, 'credit' => y, 'desc' => '...']].
     *
     * @throws Exception saat periode terkunci atau jurnal tidak seimbang
     */
    public function post(string $date, ?string $ref, string $desc, string $type, array $lines): Journal
    {
        $this->assertPeriodOpen($date);

        // FIN-09: baris tanpa akun dibuang SETELAH dicek — bila ada baris yang akunnya
        // gagal diresolusi, itu bug pemetaan COA dan harus gagal, bukan diam-diam di-drop.
        foreach ($lines as $line) {
            if (empty($line['account'])) {
                throw new Exception(sprintf(
                    'Jurnal "%s" dibatalkan: terdapat baris dengan akun COA tidak terpetakan. Periksa konfigurasi Chart of Accounts.',
                    $desc
                ));
            }
        }

        // FIN-09: jurnal tanpa detail (semua sisi nol / tanpa baris) tidak boleh tersimpan —
        // keberadaan header jurnal kosong membuat buku besar tampak terbukui padahal tidak.
        $lines = array_values(array_filter($lines, fn ($l) => (float) ($l['debit'] ?? 0) != 0.0 || (float) ($l['credit'] ?? 0) != 0.0));

        if (empty($lines)) {
            throw new Exception('Jurnal dibatalkan: tidak ada baris debit/kredit bernilai nonnol.');
        }

        $totalDebit = array_sum(array_map(fn ($l) => (float) ($l['debit'] ?? 0), $lines));
        $totalCredit = array_sum(array_map(fn ($l) => (float) ($l['credit'] ?? 0), $lines));

        if (abs($totalDebit - $totalCredit) >= 0.01) {
            throw new Exception('Jurnal tidak seimbang: Debit '.$totalDebit.' != Kredit '.$totalCredit);
        }

        $journal = Journal::create([
            'transaction_date' => $date,
            'reference_number' => $ref,
            'description' => $desc,
            'journal_type' => $type,
            'created_by' => auth()->user()->employee_id ?? null,
        ]);

        foreach ($lines as $line) {
            JournalDetail::create([
                'journal_id' => $journal->journal_id,
                'account_id' => $line['account'],
                'debit' => $line['debit'] ?? 0,
                'credit' => $line['credit'] ?? 0,
                'description' => $line['desc'] ?? $desc,
            ]);
        }

        return $journal;
    }

    /**
     * posting jurnal otomatis tanpa menggagalkan transaksi utama (diam-damai log saat gagal).
     */
    public function postQuietly(string $date, ?string $ref, string $desc, string $type, array $lines): void
    {
        try {
            $this->post($date, $ref, $desc, $type, $lines);
        } catch (\Throwable $e) {
            \Log::warning('Auto journal failed: '.$e->getMessage());
        }
    }

    /**
     * Validasi tutup buku: transaksi sebelum tanggal closing_date tidak boleh dicatat (audit 2.6).
     *
     * @throws Exception
     */
    public function assertPeriodOpen($date): void
    {
        $closing = AppSettings::get('accounting_closing_date');

        if (! $closing) {
            return;
        }

        try {
            $dateCarbon = Carbon::parse($date)->startOfDay();
        } catch (\Throwable $e) {
            return;
        }

        if ($dateCarbon->lt(Carbon::parse($closing)->startOfDay())) {
            throw new Exception(
                'Periode terkunci: transaksi sebelum tanggal tutup buku ('.Carbon::parse($closing)->format('d/m/Y').') tidak boleh diubah/dihapus.'
            );
        }
    }

    /**
     * Agregasi mutasi debit/kredit per akun pada rentang tanggal.
     */
    public function accountBalances(Carbon $start, Carbon $end): Collection
    {
        return JournalDetail::query()
            ->join('tr_journal', 'tr_journal.journal_id', '=', 'tr_journal_detail.journal_id')
            ->join('m_coa', 'm_coa.account_id', '=', 'tr_journal_detail.account_id')
            ->whereBetween('tr_journal.transaction_date', [$start, $end])
            ->groupBy('m_coa.account_id', 'm_coa.account_code', 'm_coa.account_name', 'm_coa.account_type')
            ->selectRaw('m_coa.account_id, m_coa.account_code, m_coa.account_name, m_coa.account_type,
                SUM(tr_journal_detail.debit) as total_debit, SUM(tr_journal_detail.credit) as total_credit')
            ->get()
            ->map(function ($row) {
                $row->net = (float) $row->total_debit - (float) $row->total_credit;
                $row->signed_balance = in_array($row->account_type, ['liability', 'equity', 'income'], true)
                    ? (float) $row->total_credit - (float) $row->total_debit
                    : $row->net;

                return $row;
            });
    }

    /**
     * Laba Rugi: daftar pendapatan & beban per periode.
     */
    public function incomeStatement(Carbon $start, Carbon $end): array
    {
        $rows = $this->accountBalances($start, $end)
            ->whereIn('account_type', ['income', 'expense'])
            ->sortBy('account_code')
            ->values();

        return [
            'incomes' => $rows->where('account_type', 'income')->all(),
            'expenses' => $rows->where('account_type', 'expense')->all(),
            'total_income' => (float) $rows->where('account_type', 'income')->sum('signed_balance'),
            'total_expense' => (float) $rows->where('account_type', 'expense')->sum('signed_balance'),
            'net_profit' => (float) $rows->where('account_type', 'income')->sum('signed_balance')
                - (float) $rows->where('account_type', 'expense')->sum('signed_balance'),
        ];
    }

    /**
     * Neraca: posisi aset/kewajiban/ekuitas s.d. tanggal akhir (akumulasi sepanjang masa).
     */
    public function balanceSheet(Carbon $asOf): array
    {
        $from = Carbon::create(2000, 1, 1)->startOfDay();
        $rows = $this->accountBalances($from, $asOf->copy()->endOfDay());

        $pnl = $this->incomeStatement(Carbon::create(date('Y'), 1, 1)->startOfDay(), $asOf->copy()->endOfDay());

        $assets = $rows->where('account_type', 'asset')->sortBy('account_code')->values();
        $liabilities = $rows->where('account_type', 'liability')->sortBy('account_code')->values();
        $equity = $rows->where('account_type', 'equity')->sortBy('account_code')->values();

        return [
            'assets' => $assets->all(),
            'liabilities' => $liabilities->all(),
            'equity' => $equity->all(),
            'total_assets' => (float) $assets->sum('signed_balance'),
            'total_liabilities' => (float) $liabilities->sum('signed_balance'),
            'total_equity' => (float) $equity->sum('signed_balance'),
            'current_period_profit' => $pnl['net_profit'],
            'total_equity_with_profit' => (float) $equity->sum('signed_balance') + $pnl['net_profit'],
        ];
    }

    /**
     * Arus Kas: mutasi akun kas/bank diklasifikasi berdasarkan akun lawan per jurnal.
     */
    public function cashFlow(Carbon $start, Carbon $end): array
    {
        $cashCodes = ['1-1100', '1-1200'];
        $cashAccountIds = Coa::whereIn('account_code', $cashCodes)->pluck('account_id')->all();

        $totals = ['operating' => 0.0, 'investing' => 0.0, 'financing' => 0.0];
        $details = [];

        if ($cashAccountIds) {
            $journalIds = JournalDetail::whereIn('account_id', $cashAccountIds)
                ->whereHas('journal', fn ($q) => $q->whereBetween('transaction_date', [$start, $end]))
                ->distinct()
                ->pluck('journal_id');

            $journals = Journal::with('details.account')->whereIn('journal_id', $journalIds)->get();

            foreach ($journals as $journal) {
                $cashMovement = (float) $journal->details
                    ->filter(fn ($d) => in_array($d->account_id, $cashAccountIds, true))
                    ->sum(fn ($d) => (float) $d->debit - (float) $d->credit);

                if ($cashMovement == 0.0) {
                    continue;
                }

                $counterType = $journal->details
                    ->reject(fn ($d) => in_array($d->account_id, $cashAccountIds, true))
                    ->sortByDesc(fn ($d) => max((float) $d->debit, (float) $d->credit))
                    ->first()?->account?->account_type;

                $category = match ($counterType) {
                    'asset' => 'investing',
                    'liability', 'equity' => 'financing',
                    default => 'operating',
                };

                $totals[$category] += $cashMovement;

                $details[] = [
                    'date' => $journal->transaction_date,
                    'reference' => $journal->reference_number,
                    'description' => $journal->description,
                    'category' => $category,
                    'amount' => $cashMovement,
                ];
            }
        }

        return [
            'operating' => $totals['operating'],
            'investing' => $totals['investing'],
            'financing' => $totals['financing'],
            'net_change' => $totals['operating'] + $totals['investing'] + $totals['financing'],
            'details' => $details,
        ];
    }
}

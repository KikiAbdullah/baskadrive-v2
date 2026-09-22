<?php

namespace App\Http\Controllers\Rental;

use App\Http\Controllers\Controller;
use App\Models\Coa;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Services\AccountingService;
use App\Support\AppSettings;
use App\Support\ReportFormat;
use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ============================================================
    // JURNAL UMUM
    // ============================================================

    public function journalIndex()
    {
        return view('accounting.journal.index')->with([
            'title' => 'Jurnal Umum',
            'subtitle' => 'Daftar Jurnal Umum',
        ]);
    }

    /**
     * AKN-05: agregasi debit/kredit di SQL (withSum), bukan pemindaian detail di PHP.
     * AKN-06: filter tipe divalidasi sesuai enum kolom; AKN-02: nominal 2 desimal.
     */
    public function journalData(Request $request)
    {
        $request->validate([
            'type' => ['nullable', Rule::in(array_keys(Journal::TYPES))],
        ]);

        $query = Journal::query()
            ->withSum('details as total_debit_sum', 'debit')
            ->withSum('details as total_credit_sum', 'credit');

        if ($request->filled('type')) {
            $query->where('journal_type', $request->type);
        }

        return datatables()->of($query)
            ->addColumn('transaction_date', fn ($j) => $j->transaction_date?->locale('id')->translatedFormat('d M Y') ?? '-')
            ->addColumn('reference_number', fn ($j) => $j->reference_number ?? '-')
            ->addColumn('type', fn ($j) => Journal::TYPES[$j->journal_type] ?? ($j->journal_type ?? '-'))
            ->addColumn('description', fn ($j) => $j->description ?? '-')
            ->addColumn('total_debit', fn ($j) => AppSettings::money($j->total_debit_sum ?? 0))
            ->rawColumns([])
            ->toJson();
    }

    public function journalButtonOption(Request $request)
    {
        try {
            $item = Journal::findOrFail($request->get('id'));

            return response()->json([
                'status' => true,
                'view' => view('accounting.journal.button_option')->with(['item' => $item])->render(),
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function journalShow($id)
    {
        $item = Journal::with(['creator', 'details.account'])->findOrFail($id);

        return view('accounting.journal.show')->with([
            'title' => 'Detail Jurnal',
            'subtitle' => 'Jurnal '.($item->reference_number ?? ''),
            'item' => $item,
        ]);
    }

    /**
     * AKN-01: rute kini /journal/{id}/export dengan placeholder — fitur ekspor hidup kembali.
     * AKN-09/02: CSV selaras modul Laporan (BOM UTF-8, pemisah ';', dua desimal gaya Excel-ID,
     * tanpa 'Rp' agar kolom numerik tetap bisa dihitung).
     */
    public function journalExport($id)
    {
        $item = Journal::with(['details.account'])->findOrFail($id);

        $safeRef = preg_replace('/[^A-Za-z0-9_\-]/', '_', $item->reference_number ?? 'ID'.$item->journal_id);
        $filename = 'jurnal_'.$safeRef.'_'.now()->format('Ymd').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($item) {
            $handle = fopen('php://output', 'w');
            try {
                // BOM UTF-8 agar Excel membaca langsung dengan encoding benar (standar modul Laporan).
                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, ['No. Referensi', $item->reference_number], ';', '"', '');
                fputcsv($handle, ['Tanggal', $item->transaction_date?->format('Y-m-d')], ';', '"', '');
                fputcsv($handle, ['Tipe', Journal::TYPES[$item->journal_type] ?? $item->journal_type], ';', '"', '');
                fputcsv($handle, ['Keterangan', $item->description], ';', '"', '');
                fputcsv($handle, [], ';', '"', '');
                fputcsv($handle, ['Kode Akun', 'Nama Akun', 'Debit', 'Kredit', 'Keterangan'], ';', '"', '');
                foreach ($item->details as $d) {
                    fputcsv($handle, [
                        $d->account?->account_code ?? '',
                        $d->account?->account_name ?? '',
                        ReportFormat::cell($d->debit ?? 0, 'money', true),
                        ReportFormat::cell($d->credit ?? 0, 'money', true),
                        (string) $d->description,
                    ], ';', '"', '');
                }
            } finally {
                fclose($handle);
            }
        };

        return response()->stream($callback, 200, $headers);
    }

    // ============================================================
    // BUKU BESAR
    // ============================================================

    public function ledgerIndex()
    {
        return view('accounting.ledger.index')->with([
            'title' => 'Buku Besar',
            'subtitle' => 'Daftar Akun & Saldo',
        ]);
    }

    /**
     * AKN-05: mutasi diagregasi via withSum; AKN-04: saldo bertanda sesuai saldo normal
     * akun (asset/expense = debit-kredit, liability/equity/income = kredit-debit);
     * AKN-06: filter tipe enum; AKN-02: nominal 2 desimal.
     */
    public function ledgerData(Request $request)
    {
        $request->validate([
            'type' => ['nullable', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
        ]);

        $query = Coa::query()
            ->with('parent')
            ->withCount('children')
            ->withSum('journalDetails as total_debit_sum', 'debit')
            ->withSum('journalDetails as total_credit_sum', 'credit');

        if ($request->filled('type')) {
            $query->where('account_type', $request->type);
        }

        return datatables()->of($query)
            ->addColumn('account_code', fn ($a) => $a->account_code ?? '-')
            ->addColumn('account_name', fn ($a) => ($a->parent?->account_name ? $a->parent->account_name.' / ' : '').($a->account_name ?? '-'))
            ->addColumn('account_type', fn ($a) => $a->account_type ?? '-')
            ->addColumn('balance', function ($a) {
                // Akun induk tidak bermutasi (prinsip hanya daun) — tampilkan tanda hubung,
                // saldo agregatnya terlihat melalui sub-akun.
                if ($a->children_count > 0) {
                    return '—';
                }

                return AppSettings::money(AccountingService::signedBalance(
                    $a->account_type,
                    (float) ($a->total_debit_sum ?? 0),
                    (float) ($a->total_credit_sum ?? 0)
                ));
            })
            ->rawColumns([])
            ->toJson();
    }

    public function ledgerButtonOption(Request $request)
    {
        try {
            $item = Coa::findOrFail($request->get('id'));

            return response()->json([
                'status' => true,
                'view' => view('accounting.ledger.button_option')->with(['item' => $item])->render(),
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * AKN-04: saldo berjalan mengikuti saldo normal akun — pendapatan/liabilitas/ekuitas
     * bertambah dari kredit, bukan tampil negatif.
     */
    public function ledgerDetail($accountId)
    {
        $account = Coa::with(['parent'])->findOrFail($accountId);

        $entries = JournalDetail::with(['journal'])
            ->where('account_id', $accountId)
            ->orderBy('created_at')
            ->orderBy('detail_id')
            ->get();

        $isCreditNormal = in_array($account->account_type, ['liability', 'equity', 'income'], true);

        $running = 0.0;
        $entries = $entries->map(function ($e) use (&$running, $isCreditNormal) {
            $debit = (float) ($e->debit ?? 0);
            $credit = (float) ($e->credit ?? 0);
            $running += $isCreditNormal ? ($credit - $debit) : ($debit - $credit);
            $e->balance = $running;

            return $e;
        });

        return view('accounting.ledger.detail')->with([
            'title' => 'Buku Besar',
            'subtitle' => $account->account_code.' - '.$account->account_name,
            'account' => $account,
            'isCreditNormal' => $isCreditNormal,
            'entries' => $entries,
        ]);
    }

    // ============================================================
    // JURNAL MANUAL
    // ============================================================

    public function manualJournalCreate()
    {
        $accounts = Coa::active()->withCount('children')->orderBy('account_code')->get();

        return view('accounting.manual-journal.form')->with([
            'title' => 'Jurnal Manual',
            'subtitle' => 'Buat Jurnal Manual',
            'accounts' => $accounts,
        ]);
    }

    /**
     * Pratinjau keseimbangan: AKN-07 — turut memvalidasi sisi per baris agar
     * pesan yang ditampilkan identik dengan aturan penyimpanan.
     */
    public function manualJournalValidate(Request $request)
    {
        try {
            $entries = json_decode($request->get('entries', '[]'), true) ?: [];
            $totalDebit = 0.0;
            $totalCredit = 0.0;
            $rowIssues = [];

            foreach (array_values($entries) as $i => $e) {
                $debit = (float) ($e['debit'] ?? 0);
                $credit = (float) ($e['credit'] ?? 0);
                $totalDebit += $debit;
                $totalCredit += $credit;

                if ($debit > 0 && $credit > 0) {
                    $rowIssues[] = 'Baris '.($i + 1).': debit dan kredit tidak boleh terisi bersamaan.';
                } elseif ($debit == 0 && $credit == 0) {
                    $rowIssues[] = 'Baris '.($i + 1).': salah satu sisi harus bernilai lebih dari nol.';
                }
            }

            $balanced = abs($totalDebit - $totalCredit) < 0.01 && empty($rowIssues);

            $messages = array_merge($rowIssues, [
                $balanced ? 'Seimbang' : 'Debit dan Kredit tidak seimbang',
            ]);

            return response()->json([
                'status' => true,
                'balanced' => $balanced,
                'totalDebit' => $totalDebit,
                'totalCredit' => $totalCredit,
                'msg' => implode(' ', $messages),
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * AKN-07: validasi per baris (tepat satu sisi bernilai, > 0, batas DECIMAL(15,2)),
     * referensi <= 50, minimal satu debit & satu kredit, akun wajib daun & aktif.
     * AKN-08: seluruh validasi dilakukan SEBELUM transaksi; penulisan memakai
     * closure DB::transaction (level-safe), tanpa rollback() manual di catch.
     */
    public function manualJournalStore(Request $request)
    {
        $validated = $request->validate([
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'reference_number' => ['nullable', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:255'],
            'entries' => ['required', 'array', 'min:2'],
            'entries.*.account_id' => ['required', 'integer', 'exists:m_coa,account_id'],
            'entries.*.debit' => ['nullable', 'numeric', 'min:0', 'max:99999999999.99'],
            'entries.*.credit' => ['nullable', 'numeric', 'min:0', 'max:99999999999.99'],
            'entries.*.description' => ['nullable', 'string', 'max:255'],
        ]);

        // --- Validasi domain per baris (di luar transaksi) ---
        $errors = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $hasDebit = false;
        $hasCredit = false;

        foreach ($validated['entries'] as $i => $e) {
            $debit = (float) ($e['debit'] ?? 0);
            $credit = (float) ($e['credit'] ?? 0);
            $totalDebit += $debit;
            $totalCredit += $credit;

            if ($debit > 0 && $credit > 0) {
                $errors[] = 'Baris '.($i + 1).': debit dan kredit tidak boleh terisi bersamaan — satu baris satu sisi.';
            } elseif ($debit == 0 && $credit == 0) {
                $errors[] = 'Baris '.($i + 1).': salah satu dari debit atau kredit harus bernilai lebih dari nol.';
            }

            $hasDebit = $hasDebit || $debit > 0;
            $hasCredit = $hasCredit || $credit > 0;
        }

        if (! $hasDebit) {
            $errors[] = 'Jurnal harus memiliki minimal satu baris debit.';
        }

        if (! $hasCredit) {
            $errors[] = 'Jurnal harus memiliki minimal satu baris kredit.';
        }

        if (abs($totalDebit - $totalCredit) >= 0.01) {
            $errors[] = 'Total Debit ('.number_format($totalDebit, 2, ',', '.').') harus sama dengan Total Kredit ('.number_format($totalCredit, 2, ',', '.').').';
        }

        // Akun induk / nonaktif tidak boleh bermutasi (jaga agregasi saldo parent).
        $accountIds = collect($validated['entries'])->pluck('account_id')->unique();
        $accounts = Coa::withCount('children')->whereIn('account_id', $accountIds)->get()->keyBy('account_id');

        foreach ($validated['entries'] as $i => $e) {
            $acc = $accounts->get($e['account_id']);
            if (! $acc) {
                continue; // sudah ditolak rule exists
            }
            if ($acc->children_count > 0) {
                $errors[] = 'Akun induk "'.$acc->account_code.' - '.$acc->account_name.'" tidak boleh dipakai transaksi. Pilih sub-akun.';
            }
            if (! $acc->is_active) {
                $errors[] = 'Akun "'.$acc->account_code.' - '.$acc->account_name.'" berstatus nonaktif dan tidak bisa dipakai.';
            }
        }

        if ($errors) {
            return redirect()->back()->withInput()->withErrors($errors);
        }

        // --- Penulisan (AKN-08: transaksi closure, exception domain tetap tertangani) ---
        try {
            app(AccountingService::class)->assertPeriodOpen($validated['transaction_date']);

            DB::transaction(function () use ($validated) {
                $journal = Journal::create([
                    'transaction_date' => $validated['transaction_date'],
                    'reference_number' => $validated['reference_number'] ?? null,
                    'description' => $validated['description'],
                    'journal_type' => 'manual',
                    'created_by' => auth()->user()->employee_id ?? null,
                ]);

                foreach ($validated['entries'] as $e) {
                    JournalDetail::create([
                        'journal_id' => $journal->journal_id,
                        'account_id' => $e['account_id'],
                        'debit' => (float) ($e['debit'] ?? 0),
                        'credit' => (float) ($e['credit'] ?? 0),
                        'description' => $e['description'] ?? null,
                    ]);
                }
            });
        } catch (Exception $e) {
            return redirect()->back()->withInput()->withErrors($e->getMessage());
        }

        return redirect()->route('accounting.journal.index')
            ->withSuccess('Jurnal manual berhasil disimpan.');
    }

    // ============================================================
    // LAPORAN KEUANGAN FORMAL (audit 2.6)
    // ============================================================

    /**
     * AKN-06: rentang tanggal divalidasi ketat (pola FIN-15 modul Laporan) —
     * input invalid menghasilkan 422 dengan pesan, bukan exception 500.
     */
    private function statementRange(Request $request): array
    {
        $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $start = $request->filled('start_date') ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfYear();
        $end = $request->filled('end_date') ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();

        if ($start->gt($end)) {
            throw ValidationException::withMessages(['end_date' => 'Tanggal akhir harus sama atau setelah tanggal awal.']);
        }

        if ($start->diffInDays($end->copy()->startOfDay()) + 1 > 366) {
            throw ValidationException::withMessages(['end_date' => 'Rentang laporan maksimal 366 hari.']);
        }

        return [$start, $end];
    }

    public function incomeStatement(Request $request)
    {
        [$start, $end] = $this->statementRange($request);
        $service = app(AccountingService::class);

        return view('accounting.statements.income-statement')->with([
            'title' => 'Laporan Laba Rugi',
            'subtitle' => 'Income Statement',
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'report' => $service->incomeStatement($start, $end),
        ]);
    }

    public function balanceSheet(Request $request)
    {
        [$start, $end] = $this->statementRange($request);
        $service = app(AccountingService::class);

        return view('accounting.statements.balance-sheet')->with([
            'title' => 'Neraca',
            'subtitle' => 'Balance Sheet',
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'report' => $service->balanceSheet($end),
        ]);
    }

    public function cashFlow(Request $request)
    {
        [$start, $end] = $this->statementRange($request);
        $service = app(AccountingService::class);

        return view('accounting.statements.cash-flow')->with([
            'title' => 'Laporan Arus Kas',
            'subtitle' => 'Cash Flow',
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'report' => $service->cashFlow($start, $end),
        ]);
    }
}

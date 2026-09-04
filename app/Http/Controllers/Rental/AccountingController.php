<?php

namespace App\Http\Controllers\Rental;

use App\Http\Controllers\Controller;
use App\Models\Coa;
use App\Models\Journal;
use App\Models\JournalDetail;
use DB;
use Exception;
use Illuminate\Http\Request;

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

    public function journalData(Request $request)
    {
        $query = Journal::with(['creator', 'details']);

        if ($request->filled('type')) {
            $query->where('journal_type', $request->type);
        }

        return datatables()->of($query)
            ->addColumn('transaction_date', fn($j) => $j->transaction_date?->format('d/m/Y') ?? '-')
            ->addColumn('reference_number', fn($j) => $j->reference_number ?? '-')
            ->addColumn('type', fn($j) => ucfirst($j->journal_type ?? '-'))
            ->addColumn('description', fn($j) => $j->description ?? '-')
            ->addColumn('total_debit', function ($j) {
                $sum = $j->details->sum('debit');
                return 'Rp ' . number_format($sum ?? 0, 0, ',', '.');
            })
            ->addColumn('action', function ($j) {
                $html = '<div class="d-flex gap-1">';
                $html .= '<a href="' . route('accounting.journal.show', $j->journal_id) . '" class="btn btn-sm btn-outline-primary"><i class="ri-eye-line"></i></a>';
                $html .= '<a href="' . route('accounting.journal.export', $j->journal_id) . '" class="btn btn-sm btn-outline-secondary"><i class="ri-download-line"></i></a>';
                $html .= '</div>';
                return $html;
            })
            ->rawColumns(['action'])
            ->toJson();
    }

    public function journalShow($id)
    {
        $item = Journal::with(['creator', 'details.account'])->findOrFail($id);

        return view('accounting.journal.show')->with([
            'title' => 'Detail Jurnal',
            'subtitle' => 'Jurnal ' . ($item->reference_number ?? ''),
            'item' => $item,
        ]);
    }

    public function journalExport($id)
    {
        $item = Journal::with(['details.account'])->findOrFail($id);

        $filename = 'jurnal_' . ($item->reference_number ?? $item->journal_id) . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($item) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['No. Referensi', $item->reference_number]);
            fputcsv($handle, ['Tanggal', $item->transaction_date?->format('Y-m-d')]);
            fputcsv($handle, ['Keterangan', $item->description]);
            fputcsv($handle, []);
            fputcsv($handle, ['Kode Akun', 'Nama Akun', 'Debit', 'Kredit', 'Keterangan']);
            foreach ($item->details as $d) {
                fputcsv($handle, [
                    $d->account?->account_code,
                    $d->account?->account_name,
                    $d->debit ?? 0,
                    $d->credit ?? 0,
                    $d->description,
                ]);
            }
            fclose($handle);
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

    public function ledgerData(Request $request)
    {
        $query = Coa::with(['parent', 'journalDetails']);

        if ($request->filled('type')) {
            $query->where('account_type', $request->type);
        }

        return datatables()->of($query)
            ->addColumn('account_code', fn($a) => $a->account_code ?? '-')
            ->addColumn('account_name', fn($a) => ($a->parent?->account_name ? $a->parent->account_name . ' / ' : '') . ($a->account_name ?? '-'))
            ->addColumn('account_type', fn($a) => ucfirst($a->account_type ?? '-'))
            ->addColumn('balance', function ($a) {
                $debit = $a->journalDetails->sum('debit');
                $credit = $a->journalDetails->sum('credit');
                return 'Rp ' . number_format(($debit - $credit) ?? 0, 0, ',', '.');
            })
            ->addColumn('action', fn($a) => '<a href="' . route('accounting.ledger.detail', $a->account_id) . '" class="btn btn-sm btn-outline-primary"><i class="ri-book-line"></i> Detail</a>')
            ->rawColumns(['action'])
            ->toJson();
    }

    public function ledgerDetail($accountId)
    {
        $account = Coa::with(['parent'])->findOrFail($accountId);

        $entries = JournalDetail::with(['journal'])
            ->where('account_id', $accountId)
            ->orderBy('created_at')
            ->get();

        $running = 0;
        $entries = $entries->map(function ($e) use (&$running) {
            $running += ($e->debit ?? 0) - ($e->credit ?? 0);
            $e->balance = $running;
            return $e;
        });

        return view('accounting.ledger.detail')->with([
            'title' => 'Buku Besar',
            'subtitle' => $account->account_code . ' - ' . $account->account_name,
            'account' => $account,
            'entries' => $entries,
        ]);
    }

    // ============================================================
    // JURNAL MANUAL
    // ============================================================

    public function manualJournalCreate()
    {
        $accounts = Coa::active()->orderBy('account_code')->get();

        return view('accounting.manual-journal.form')->with([
            'title' => 'Jurnal Manual',
            'subtitle' => 'Buat Jurnal Manual',
            'accounts' => $accounts,
        ]);
    }

    public function manualJournalValidate(Request $request)
    {
        try {
            $entries = json_decode($request->get('entries', '[]'), true) ?: [];
            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($entries as $e) {
                $totalDebit += (float) ($e['debit'] ?? 0);
                $totalCredit += (float) ($e['credit'] ?? 0);
            }

            $balanced = abs($totalDebit - $totalCredit) < 0.01;

            return response()->json([
                'status' => true,
                'balanced' => $balanced,
                'totalDebit' => $totalDebit,
                'totalCredit' => $totalCredit,
                'msg' => $balanced ? 'Seimbang' : 'Debit dan Kredit tidak seimbang',
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function manualJournalStore(Request $request)
    {
        try {
            $request->validate([
                'transaction_date' => 'required|date',
                'reference_number' => 'nullable|string',
                'description' => 'required|string',
                'entries' => 'required|array|min:2',
                'entries.*.account_id' => 'required|exists:m_coa,account_id',
                'entries.*.debit' => 'nullable|numeric',
                'entries.*.credit' => 'nullable|numeric',
                'entries.*.description' => 'nullable|string',
            ]);

            $totalDebit = 0;
            $totalCredit = 0;
            foreach ($request->entries as $e) {
                $totalDebit += (float) ($e['debit'] ?? 0);
                $totalCredit += (float) ($e['credit'] ?? 0);
            }

            if (abs($totalDebit - $totalCredit) >= 0.01) {
                return redirect()->back()->withInput()
                    ->withErrors('Total Debit (' . $totalDebit . ') harus sama dengan Total Kredit (' . $totalCredit . ').');
            }

            DB::beginTransaction();

            $journal = Journal::create([
                'transaction_date' => $request->transaction_date,
                'reference_number' => $request->reference_number,
                'description' => $request->description,
                'journal_type' => 'manual',
                'created_by' => auth()->user()->employee_id ?? null,
            ]);

            foreach ($request->entries as $e) {
                JournalDetail::create([
                    'journal_id' => $journal->journal_id,
                    'account_id' => $e['account_id'],
                    'debit' => (float) ($e['debit'] ?? 0),
                    'credit' => (float) ($e['credit'] ?? 0),
                    'description' => $e['description'] ?? null,
                ]);
            }

            DB::commit();

            return redirect()->route('accounting.journal.index')
                ->withSuccess('Jurnal manual berhasil disimpan.');
        } catch (Exception $e) {
            DB::rollback();
            return redirect()->back()->withInput()->withErrors($e->getMessage());
        }
    }
}

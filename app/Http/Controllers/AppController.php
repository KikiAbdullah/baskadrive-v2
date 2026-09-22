<?php

namespace App\Http\Controllers;

use App\Models\Coa;
use App\Models\DamageReport;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\Maintenance;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\UserLog;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Beranda aplikasi (rute `/`) — ringkasan eksekutif lintas modul.
 *
 * Semua kartu & panel dirakit dari database (bukan angka statis), dirancang
 * hemat query (agregasi SQL, tanpa N+1) dan sadar-peran: setiap seksi view
 * dibungkus gate modul yang sama dengan menu, sehingga VIEWER tidak melihat
 * — dan tidak bisa menyimpulkan — data operasional yang bukan haknya.
 */
class AppController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $data = [
            'title' => 'Dashboard',
            'subtitle' => 'Hi, '.$user->name,
            'userName' => $user->name,
            'roleLabel' => $this->roleLabel($user),
            'today' => Carbon::today(),
            'greeting' => $this->greeting(),
        ];

        // ------------------------- Armada & Sewa (modul Rental) -------------------------
        if (Gate::allows('rental_view')) {
            $data += $this->fleetAndRentalData();
        }

        // ------------------------- Keuangan (modul Finance) -------------------------
        if (Gate::allows('finance_view')) {
            $data += $this->financeData();
        }

        // ------------------------- Fleet: maintenance & kerusakan -------------------------
        if (Gate::allows('fleet_view')) {
            $data += $this->fleetMaintenanceData();
        }

        // ------------------------- Akuntansi (modul Accounting) -------------------------
        if (Gate::allows('accounting_view')) {
            $data += $this->accountingData();
        }

        // ------------------------- Aktivitas terbaru (seluruh peran) -------------------------
        $data['recentLogs'] = UserLog::query()
            ->with('user')
            ->latest('created_at')
            ->limit(8)
            ->get();

        return view('home')->with($data);
    }

    /**
     * Armada & sewa: KPI armada/sewa, tren 7 hari, antrian hari ini,
     * jatuh tempo 7 hari, dan unit terlambat.
     */
    private function fleetAndRentalData(): array
    {
        $today = Carbon::today();

        $vehicleStatus = Vehicle::query()
            ->select('status', DB::raw('COUNT(*) AS total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $rentalStatus = Rental::query()
            ->select('status', DB::raw('COUNT(*) AS total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // Tren 7 hari terakhir: sewa baru & pendapatan (unit disewa + denda terbayar).
        $trend = collect(range(6, 0))->mapWithKeys(function (int $back) use ($today) {
            $day = $today->copy()->subDays($back)->toDateString();

            return [$day => ['rentals' => 0, 'revenue' => 0.0]];
        });

        $rentalsPerDay = Rental::query()
            ->whereBetween('created_at', [$today->copy()->subDays(6)->startOfDay(), $today->copy()->endOfDay()])
            ->select(DB::raw('DATE(created_at) AS day'), DB::raw('COUNT(*) AS total'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'day');

        $revenuePerDay = Payment::query()
            ->completed()
            ->whereBetween('payment_date', [$today->copy()->subDays(6)->startOfDay(), $today->copy()->endOfDay()])
            ->select(DB::raw('DATE(payment_date) AS day'), DB::raw('SUM(amount) AS total'))
            ->groupBy(DB::raw('DATE(payment_date)'))
            ->pluck('total', 'day');

        $trend = $trend->map(function (array $row, string $day) use ($rentalsPerDay, $revenuePerDay) {
            return [
                'rentals' => (int) ($rentalsPerDay[$day] ?? 0),
                'revenue' => (float) ($revenuePerDay[$day] ?? 0),
            ];
        });

        // Antrian hari ini: serah terima unit keluar (reservasi) dan kembali (berjalan).
        $pickupToday = Rental::query()
            ->with(['customer', 'vehicle'])
            ->where('status', 'reserved')
            ->whereBetween('rental_start_date', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
            ->orderBy('rental_start_date')
            ->limit(6)
            ->get();

        $returnToday = Rental::query()
            ->with(['customer', 'vehicle'])
            ->whereIn('status', ['ongoing', 'overdue'])
            ->whereBetween('rental_end_date', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
            ->orderBy('rental_end_date')
            ->limit(6)
            ->get();

        // Jatuh tempo 7 hari ke depan — termasuk yang sudah lewat (badge merah di view).
        $dueSoon = Rental::query()
            ->with(['customer', 'vehicle'])
            ->whereIn('status', ['ongoing', 'overdue'])
            ->whereBetween('rental_end_date', [$today->copy()->startOfDay(), $today->copy()->addDays(7)->endOfDay()])
            ->orderBy('rental_end_date')
            ->limit(8)
            ->get();

        $overdue = Rental::query()
            ->with(['customer', 'vehicle'])
            ->where('status', 'overdue')
            ->orderBy('rental_end_date')
            ->limit(6)
            ->get();

        $activeRentals = (int) $rentalStatus->get('ongoing', 0) + (int) $rentalStatus->get('overdue', 0);

        return [
            'vehicleStatus' => $vehicleStatus,
            'rentalStatus' => $rentalStatus,
            'activeRentals' => $activeRentals,
            'overdueCount' => (int) $rentalStatus->get('overdue', 0),
            'trend' => $trend,
            'pickupToday' => $pickupToday,
            'returnToday' => $returnToday,
            'dueSoon' => $dueSoon,
            'overdueRentals' => $overdue,
        ];
    }

    /**
     * Keuangan: piutang, invoice jatuh tempo, penerimaan hari ini & bulan ini,
     * denda tertunda, deposit tertahan, dan rincian penerimaan per metode.
     */
    private function financeData(): array
    {
        $today = Carbon::today();

        // Piutang usaha sewa: tagihan belum lunas (belum dibatalkan), sesuai kontrak
        // service layer: remaining = total_amount - paid_amount.
        $outstanding = Invoice::query()
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) AS amount, COUNT(*) AS total')
            ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->first();

        // Invoice jatuh tempo: sent/partial melewati due_date (kontrak scopeOverdue).
        $overdueInvoices = Invoice::query()
            ->whereIn('status', ['sent', 'partially_paid'])
            ->where('due_date', '<', $today)
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) AS amount, COUNT(*) AS total')
            ->first();

        $receivedToday = Payment::query()
            ->completed()
            ->whereBetween('payment_date', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
            ->sum('amount');

        $receivedMonth = Payment::query()
            ->completed()
            ->whereBetween('payment_date', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])
            ->sum('amount');

        // Denda belum tertangani: belum lunas dan belum di-waive.
        $openFines = DB::table('tr_fine')
            ->where('status', 'unpaid')
            ->selectRaw('COALESCE(SUM(amount), 0) AS amount, COUNT(*) AS total')
            ->first();

        // Deposit jaminan tertahan (FIN-08): belum dikembalikan lewat refund.
        $depositHeld = (float) (Payment::query()
            ->where('allocation', Payment::ALLOCATION_DEPOSIT)
            ->where('status', 'completed')
            ->sum('amount')
            - DB::table('tr_refund')->whereIn('refund_type', ['deposit', 'cancellation'])->sum('amount'));

        // Komposisi penerimaan per metode bulan ini (chart donut).
        $methodBreakdown = Payment::query()
            ->completed()
            ->whereBetween('payment_date', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])
            ->select('payment_method', DB::raw('SUM(amount) AS total'))
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get();

        return [
            'receivableAmount' => (float) $outstanding->amount,
            'receivableCount' => (int) $outstanding->total,
            'overdueInvoiceAmount' => (float) $overdueInvoices->amount,
            'overdueInvoiceCount' => (int) $overdueInvoices->total,
            'receivedToday' => (float) $receivedToday,
            'receivedMonth' => (float) $receivedMonth,
            'openFineAmount' => (float) $openFines->amount,
            'openFineCount' => (int) $openFines->total,
            'depositHeld' => max(0.0, $depositHeld),
            'methodBreakdown' => $methodBreakdown,
        ];
    }

    /**
     * Fleet: jadwal maintenance, kerusakan, dan klaim asuransi.
     */
    private function fleetMaintenanceData(): array
    {
        $today = Carbon::today();

        $scheduledMaintenance = Maintenance::query()
            ->with(['vehicle', 'maintenanceType'])
            ->whereIn('status', ['scheduled', 'overdue'])
            ->whereBetween('scheduled_date', [$today->copy()->startOfDay(), $today->copy()->addDays(30)->endOfDay()])
            ->orderBy('scheduled_date')
            ->limit(6)
            ->get();

        $openDamage = DamageReport::query()
            ->with(['vehicle', 'rental'])
            ->whereNotIn('status', ['closed', 'rejected', 'written_off'])
            ->latest('reported_date')
            ->limit(6)
            ->get();

        $openDamageCount = DamageReport::query()
            ->whereNotIn('status', ['closed', 'rejected', 'written_off'])
            ->count();

        // Kerusakan berat yang masih menahan unit (severity >= severe, belum selesai).
        $severeDamageCount = DamageReport::query()
            ->whereIn('severity', ['severe', 'total_loss'])
            ->whereNotIn('status', ['closed', 'rejected', 'written_off'])
            ->count();

        $claimsInProgress = DB::table('tr_insurance_claim')
            ->whereIn('status', ['submitted', 'in_review', 'approved'])
            ->selectRaw('COALESCE(SUM(claim_amount), 0) AS amount, COUNT(*) AS total')
            ->first();

        // Hitung penuh terpisah dari tampilan (list dibatasi 6 baris).
        $scheduledMaintenanceCount = Maintenance::query()
            ->whereIn('status', ['scheduled', 'overdue'])
            ->whereBetween('scheduled_date', [$today->copy()->startOfDay(), $today->copy()->addDays(30)->endOfDay()])
            ->count();

        return [
            'scheduledMaintenance' => $scheduledMaintenance,
            'scheduledMaintenanceCount' => $scheduledMaintenanceCount,
            'openDamage' => $openDamage,
            'openDamageCount' => $openDamageCount,
            'severeDamageCount' => $severeDamageCount,
            'claimsInProgress' => $claimsInProgress,
        ];
    }

    /**
     * Akuntansi: neraca ringkas hari ini (aset/liabilitas/ekuitas) dan jurnal terakhir.
     * Rumus tunduk pada kontrak AccountingService: ekuitas meleburkan laba akumulasi.
     */
    private function accountingData(): array
    {
        $prefixByAccount = $this->accountPrefixMap();

        if ($prefixByAccount === null) {
            return [
                'balanceHealthy' => null,
                'ledger' => collect(),
                'recentJournals' => collect(),
                'journalCount' => 0,
            ];
        }

        $signByPrefix = [
            'asset' => 1, 'liability' => -1, 'equity' => -1, 'revenue' => -1, 'expense' => 1,
        ];

        $detail = JournalDetail::query()
            ->select('tr_journal_detail.account_id', DB::raw('SUM(debit) AS debit_sum'), DB::raw('SUM(credit) AS credit_sum'))
            ->join('m_coa', 'm_coa.account_id', '=', 'tr_journal_detail.account_id')
            ->groupBy('tr_journal_detail.account_id')
            ->get();

        $buckets = ['asset' => 0.0, 'liability' => 0.0, 'equity' => 0.0, 'revenue' => 0.0, 'expense' => 0.0];
        $debits = 0.0;
        $credits = 0.0;

        foreach ($detail as $row) {
            $debits += (float) $row->debit_sum;
            $credits += (float) $row->credit_sum;

            $kind = $prefixByAccount[$row->account_id] ?? null;
            if ($kind === null) {
                continue;
            }

            $sign = $signByPrefix[$kind];
            $buckets[$kind] += ($sign > 0 ? 1 : -1) * ((float) $row->debit_sum - (float) $row->credit_sum);
        }

        // Neraca ringkas: ekuitas meleburkan laba akumulasi (AKN-03).
        $assets = $buckets['asset'];
        $liabilities = $buckets['liability'];
        $equity = $buckets['equity'] + $buckets['revenue'] - $buckets['expense'];

        $ledger = collect([
            ['label' => 'Aset', 'value' => $assets],
            ['label' => 'Liabilitas', 'value' => $liabilities],
            ['label' => 'Ekuitas', 'value' => $equity],
        ]);

        $recentJournals = Journal::query()
            ->latest('transaction_date')
            ->latest('journal_id')
            ->limit(5)
            ->get();

        return [
            'balanceHealthy' => abs($assets - ($liabilities + $equity)) < 0.01,
            'ledger' => $ledger,
            'recentJournals' => $recentJournals,
            'journalCount' => Journal::count(),
        ];
    }

    /**
     * Klasifikasi akun per account_id dari prefix kode akun.
     * Bila tabel COA kosong (belum ada jurnal), kembalikan null.
     */
    private function accountPrefixMap(): ?array
    {
        $map = [];

        Coa::query()->select('account_id', 'account_code', 'account_type')->chunkById(500, function ($rows) use (&$map) {
            foreach ($rows as $row) {
                $map[$row->account_id] = $this->classifyAccount($row->account_type, $row->account_code);
            }
        });

        return $map === [] ? null : $map;
    }

    private function classifyAccount(?string $type, string $code): ?string
    {
        if (in_array($type, ['asset', 'liability', 'equity', 'revenue', 'expense'], true)) {
            return $type;
        }

        $head = $code[0] ?? '';

        return match ($head) {
            '1' => 'asset',
            '2' => 'liability',
            '3' => 'equity',
            '4' => 'revenue',
            '5' => 'expense',
            default => null,
        };
    }

    private function greeting(): string
    {
        return match (true) {
            now()->hour < 11 => 'Selamat pagi',
            now()->hour < 15 => 'Selamat siang',
            now()->hour < 19 => 'Selamat sore',
            default => 'Selamat malam',
        };
    }

    /**
     * Nama peran primer user untuk chip identitas (aman terhadap nama role baru).
     */
    private function roleLabel($user): string
    {
        $roles = method_exists($user, 'roles') ? $user->roles : collect();
        $first = $roles->first();

        if (! $first) {
            return 'Pengguna';
        }

        return ucfirst(strtolower($first->name ?? 'Pengguna'));
    }
}

<?php

namespace App\Http\Controllers\Rental;

use App\Http\Controllers\Controller;
use App\Models\DamageReport;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\ReturnCar;
use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function dateRange(Request $request)
    {
        $start = $request->filled('start_date') ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfYear();
        $end = $request->filled('end_date') ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();
        return [$start, $end];
    }

    // ============================================================
    // PENDAPATAN & PROFIT
    // ============================================================

    public function revenue()
    {
        [$start, $end] = $this->dateRange(request());

        $totalRevenue = Payment::where('status', 'completed')
            ->whereBetween('payment_date', [$start, $end])
            ->sum('amount');
        $totalInvoices = Invoice::whereBetween('issue_date', [$start, $end])->count();
        $outstanding = Invoice::whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->where('due_date', '<', now())->sum(DB::raw('total_amount - paid_amount'));

        return view('report.revenue.index')->with([
            'title' => 'Pendapatan & Profit',
            'subtitle' => 'Laporan Pendapatan',
            'totalRevenue' => $totalRevenue,
            'totalInvoices' => $totalInvoices,
            'outstanding' => $outstanding,
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ]);
    }

    public function revenueData(Request $request)
    {
        [$start, $end] = $this->dateRange($request);

        $query = Payment::query()
            ->where('status', 'completed')
            ->whereBetween('payment_date', [$start, $end])
            ->selectRaw("DATE_FORMAT(payment_date,'%Y-%m') as period, DATE_FORMAT(payment_date,'%M %Y') as period_label, SUM(amount) as revenue, COUNT(*) as payments_count")
            ->groupBy('period', 'period_label')
            ->orderBy('period', 'desc');

        return datatables()->of($query)
            ->addColumn('period_label', fn($r) => $r->period_label)
            ->addColumn('revenue', fn($r) => 'Rp ' . number_format($r->revenue, 0, ',', '.'))
            ->addColumn('payments_count', fn($r) => $r->payments_count)
            ->toJson();
    }

    // ============================================================
    // UTILISASI ARMADA
    // ============================================================

    public function fleetUtilization()
    {
        return view('report.fleet-utilization.index')->with([
            'title' => 'Utilisasi Armada',
            'subtitle' => 'Laporan Utilisasi Kendaraan',
        ]);
    }

    public function fleetUtilizationData(Request $request)
    {
        $query = DB::table('tr_rental')
            ->join('m_vehicle', 'tr_rental.vehicle_id', '=', 'm_vehicle.vehicle_id')
            ->leftJoin('m_vehicle_model', 'm_vehicle.model_id', '=', 'm_vehicle_model.model_id')
            ->leftJoin('m_brand', 'm_vehicle_model.brand_id', '=', 'm_brand.brand_id')
            ->selectRaw("m_vehicle.vehicle_id, m_vehicle.license_plate, CONCAT(IFNULL(m_brand.brand_name,''),' ',IFNULL(m_vehicle_model.model_name,'')) as vehicle_name,
                COUNT(*) as total_rentals,
                SUM(tr_rental.rental_days) as total_days,
                SUM(tr_rental.total_amount) as total_revenue")
            ->groupBy('m_vehicle.vehicle_id', 'm_vehicle.license_plate', 'vehicle_name')
            ->orderByDesc('total_rentals');

        return datatables()->of($query)
            ->addColumn('license_plate', fn($v) => $v->license_plate)
            ->addColumn('vehicle_name', fn($v) => $v->vehicle_name ?: '-')
            ->addColumn('total_rentals', fn($v) => $v->total_rentals)
            ->addColumn('total_days', fn($v) => number_format($v->total_days ?? 0) . ' hari')
            ->addColumn('utilization', function ($v) {
                $pct = min(100, round((($v->total_days ?? 0) / 365) * 100));
                return $pct . '%';
            })
            ->addColumn('total_revenue', fn($v) => 'Rp ' . number_format($v->total_revenue ?? 0, 0, ',', '.'))
            ->toJson();
    }

    // ============================================================
    // TOP PELANGGAN
    // ============================================================

    public function topCustomers()
    {
        return view('report.top-customers.index')->with([
            'title' => 'Top Pelanggan',
            'subtitle' => 'Pelanggan Terbaik',
        ]);
    }

    public function topCustomersData(Request $request)
    {
        $query = DB::table('tr_rental')
            ->join('m_customer', 'tr_rental.customer_id', '=', 'm_customer.customer_id')
            ->selectRaw("m_customer.customer_id,
                CONCAT(IFNULL(m_customer.first_name,''),' ',IFNULL(m_customer.last_name,''),
                    IF(m_customer.customer_type='company', CONCAT(' (',IFNULL(m_customer.company_name,''),')'),'')) as customer_name,
                m_customer.customer_type,
                COUNT(*) as total_rentals,
                SUM(tr_rental.total_amount) as total_spend,
                MAX(tr_rental.rental_start_date) as last_rental")
            ->groupBy('m_customer.customer_id', 'customer_name', 'm_customer.customer_type')
            ->orderByDesc('total_spend');

        return datatables()->of($query)
            ->addColumn('customer_name', fn($c) => trim($c->customer_name) ?: '-')
            ->addColumn('customer_type', fn($c) => ucfirst($c->customer_type ?? '-'))
            ->addColumn('total_rentals', fn($c) => $c->total_rentals)
            ->addColumn('total_spend', fn($c) => 'Rp ' . number_format($c->total_spend ?? 0, 0, ',', '.'))
            ->addColumn('last_rental', fn($c) => $c->last_rental ? Carbon::parse($c->last_rental)->format('d/m/Y') : '-')
            ->toJson();
    }

    // ============================================================
    // KLAIM & DENDA
    // ============================================================

    public function claims()
    {
        return view('report.claims.index')->with([
            'title' => 'Klaim & Denda',
            'subtitle' => 'Laporan Kerusakan & Klaim Asuransi',
        ]);
    }

    public function claimsData(Request $request)
    {
        $query = DamageReport::query()->with(['vehicle', 'insuranceClaim']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return datatables()->of($query)
            ->addColumn('vehicle', fn($d) => $d->vehicle?->license_plate ?? '-')
            ->addColumn('damage_type', fn($d) => ucfirst(str_replace('_', ' ', $d->damage_type ?? '-')))
            ->addColumn('severity', fn($d) => ucfirst($d->severity ?? '-'))
            ->addColumn('reported_date', fn($d) => $d->reported_date?->format('d/m/Y') ?? '-')
            ->addColumn('repair_cost', fn($d) => 'Rp ' . number_format($d->actual_repair_cost ?? $d->repair_cost_estimate ?? 0, 0, ',', '.'))
            ->addColumn('status_badge', fn($d) => view('components.damage-status-badge', ['status' => $d->status])->render())
            ->addColumn('claim_status', fn($d) => $d->insuranceClaim ? view('components.claim-status-badge', ['status' => $d->insuranceClaim->status])->render() : '<span class="text-muted">-</span>')
            ->addColumn('claim_amount', fn($d) => $d->insuranceClaim ? 'Rp ' . number_format($d->insuranceClaim->claim_amount ?? 0, 0, ',', '.') : '-')
            ->rawColumns(['status_badge', 'claim_status'])
            ->toJson();
    }

    // ============================================================
    // LAPORAN KEUANGAN
    // ============================================================

    public function financial()
    {
        [$start, $end] = $this->dateRange(request());

        $income = Payment::where('status', 'completed')->whereBetween('payment_date', [$start, $end])->sum('amount');
        $expense = DB::table('tr_maintenance')->whereNotNull('cost')->whereBetween('actual_date', [$start, $end])->sum('cost')
            + DB::table('tr_fine')->where('status', 'paid')->whereBetween('paid_date', [$start, $end])->sum('amount')
            + DB::table('tr_refund')->where('status', 'processed')->whereBetween('refund_date', [$start, $end])->sum('amount');
        $profit = $income - $expense;

        return view('report.financial.index')->with([
            'title' => 'Laporan Keuangan',
            'subtitle' => 'Ringkasan Keuangan',
            'income' => $income,
            'expense' => $expense,
            'profit' => $profit,
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ]);
    }

    public function financialData(Request $request)
    {
        [$start, $end] = $this->dateRange($request);

        $union = DB::table('tr_payment')
            ->selectRaw("DATE_FORMAT(payment_date,'%Y-%m') as period, SUM(amount) as income, 0 as expense")
            ->where('status', 'completed')
            ->whereBetween('payment_date', [$start, $end])
            ->unionAll(
                DB::table('tr_maintenance')->selectRaw("DATE_FORMAT(COALESCE(actual_date,scheduled_date),'%Y-%m') as period, 0 as income, SUM(cost) as expense")->whereNotNull('cost')->whereBetween('actual_date', [$start, $end])
            )
            ->unionAll(
                DB::table('tr_fine')->selectRaw("DATE_FORMAT(paid_date,'%Y-%m') as period, 0 as income, SUM(amount) as expense")->where('status', 'paid')->whereBetween('paid_date', [$start, $end])
            )
            ->unionAll(
                DB::table('tr_refund')->selectRaw("DATE_FORMAT(refund_date,'%Y-%m') as period, 0 as income, SUM(amount) as expense")->where('status', 'processed')->whereBetween('refund_date', [$start, $end])
            );

        $query = DB::query()->fromSub($union, 't')
            ->selectRaw("period, SUM(income) as income, SUM(expense) as expense, SUM(income)-SUM(expense) as profit")
            ->groupBy('period')
            ->orderByDesc('period');

        return datatables()->of($query)
            ->addColumn('period', fn($r) => $r->period)
            ->addColumn('income', fn($r) => 'Rp ' . number_format($r->income, 0, ',', '.'))
            ->addColumn('expense', fn($r) => 'Rp ' . number_format($r->expense, 0, ',', '.'))
            ->addColumn('profit', fn($r) => 'Rp ' . number_format($r->profit, 0, ',', '.'))
            ->toJson();
    }

    // ============================================================
    // EXPORT
    // ============================================================

    public function export(Request $request, $type)
    {
        try {
            $data = [];
            $filename = $type . '_' . now()->format('Ymd') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            switch ($type) {
                case 'revenue':
                    $rows = Payment::where('status', 'completed')
                        ->selectRaw("DATE_FORMAT(payment_date,'%M %Y') as period, SUM(amount) as revenue, COUNT(*) as count")
                        ->groupBy('period')->orderBy('period')->get();
                    $data[] = ['Periode', 'Pendapatan', 'Jumlah Pembayaran'];
                    foreach ($rows as $r) {
                        $data[] = [$r->period, $r->revenue, $r->count];
                    }
                    break;
                case 'fleet-utilization':
                    $rows = DB::table('tr_rental')
                        ->join('m_vehicle', 'tr_rental.vehicle_id', '=', 'm_vehicle.vehicle_id')
                        ->leftJoin('m_vehicle_model', 'm_vehicle.model_id', '=', 'm_vehicle_model.model_id')
                        ->leftJoin('m_brand', 'm_vehicle_model.brand_id', '=', 'm_brand.brand_id')
                        ->selectRaw("m_vehicle.license_plate, CONCAT(IFNULL(m_brand.brand_name,''),' ',IFNULL(m_vehicle_model.model_name,'')) as vehicle_name, COUNT(*) as total_rentals, SUM(tr_rental.rental_days) as total_days, SUM(tr_rental.total_amount) as total_revenue")
                        ->groupBy('m_vehicle.vehicle_id', 'm_vehicle.license_plate', 'vehicle_name')->get();
                    $data[] = ['Plat', 'Kendaraan', 'Jml Sewa', 'Hari', 'Pendapatan'];
                    foreach ($rows as $r) {
                        $data[] = [$r->license_plate, $r->vehicle_name, $r->total_rentals, $r->total_days, $r->total_revenue];
                    }
                    break;
                case 'top-customers':
                    $rows = DB::table('tr_rental')
                        ->join('m_customer', 'tr_rental.customer_id', '=', 'm_customer.customer_id')
                        ->selectRaw("CONCAT(IFNULL(m_customer.first_name,''),' ',IFNULL(m_customer.last_name,'')) as name, COUNT(*) as total_rentals, SUM(tr_rental.total_amount) as total_spend")
                        ->groupBy('m_customer.customer_id', 'name')->orderByDesc('total_spend')->get();
                    $data[] = ['Pelanggan', 'Jml Sewa', 'Total Belanja'];
                    foreach ($rows as $r) {
                        $data[] = [trim($r->name), $r->total_rentals, $r->total_spend];
                    }
                    break;
                case 'claims':
                    $rows = DamageReport::with(['vehicle', 'insuranceClaim'])->get();
                    $data[] = ['Kendaraan', 'Jenis', 'Severity', 'Status', 'Klaim', 'Nilai Klaim'];
                    foreach ($rows as $r) {
                        $data[] = [
                            $r->vehicle?->license_plate,
                            $r->damage_type,
                            $r->severity,
                            $r->status,
                            $r->insuranceClaim?->status ?? '-',
                            $r->insuranceClaim?->claim_amount ?? 0,
                        ];
                    }
                    break;
                case 'financial':
                    $union = DB::table('tr_payment')
                        ->selectRaw("DATE_FORMAT(payment_date,'%Y-%m') as period, SUM(amount) as income, 0 as expense")
                        ->where('status', 'completed')
                        ->unionAll(DB::table('tr_maintenance')->selectRaw("DATE_FORMAT(COALESCE(actual_date,scheduled_date),'%Y-%m') as period, 0 as income, SUM(cost) as expense")->whereNotNull('cost'))
                        ->unionAll(DB::table('tr_fine')->selectRaw("DATE_FORMAT(paid_date,'%Y-%m') as period, 0 as income, SUM(amount) as expense")->where('status', 'paid'))
                        ->unionAll(DB::table('tr_refund')->selectRaw("DATE_FORMAT(refund_date,'%Y-%m') as period, 0 as income, SUM(amount) as expense")->where('status', 'processed'));
                    $rows = DB::query()->fromSub($union, 't')
                        ->selectRaw("period, SUM(income) as income, SUM(expense) as expense, SUM(income)-SUM(expense) as profit")
                        ->groupBy('period')->orderBy('period')->get();
                    $data[] = ['Periode', 'Pendapatan', 'Beban', 'Laba'];
                    foreach ($rows as $r) {
                        $data[] = [$r->period, $r->income, $r->expense, $r->profit];
                    }
                    break;
                default:
                    return redirect()->back()->withErrors('Tipe laporan tidak dikenal.');
            }

            $callback = function () use ($data) {
                $handle = fopen('php://output', 'w');
                foreach ($data as $row) {
                    fputcsv($handle, $row);
                }
                fclose($handle);
            };

            return response()->stream($callback, 200, $headers);
        } catch (Exception $e) {
            return redirect()->back()->withErrors($e->getMessage());
        }
    }
}

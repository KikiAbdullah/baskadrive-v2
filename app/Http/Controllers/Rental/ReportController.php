<?php

namespace App\Http\Controllers\Rental;

use App\Http\Controllers\Controller;
use App\Models\DamageReport;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Rental;
use App\Support\AppSettings;
use App\Support\PdfDocument;
use App\Support\ReportFormat;
use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function dateRange(Request $request)
    {
        $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'tab' => ['nullable', 'string'],
            'status' => ['nullable', 'in:reported,assessment,repair_in_progress,repaired,claimed_insurance,written_off'],
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

    public function index(Request $request)
    {
        [$start, $end] = $this->dateRange($request);
        $tab = $request->get('tab', 'revenue');

        $tabs = [
            'revenue' => 'Pendapatan & Profit',
            'fleet' => 'Utilisasi Armada',
            'customers' => 'Top Pelanggan',
            'claims' => 'Klaim & Denda',
            'financial' => 'Laporan Keuangan',
        ];

        if (! array_key_exists($tab, $tabs)) {
            return redirect()->route('report.index')->withErrors('Tipe laporan tidak dikenal.');
        }

        $view = [
            'title' => 'Laporan',
            'subtitle' => 'Analitik & Laporan',
            'tab' => $tab,
            'tabs' => $tabs,
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ];

        if ($tab === 'revenue') {
            $view['totalRevenue'] = $this->revenueQuery($start, $end)->get()->sum('revenue');
            $view['totalInvoices'] = Invoice::whereBetween('issue_date', [$start, $end])->count();
            $view['outstanding'] = Invoice::whereIn('status', ['sent', 'partially_paid', 'overdue'])
                ->where('due_date', '<=', $end)
                ->where('issue_date', '<=', $end)
                ->sum(DB::raw('total_amount - paid_amount'));
        }

        if ($tab === 'financial') {
            $rows = $this->financialRows($start, $end);
            $view['income'] = $rows->sum('income');
            $view['expense'] = $rows->sum('expense');
            $view['profit'] = $view['income'] - $view['expense'];
        }

        return view('report.index')->with($view);
    }

    public function data(Request $request)
    {
        return match ($request->get('tab', 'revenue')) {
            'revenue' => $this->revenueData($request),
            'fleet' => $this->fleetUtilizationData($request),
            'customers' => $this->topCustomersData($request),
            'claims' => $this->claimsData($request),
            'financial' => $this->financialData($request),
            default => response()->json(['error' => 'Tipe laporan tidak dikenal.'], 422),
        };
    }

    private function validatePagination(Request $request): void
    {
        $request->validate([
            'length' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'start' => ['sometimes', 'integer', 'min:0'],
        ]);

        $request->merge([
            'length' => (int) $request->input('length', 10),
            'start' => (int) $request->input('start', 0),
        ]);
    }

    private function monthExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    private function revenueQuery(Carbon $start, Carbon $end): Builder
    {
        $period = $this->monthExpression('payment_date');

        return Payment::query()
            ->where('status', 'completed')
            ->whereBetween('payment_date', [$start, $end])
            ->selectRaw("{$period} as period, SUM(amount) as revenue, COUNT(*) as payments_count")
            ->groupByRaw($period)
            ->orderByDesc('period');
    }

    private function fleetQuery(Carbon $start, Carbon $end): Builder
    {
        $name = DB::connection()->getDriverName() === 'sqlite'
            ? "COALESCE(m_brand.brand_name, '') || ' ' || COALESCE(m_vehicle_model.model_name, '')"
            : "CONCAT(COALESCE(m_brand.brand_name, ''), ' ', COALESCE(m_vehicle_model.model_name, ''))";

        return Rental::query()
            ->join('m_vehicle', 'tr_rental.vehicle_id', '=', 'm_vehicle.vehicle_id')
            ->leftJoin('m_vehicle_model', 'm_vehicle.model_id', '=', 'm_vehicle_model.model_id')
            ->leftJoin('m_brand', 'm_vehicle_model.brand_id', '=', 'm_brand.brand_id')
            ->whereBetween('tr_rental.rental_start_date', [$start, $end])
            ->selectRaw("m_vehicle.vehicle_id, m_vehicle.license_plate, {$name} as vehicle_name,
                COUNT(*) as total_rentals, SUM(tr_rental.rental_days) as total_days,
                SUM(tr_rental.total_amount) as total_revenue")
            ->groupBy('m_vehicle.vehicle_id', 'm_vehicle.license_plate', 'm_brand.brand_name', 'm_vehicle_model.model_name')
            ->orderByDesc('total_rentals')
            ->orderBy('m_vehicle.vehicle_id');
    }

    private function customerQuery(Carbon $start, Carbon $end): Builder
    {
        $name = DB::connection()->getDriverName() === 'sqlite'
            ? "COALESCE(m_customer.first_name, '') || ' ' || COALESCE(m_customer.last_name, '') ||
                CASE WHEN m_customer.customer_type = 'corporate' THEN ' (' || COALESCE(m_customer.company_name, '') || ')' ELSE '' END"
            : "CONCAT(COALESCE(m_customer.first_name, ''), ' ', COALESCE(m_customer.last_name, ''),
                CASE WHEN m_customer.customer_type = 'corporate' THEN CONCAT(' (', COALESCE(m_customer.company_name, ''), ')') ELSE '' END)";

        return Rental::query()
            ->join('m_customer', 'tr_rental.customer_id', '=', 'm_customer.customer_id')
            ->whereBetween('tr_rental.rental_start_date', [$start, $end])
            ->selectRaw("m_customer.customer_id, {$name} as customer_name, m_customer.customer_type,
                COUNT(*) as total_rentals, SUM(tr_rental.total_amount) as total_spend,
                MAX(tr_rental.rental_start_date) as last_rental")
            ->groupBy('m_customer.customer_id', 'm_customer.first_name', 'm_customer.last_name', 'm_customer.company_name', 'm_customer.customer_type')
            ->orderByDesc('total_spend')
            ->orderBy('m_customer.customer_id');
    }

    private function claimsQuery(Request $request, Carbon $start, Carbon $end): Builder
    {
        return DamageReport::query()->with(['vehicle', 'insuranceClaim'])
            ->whereBetween('reported_date', [$start, $end])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status));
    }

    private function utilization($days, Carbon $start, Carbon $end): int
    {
        $rangeDays = max(1, (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);

        return (int) min(100, round((($days ?? 0) / $rangeDays) * 100));
    }

    private function financialRows(Carbon $start, Carbon $end): Collection
    {
        $pays = $this->revenueQuery($start, $end)->pluck('revenue', 'period');
        $maintenancePeriod = $this->monthExpression('actual_date');
        $finePeriod = $this->monthExpression('paid_date');
        $refundPeriod = $this->monthExpression('refund_date');

        $maint = DB::table('tr_maintenance')->whereNotNull('cost')->whereBetween('actual_date', [$start, $end])
            ->selectRaw("{$maintenancePeriod} as period, SUM(cost) as amount")
            ->groupByRaw($maintenancePeriod)->pluck('amount', 'period');
        $fines = DB::table('tr_fine')->where('status', 'paid')->whereBetween('paid_date', [$start, $end])
            ->selectRaw("{$finePeriod} as period, SUM(amount) as total")
            ->groupByRaw($finePeriod)->pluck('total', 'period');
        $refunds = Refund::query()->where('status', 'processed')->whereBetween('refund_date', [$start, $end])
            ->selectRaw("{$refundPeriod} as period, SUM(amount) as total")
            ->groupByRaw($refundPeriod)->pluck('total', 'period');

        return $pays->keys()->merge($maint->keys())->merge($fines->keys())->merge($refunds->keys())
            ->unique()->sortDesc()->values()->map(function ($period) use ($pays, $maint, $fines, $refunds) {
                $income = (float) ($pays[$period] ?? 0);
                $expense = (float) (($maint[$period] ?? 0) + ($fines[$period] ?? 0) + ($refunds[$period] ?? 0));

                return (object) [
                    'period' => $period,
                    'income' => $income,
                    'expense' => $expense,
                    'profit' => $income - $expense,
                ];
            });
    }

    // ============================================================
    // PENDAPATAN & PROFIT
    // ============================================================

    public function revenueData(Request $request)
    {
        [$start, $end] = $this->dateRange($request);

        $this->validatePagination($request);

        return datatables()->of($this->revenueQuery($start, $end))
            ->addColumn('period_label', fn ($r) => ReportFormat::month($r->period))
            ->orderColumn('period_label', 'period $1')
            ->addColumn('revenue', fn ($r) => (float) $r->revenue)
            ->addColumn('payments_count', fn ($r) => $r->payments_count)
            ->toJson();
    }

    // ============================================================
    // UTILISASI ARMADA
    // ============================================================

    public function fleetUtilizationData(Request $request)
    {
        [$start, $end] = $this->dateRange($request);
        $this->validatePagination($request);

        return datatables()->of($this->fleetQuery($start, $end))
            ->addColumn('license_plate', fn ($v) => $v->license_plate)
            ->addColumn('vehicle_name', fn ($v) => trim($v->vehicle_name) ?: '-')
            ->addColumn('total_rentals', fn ($v) => $v->total_rentals)
            ->addColumn('total_days', fn ($v) => (int) ($v->total_days ?? 0))
            ->addColumn('utilization', fn ($v) => $this->utilization($v->total_days, $start, $end).'%')
            ->addColumn('total_revenue', fn ($v) => (float) ($v->total_revenue ?? 0))
            ->toJson();
    }

    // ============================================================
    // TOP PELANGGAN
    // ============================================================

    public function topCustomersData(Request $request)
    {
        [$start, $end] = $this->dateRange($request);

        $this->validatePagination($request);

        return datatables()->of($this->customerQuery($start, $end))
            ->addColumn('customer_name', fn ($c) => trim($c->customer_name) ?: '-')
            ->addColumn('customer_type', fn ($c) => ucfirst($c->customer_type ?? '-'))
            ->addColumn('total_rentals', fn ($c) => $c->total_rentals)
            ->addColumn('total_spend', fn ($c) => (float) ($c->total_spend ?? 0))
            ->addColumn('last_rental', fn ($c) => $c->last_rental ? Carbon::parse($c->last_rental)->format('d/m/Y') : '-')
            ->toJson();
    }

    // ============================================================
    // KLAIM & DENDA
    // ============================================================

    public function claimsData(Request $request)
    {
        [$start, $end] = $this->dateRange($request);

        $this->validatePagination($request);

        return datatables()->of($this->claimsQuery($request, $start, $end))
            ->addColumn('vehicle', fn ($d) => $d->vehicle?->license_plate ?? '-')
            ->addColumn('damage_type', fn ($d) => ucfirst(str_replace('_', ' ', $d->damage_type ?? '-')))
            ->addColumn('severity', fn ($d) => ucfirst($d->severity ?? '-'))
            ->addColumn('reported_date', fn ($d) => $d->reported_date?->format('d/m/Y') ?? '-')
            ->addColumn('repair_cost', fn ($d) => (float) ($d->actual_repair_cost ?? $d->repair_cost_estimate ?? 0))
            ->addColumn('status_badge', fn ($d) => view('components.damage-status-badge', ['status' => $d->status])->render())
            ->addColumn('claim_status', fn ($d) => $d->insuranceClaim ? view('components.claim-status-badge', ['status' => $d->insuranceClaim->status])->render() : '<span class="text-muted">-</span>')
            ->addColumn('claim_amount', fn ($d) => $d->insuranceClaim ? (float) ($d->insuranceClaim->claim_amount ?? 0) : null)
            ->rawColumns(['status_badge', 'claim_status'])
            ->toJson();
    }

    // ============================================================
    // LAPORAN KEUANGAN
    // ============================================================

    public function financialData(Request $request)
    {
        [$start, $end] = $this->dateRange($request);

        $this->validatePagination($request);

        return datatables()->of($this->financialRows($start, $end))
            ->editColumn('period', fn ($r) => ReportFormat::month($r->period))
            ->addColumn('income', fn ($r) => (float) $r->income)
            ->addColumn('expense', fn ($r) => (float) $r->expense)
            ->addColumn('profit', fn ($r) => (float) $r->profit)
            ->toJson();
    }

    // ============================================================
    // EXPORT (CSV & PDF) — dengan filter rentang tanggal (audit 2.7)
    // ============================================================

    private function exportRows(Request $request, string $type): ?array
    {
        $columns = match ($type) {
            'revenue' => [
                ['Periode', 'Pendapatan', 'Jumlah Pembayaran'],
                ['text', 'money', 'integer'],
            ],
            'fleet-utilization' => [
                ['Plat', 'Kendaraan', 'Jml Sewa', 'Hari', 'Utilisasi', 'Pendapatan'],
                ['text', 'text', 'integer', 'integer', 'percent', 'money'],
            ],
            'top-customers' => [
                ['Pelanggan', 'Jml Sewa', 'Total Belanja'],
                ['text', 'integer', 'money'],
            ],
            'claims' => [
                ['Kendaraan', 'Jenis', 'Severity', 'Status', 'Klaim', 'Nilai Klaim'],
                ['text', 'text', 'text', 'text', 'text', 'money'],
            ],
            'financial' => [
                ['Periode', 'Pendapatan', 'Beban', 'Laba'],
                ['text', 'money', 'money', 'money'],
            ],
            default => null,
        };

        if ($columns === null) {
            return null;
        }

        [$start, $end] = $this->dateRange($request);
        [$header, $columnTypes] = $columns;

        $rows = (function () use ($request, $type, $start, $end, $header) {
            yield $header;

            $records = match ($type) {
                'revenue' => $this->revenueQuery($start, $end)->lazy(500),
                'fleet-utilization' => $this->fleetQuery($start, $end)->lazy(500),
                'top-customers' => $this->customerQuery($start, $end)->lazy(500),
                'claims' => $this->claimsQuery($request, $start, $end)->orderBy('damage_id')->lazy(500),
                'financial' => $this->financialRows($start, $end),
            };

            foreach ($records as $r) {
                yield match ($type) {
                    'revenue' => [ReportFormat::month($r->period), $r->revenue, $r->payments_count],
                    'fleet-utilization' => [
                        $r->license_plate,
                        trim($r->vehicle_name) ?: '-',
                        $r->total_rentals,
                        (int) ($r->total_days ?? 0),
                        $this->utilization($r->total_days, $start, $end),
                        $r->total_revenue ?? 0,
                    ],
                    'top-customers' => [trim($r->customer_name) ?: '-', $r->total_rentals, $r->total_spend ?? 0],
                    'claims' => [
                        $r->vehicle?->license_plate ?? '-',
                        $r->damage_type,
                        $r->severity,
                        $r->status,
                        $r->insuranceClaim?->status ?? '-',
                        $r->insuranceClaim?->claim_amount ?? 0,
                    ],
                    'financial' => [ReportFormat::month($r->period), $r->income, $r->expense, $r->profit],
                };
            }
        })();

        return [
            'rows' => $rows,
            'columnTypes' => $columnTypes,
            'start' => $start,
            'end' => $end,
            'note' => $type === 'fleet-utilization'
                ? 'Utilisasi = jumlah rental_days untuk sewa yang dimulai dalam periode, semua status, dibagi jumlah hari kalender inklusif dalam periode; maksimal 100%.'
                : null,
        ];
    }

    public function export(Request $request, $type)
    {
        try {
            $result = $this->exportRows($request, $type);
            if ($result === null) {
                return redirect()->back()->withErrors('Tipe laporan tidak dikenal.');
            }
            $filename = $type.'_'.now()->format('Ymd').'.csv';
            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ];

            $callback = function () use ($result) {
                $handle = fopen('php://output', 'w');
                try {
                    fwrite($handle, "\xEF\xBB\xBF");
                    foreach ($result['rows'] as $index => $row) {
                        $cells = [];
                        foreach ($row as $column => $value) {
                            $cells[] = ReportFormat::cell($value, $index === 0 ? 'text' : $result['columnTypes'][$column], true);
                        }
                        fputcsv($handle, $cells, ';', '"', '');
                    }
                } catch (ValidationException $e) {
                    throw $e;
                } catch (Exception $e) {
                    report($e);
                    throw $e;
                } finally {
                    fclose($handle);
                }
            };

            return response()->stream($callback, 200, $headers);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            report($e);

            return redirect()->route('report.index')->withErrors('Laporan gagal diekspor. Silakan coba lagi.');
        }
    }

    public function exportPdf(Request $request, $type)
    {
        try {
            $result = $this->exportRows($request, $type);
            if ($result === null) {
                return redirect()->back()->withErrors('Tipe laporan tidak dikenal.');
            }

            $rows = [];
            foreach ($result['rows'] as $index => $row) {
                if ($index > 2000) {
                    throw ValidationException::withMessages(['type' => 'Ekspor PDF maksimal 2000 baris data. Persempit filter atau gunakan CSV.']);
                }
                $rows[] = $row;
            }

            $labels = [
                'revenue' => 'Laporan Pendapatan & Profit',
                'fleet-utilization' => 'Laporan Utilisasi Armada',
                'top-customers' => 'Laporan Top Pelanggan',
                'claims' => 'Laporan Klaim & Denda',
                'financial' => 'Laporan Keuangan',
            ];

            return PdfDocument::download(
                'report.pdf',
                [
                    'reportTitle' => $labels[$type] ?? 'Laporan',
                    'rows' => $rows,
                    'columnTypes' => $result['columnTypes'],
                    'note' => $result['note'],
                    'start' => $result['start']->format('d/m/Y'),
                    'end' => $result['end']->format('d/m/Y'),
                    'settings' => AppSettings::all(),
                ],
                'Laporan-'.$type.'-'.now()->format('Ymd')
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            report($e);

            return redirect()->route('report.index')->withErrors('Laporan gagal diekspor. Silakan coba lagi.');
        }
    }
}

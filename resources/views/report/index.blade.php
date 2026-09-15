@extends('layouts.header')

@section('customcss')
    <style>
        /* ===== Tab pill ===== */
        .rpt-tabs {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .rpt-tab {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            border: 1px solid #d9dee3;
            background: #fff;
            color: #697a8d;
            font-size: 0.8125rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.15s;
        }

        .rpt-tab:hover {
            border-color: #666cff;
            color: #666cff;
        }

        .rpt-tab.active {
            background: #666cff;
            border-color: #666cff;
            color: #fff;
        }

        /* ===== Summary cards ===== */
        .rpt-stat {
            border: 1px solid #e5e5e8;
            border-radius: 0.625rem;
            padding: 1rem 1.15rem;
            background: #fff;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .rpt-stat::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 3px;
            height: 100%;
            background: #666cff;
        }

        .rpt-stat .stat-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #a8aab4;
            margin-bottom: 0.3rem;
        }

        .rpt-stat .stat-value {
            font-size: 1.35rem;
            font-weight: 900;
            color: #3b4055;
            line-height: 1.2;
        }

        .rpt-stat .stat-value.text-success { color: #3ea016; }
        .rpt-stat .stat-value.text-danger { color: #d33a3a; }

        .rpt-stat .stat-icon {
            position: absolute;
            top: 0.9rem;
            right: 1rem;
            font-size: 1.6rem;
            color: #666cff29;
        }

        .rpt-stat.green::before { background: #72e128; }
        .rpt-stat.green .stat-icon { color: #72e12845; }
        .rpt-stat.red::before { background: #ff4d49; }
        .rpt-stat.red .stat-icon { color: #ff4d4945; }
        .rpt-stat.amber::before { background: #fdb528; }
        .rpt-stat.amber .stat-icon { color: #fdb52845; }

        /* ===== Filter bar ===== */
        .rpt-filter {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .rpt-filter .form-control-sm,
        .rpt-filter .form-select-sm {
            min-width: 130px;
        }

        /* ===== Progress bar utk utilisasi ===== */
        .util-bar {
            background: #eef0ff;
            border-radius: 20px;
            height: 8px;
            width: 90px;
            overflow: hidden;
            display: inline-block;
            vertical-align: middle;
            margin-right: 6px;
        }

        .util-bar > div {
            height: 100%;
            background: #666cff;
            border-radius: 20px;
        }

        /* ===== Table header align ===== */
        table.dataTable thead th.r {
            text-align: right;
        }

        table.dataTable tbody td.r {
            text-align: right;
        }
    </style>
@endsection

@php
    $exportTypes = [
        'revenue' => 'revenue',
        'fleet' => 'fleet-utilization',
        'customers' => 'top-customers',
        'claims' => 'claims',
        'financial' => 'financial',
    ];

    $tabIcons = [
        'revenue' => 'ri-line-chart-line',
        'fleet' => 'ri-pie-chart-2-line',
        'customers' => 'ri-award-line',
        'claims' => 'ri-alert-line',
        'financial' => 'ri-money-dollar-circle-line',
    ];
@endphp

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        {{-- ===== Header ===== --}}
        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2 row-gap-3">
            <div class="d-flex flex-column justify-content-center">
                <h4 class="mb-1">{{ $title }}</h4>
                <p class="mb-0">{{ $subtitle }} — <strong>{{ $tabs[$tab] }}</strong></p>
            </div>
            <div class="d-flex align-content-center flex-wrap gap-4">
                <a href="{{ route('report.export', $exportTypes[$tab]) }}" id="btnExportCsv" class="action-link-icon-text">
                    <i class="ri-download-2-line"></i>
                    <span class="fw-semibold text-uppercase">Export CSV</span>
                </a>
                <a href="{{ route('report.export-pdf', $exportTypes[$tab]) }}" id="btnExportPdf" class="action-link-icon-text">
                    <i class="ri-file-pdf-2-line"></i>
                    <span class="fw-semibold text-uppercase">Export PDF</span>
                </a>
            </div>
        </div>

        @include('layouts.alert')

        {{-- ===== TABS ===== --}}
        <div class="rpt-tabs mb-1">
            @foreach($tabs as $key => $label)
                <a href="{{ route('report.index', ['tab' => $key]) }}"
                    class="rpt-tab {{ $tab == $key ? 'active' : '' }}">
                    <i class="{{ $tabIcons[$key] }}"></i> {{ $label }}
                </a>
            @endforeach
        </div>

        {{-- ===== FILTER BAR ===== --}}
        <div class="card mb-1">
            <div class="card-body py-2">
                <div class="rpt-filter">
                    <i class="ri-filter-3-line text-muted"></i>
                    <strong class="small text-muted">Filter:</strong>
                    <input type="date" class="form-control form-control-sm" id="startDate" value="{{ $start }}">
                    <span class="text-muted small">s/d</span>
                    <input type="date" class="form-control form-control-sm" id="endDate" value="{{ $end }}">
                    @if($tab === 'claims')
                        <select class="form-select form-select-sm w-auto" id="statusFilter">
                            <option value="">Semua Status</option>
                            <option value="reported">Laporan</option>
                            <option value="assessment">Assessment</option>
                            <option value="repair_in_progress">Diperbaiki</option>
                            <option value="repaired">Selesai</option>
                            <option value="claimed_insurance">Diklaim</option>
                        </select>
                    @endif
                </div>
            </div>
        </div>

        {{-- ===== SUMMARY CARDS ===== --}}
        @if($tab === 'revenue')
            <div class="row g-3 mb-1">
                <div class="col-md-4">
                    <div class="rpt-stat green">
                        <i class="stat-icon ri-money-dollar-circle-line"></i>
                        <div class="stat-label">Total Pendapatan</div>
                        <div class="stat-value text-success">Rp {{ number_format($totalRevenue ?? 0, 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="rpt-stat">
                        <i class="stat-icon ri-file-list-3-line"></i>
                        <div class="stat-label">Jumlah Invoice</div>
                        <div class="stat-value">{{ number_format($totalInvoices ?? 0) }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="rpt-stat red">
                        <i class="stat-icon ri-error-warning-line"></i>
                        <div class="stat-label">Piutang (Jatuh Tempo)</div>
                        <div class="stat-value text-danger">Rp {{ number_format($outstanding ?? 0, 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        @elseif($tab === 'financial')
            <div class="row g-3 mb-1">
                <div class="col-md-4">
                    <div class="rpt-stat green">
                        <i class="stat-icon ri-arrow-up-circle-line"></i>
                        <div class="stat-label">Total Pendapatan</div>
                        <div class="stat-value text-success">Rp {{ number_format($income ?? 0, 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="rpt-stat red">
                        <i class="stat-icon ri-arrow-down-circle-line"></i>
                        <div class="stat-label">Total Beban</div>
                        <div class="stat-value text-danger">Rp {{ number_format($expense ?? 0, 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="rpt-stat {{ ($profit ?? 0) >= 0 ? 'green' : 'red' }}">
                        <i class="stat-icon ri-funds-line"></i>
                        <div class="stat-label">Laba Bersih</div>
                        <div class="stat-value {{ ($profit ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">Rp {{ number_format($profit ?? 0, 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ===== GRAFIK VISUAL (audit 2.7) ===== --}}
        @if(in_array($tab, ['revenue', 'fleet']))
            <div class="card mb-1">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="ri-bar-chart-2-line me-1"></i> {{ $tab === 'revenue' ? 'Tren Pendapatan Bulanan' : 'Pendapatan per Kendaraan' }}</h6>
                </div>
                <div class="card-body">
                    <div id="rptChart"></div>
                </div>
            </div>
        @endif

        {{-- ===== TABEL ===== --}}
        <div class="card">
            <div class="card-datatable table-responsive">
                <table class="table table-xxs dataTable" id="dtable">
                    <thead>
                        @if($tab === 'revenue')
                            <tr>
                                <th>Periode</th>
                                <th class="r">Pendapatan</th>
                                <th class="r">Jml Pembayaran</th>
                            </tr>
                        @elseif($tab === 'fleet')
                            <tr>
                                <th>Plat</th>
                                <th>Kendaraan</th>
                                <th class="r">Jml Sewa</th>
                                <th class="r">Total Hari</th>
                                <th class="r">Utilisasi (periode)</th>
                                <th class="r">Pendapatan</th>
                            </tr>
                        @elseif($tab === 'customers')
                            <tr>
                                <th>Pelanggan</th>
                                <th>Tipe</th>
                                <th class="r">Jml Sewa</th>
                                <th class="r">Total Belanja</th>
                                <th class="r">Sewa Terakhir</th>
                            </tr>
                        @elseif($tab === 'claims')
                            <tr>
                                <th>Kendaraan</th>
                                <th>Jenis</th>
                                <th>Severity</th>
                                <th>Tgl Lapor</th>
                                <th class="r">Biaya Perbaikan</th>
                                <th>Status Kerusakan</th>
                                <th>Status Klaim</th>
                                <th class="r">Nilai Klaim</th>
                            </tr>
                        @else
                            <tr>
                                <th>Periode</th>
                                <th class="r">Pendapatan</th>
                                <th class="r">Beban</th>
                                <th class="r">Laba</th>
                            </tr>
                        @endif
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('customjs')
    <script type="text/javascript">
        var dtable;
        const urlAjax = '{{ route('report.data', ['tab' => $tab]) }}';

        // ===== Render kolom khusus =====
        const renderRp = (num) => 'Rp ' + new Intl.NumberFormat('id-ID').format(parseFloat(num || 0));

        $(document).ready(function() {
            @php
                $colDefs = [
                    'revenue' => [
                        ['data' => 'period_label'],
                        ['data' => 'revenue', 'render' => 'rp'],
                        ['data' => 'payments_count', 'className' => 'r'],
                    ],
                    'fleet' => [
                        ['data' => 'license_plate', 'render' => 'bold'],
                        ['data' => 'vehicle_name'],
                        ['data' => 'total_rentals', 'className' => 'r'],
                        ['data' => 'total_days', 'className' => 'r', 'render' => 'days'],
                        ['data' => 'utilization', 'className' => 'r', 'render' => 'util'],
                        ['data' => 'total_revenue', 'className' => 'r', 'render' => 'rp'],
                    ],
                    'customers' => [
                        ['data' => 'customer_name', 'render' => 'bold'],
                        ['data' => 'customer_type'],
                        ['data' => 'total_rentals', 'className' => 'r'],
                        ['data' => 'total_spend', 'className' => 'r', 'render' => 'rp'],
                        ['data' => 'last_rental', 'className' => 'r'],
                    ],
                    'claims' => [
                        ['data' => 'vehicle', 'render' => 'bold'],
                        ['data' => 'damage_type'],
                        ['data' => 'severity'],
                        ['data' => 'reported_date'],
                        ['data' => 'repair_cost', 'className' => 'r', 'render' => 'rp'],
                        ['data' => 'status_badge'],
                        ['data' => 'claim_status'],
                        ['data' => 'claim_amount', 'className' => 'r', 'render' => 'rp'],
                    ],
                    'financial' => [
                        ['data' => 'period', 'render' => 'bold'],
                        ['data' => 'income', 'className' => 'r', 'render' => 'rp'],
                        ['data' => 'expense', 'className' => 'r', 'render' => 'rp'],
                        ['data' => 'profit', 'className' => 'r', 'render' => 'profit'],
                    ],
                ];
                $orders = ['revenue' => [0, 'desc'], 'fleet' => [2, 'desc'], 'customers' => [3, 'desc'], 'claims' => [3, 'desc'], 'financial' => [0, 'desc']];
            @endphp

            const columns = @json($colDefs[$tab] ?? []);
            columns.forEach(col => {
                col.render = (() => {
                    switch (col.render) {
                        case 'rp': return (d, t, row) => renderRp(d);
                        case 'days': return (d) => new Intl.NumberFormat('id-ID').format(d || 0) + ' hari';
                        case 'util': return (d) => {
                            const pct = parseInt(d) || 0;
                            return '<div class="util-bar"><div style="width:' + pct + '%"></div></div>' + pct + '%';
                        };
                        case 'bold': return (d) => '<strong>' + (d || '-') + '</strong>';
                        case 'profit': return (d) => {
                            const v = parseFloat(d || 0);
                            const cls = v >= 0 ? 'text-success' : 'text-danger';
                            return '<strong class="' + cls + '">' + renderRp(v) + '</strong>';
                        };
                        default: return col.render;
                    }
                })();
            });

            dtable = $('#dtable').DataTable({
                "serverSide": true,
                "stateSave": true,
                "sServerMethod": "GET",
                "deferRender": true,
                "ajax": {
                    url: urlAjax,
                    data: function(d) {
                        d.start_date = $('#startDate').val();
                        d.end_date = $('#endDate').val();
                        @if($tab === 'claims')
                        d.status = $('#statusFilter').val();
                        @endif
                    }
                },
                "columns": columns,
                "order": @json($orders[$tab] ?? [0, 'desc']),
                "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 d-flex justify-content-center justify-content-md-end"f>><"table-responsive"t><"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            });

            $('#startDate, #endDate, #statusFilter').on('change', function() {
                dtable.ajax.reload();
                syncExportLinks();
                @if(in_array($tab, ['revenue', 'fleet']))
                loadChart();
                @endif
            });

            function syncExportLinks() {
                const qs = '?start_date=' + ($('#startDate').val() || '') + '&end_date=' + ($('#endDate').val() || '');
                $('#btnExportCsv').attr('href', $('#btnExportCsv').attr('href').split('?')[0] + qs);
                $('#btnExportPdf').attr('href', $('#btnExportPdf').attr('href').split('?')[0] + qs);
            }
            syncExportLinks();

            @if(in_array($tab, ['revenue', 'fleet']))
            let chartInstance = null;
            function loadChart() {
                $.getJSON(urlAjax, {
                    draw: 1, start: 0, length: 1000,
                    start_date: $('#startDate').val(),
                    end_date: $('#endDate').val()
                }, function(res) {
                    const rows = res.data || [];
                    @if($tab === 'revenue')
                    const cats = rows.map(r => r.period_label).reverse();
                    const series = rows.map(r => parseFloat(r.revenue)).reverse();
                    const type = 'area';
                    @else
                    const cats = rows.slice(0, 15).map(r => r.license_plate);
                    const series = rows.slice(0, 15).map(r => parseFloat(r.total_revenue));
                    const type = 'bar';
                    @endif
                    const config = {
                        chart: { type: type, height: 280, toolbar: { show: false }, sparkline: { enabled: false } },
                        series: [{ name: 'Pendapatan (Rp)', data: series }],
                        xaxis: { categories: cats },
                        yaxis: { labels: { formatter: v => (v >= 1000000 ? (v / 1000000).toFixed(1) + ' jt' : (v || 0)) } },
                        colors: ['#666cff'],
                        dataLabels: { enabled: false },
                        legend: { show: false }
                    };
                    if (chartInstance) { chartInstance.destroy(); }
                    chartInstance = new ApexCharts(document.querySelector('#rptChart'), config);
                    chartInstance.render();
                });
            }
            loadChart();
            @endif
        });
    </script>
@endsection

@section('appmodal')
    <div id="mymodal" class="modal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-indigo text-white">
                    <h5 class="modal-title"></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body"></div>
            </div>
        </div>
    </div>
@endsection

@section('notification')
    @include('layouts.notification')
@endsection
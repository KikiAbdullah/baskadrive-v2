@extends('layouts.header')

@section('customcss')
    <style>
        .kpi-card .card-info p { margin-bottom: 0; color: #a8aab4; font-size: .8125rem; }
        .mini-label { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: #a8aab4; }
        .queue-item { display: flex; justify-content: space-between; gap: .75rem; align-items: center; padding: .5rem 0; border-bottom: 1px dashed rgba(148, 151, 165, .3); text-decoration: none; color: inherit; }
        .queue-item:last-child { border-bottom: none; }
        .queue-item:hover { color: #666cff; }
        .queue-item .queue-main { min-width: 0; }
        .queue-item .queue-title { font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .queue-item .queue-sub { font-size: .75rem; color: #a8aab4; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .legend-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: .35rem; }
        .stat-line { display: flex; justify-content: space-between; align-items: center; padding: .35rem 0; }
        .stat-line .stat-name { display: flex; align-items: center; gap: .5rem; color: #6b7280; }
    </style>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">

        @include('layouts.alert')

        {{-- ================= HEADER SAPAAN ================= --}}
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 row-gap-3">
            <div>
                <h4 class="mb-1">{{ $greeting }}, {{ $userName }}! &#128075;</h4>
                <p class="mb-0 text-muted">{{ $today->translatedFormat('l, d F Y') }} &mdash; ringkasan {{ $roleLabel }}</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                @can('rental_add')
                    <a href="{{ route('rental.create') }}" class="btn btn-primary"><i class="ri-add-line me-1"></i> Sewa Baru</a>
                @endcan
                @can('rental_view')
                    <a href="{{ route('dashboard.index') }}" class="btn btn-label-secondary"><i class="ri-dashboard-line me-1"></i> Dashboard Operasional</a>
                @endcan
                @can('report_view')
                    <a href="{{ route('report.index') }}" class="btn btn-label-secondary"><i class="ri-bar-chart-2-line me-1"></i> Laporan</a>
                @endcan
            </div>
        </div>

        {{-- ================= BARIS KPI ================= --}}
        <div class="row g-6 mb-6">
            {{-- KPI utama: penerimaan hari ini (keuangan) atau armada tersedia (non-keuangan) --}}
            <div class="col-xxl-3 col-lg-4 col-md-6">
                <div class="card kpi-card h-100 border-primary shadow-none">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="avatar">
                                <div class="avatar-initial bg-label-primary rounded-3"><i class="ri-money-dollar-circle-line ri-24px"></i></div>
                            </div>
                            @isset($receivedToday)
                                <span class="badge bg-label-primary">Hari Ini</span>
                            @endisset
                        </div>
                        @isset($receivedToday)
                            <div class="card-info mt-4">
                                <h5 class="mb-1">{{ \App\Support\AppSettings::money($receivedToday) }}</h5>
                                <p>Penerimaan pembayaran hari ini</p>
                                <div class="mini-label mt-2">Bulan ini: {{ \App\Support\AppSettings::money($receivedMonth) }}</div>
                            </div>
                        @else
                            @isset($vehicleStatus)
                                <div class="card-info mt-4">
                                    <h5 class="mb-1">{{ $vehicleStatus->get('available', 0) }} / {{ $vehicleStatus->sum() }}</h5>
                                    <p>Unit kendaraan tersedia</p>
                                    <div class="mini-label mt-2">Sewa berjalan: {{ $rentalStatus->get('ongoing', 0) }}</div>
                                </div>
                            @else
                                <div class="card-info mt-4">
                                    <h5 class="mb-1">&mdash;</h5>
                                    <p>Data armada butuh akses modul Sewa</p>
                                </div>
                            @endisset
                        @endisset
                    </div>
                </div>
            </div>

            @isset($receivableAmount)
                <div class="col-xxl-3 col-lg-4 col-md-6">
                    <div class="card kpi-card h-100 {{ $overdueInvoiceCount > 0 ? 'border-danger' : 'shadow-none' }}">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="avatar">
                                    <div class="avatar-initial bg-label-warning rounded-3"><i class="ri-bill-line ri-24px"></i></div>
                                </div>
                                @if($overdueInvoiceCount > 0)
                                    <span class="badge bg-label-danger">{{ $overdueInvoiceCount }} jatuh tempo</span>
                                @endif
                            </div>
                            <div class="card-info mt-4">
                                <h5 class="mb-1">{{ \App\Support\AppSettings::money($receivableAmount) }}</h5>
                                <p>Piutang belum tertagih ({{ $receivableCount }} invoice)</p>
                                <div class="mini-label mt-2">Nilai tertunggak: {{ \App\Support\AppSettings::money($overdueInvoiceAmount) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endisset

            <div class="col-xxl-3 col-lg-4 col-md-6">
                <div class="card kpi-card h-100 {{ ($overdueCount ?? 0) > 0 ? 'border-warning' : 'shadow-none' }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="avatar">
                                <div class="avatar-initial bg-label-info rounded-3"><i class="ri-drive-line ri-24px"></i></div>
                            </div>
                            @isset($overdueCount)
                                @if($overdueCount > 0)
                                    <span class="badge bg-label-warning">{{ $overdueCount }} terlambat</span>
                                @endif
                            @endisset
                        </div>
                        <div class="card-info mt-4">
                            @isset($rentalStatus)
                                <h5 class="mb-1">{{ $activeRentals ?? $rentalStatus->get('ongoing', 0) }}</h5>
                                <p>Sewa berjalan (aktif)</p>
                                <div class="mini-label mt-2">Reservasi menunggu: {{ $rentalStatus->get('reserved', 0) }}</div>
                            @else
                                <h5 class="mb-1">&mdash;</h5>
                                <p>Ringkasan sewa butuh akses modul Sewa</p>
                            @endisset
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xxl-3 col-lg-4 col-md-6">
                <div class="card kpi-card h-100 {{ ($severeDamageCount ?? 0) > 0 ? 'border-danger' : 'shadow-none' }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="avatar">
                                <div class="avatar-initial bg-label-danger rounded-3"><i class="ri-car-line ri-24px"></i></div>
                            </div>
                            @isset($openDamageCount)
                                <span class="badge bg-label-secondary">Armada</span>
                            @endisset
                        </div>
                        <div class="card-info mt-4">
                            @isset($vehicleStatus)
                                <h5 class="mb-1">{{ $vehicleStatus->get('available', 0) }} / {{ $vehicleStatus->sum() }}</h5>
                                <p>Unit tersedia dari total armada</p>
                                <div class="mini-label mt-2">
                                    Maintenance: {{ $vehicleStatus->get('maintenance', 0) }}@isset($openDamageCount) &middot; Kerusakan aktif: {{ $openDamageCount }}@if(($severeDamageCount ?? 0) > 0) ({{ $severeDamageCount }} berat)@endif @endisset
                                </div>
                            @else
                                <h5 class="mb-1">&mdash;</h5>
                                <p>Data armada butuh akses modul Sewa</p>
                            @endisset
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ================= TREN & STATUS ================= --}}
        @isset($trend)
            <div class="row g-6 mb-6">
                <div class="col-xxl-8 col-lg-7">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title m-0">Tren Sewa &amp; Penerimaan &mdash; 7 Hari Terakhir</h5>
                            <span class="badge bg-label-primary">Real-time</span>
                        </div>
                        <div class="card-body">
                            <div id="trendChart" style="min-height: 300px;"></div>
                        </div>
                    </div>
                </div>
                <div class="col-xxl-4 col-lg-5">
                    <div class="card h-100">
                        <div class="card-header"><h5 class="card-title m-0">Status Armada &amp; Sewa</h5></div>
                        <div class="card-body">
                            <p class="mini-label mb-2">Armada ({{ $vehicleStatus->sum() }} unit)</p>
                            @foreach(['available' => ['Tersedia', 'success'], 'rented' => ['Disewa', 'primary'], 'reserved' => ['Reservasi', 'info'], 'maintenance' => ['Maintenance', 'warning']] as $key => [$label, $color])
                                <div class="stat-line">
                                    <span class="stat-name"><span class="legend-dot bg-{{ $color }}"></span> {{ $label }}</span>
                                    <span class="badge bg-label-{{ $color }}">{{ $vehicleStatus->get($key, 0) }}</span>
                                </div>
                            @endforeach
                            @php $otherVehicles = $vehicleStatus->sum() - $vehicleStatus->only(['available', 'rented', 'reserved', 'maintenance'])->sum(); @endphp
                            @if($otherVehicles > 0)
                                <div class="stat-line">
                                    <span class="stat-name"><span class="legend-dot bg-secondary"></span> Lainnya</span>
                                    <span class="badge bg-label-secondary">{{ $otherVehicles }}</span>
                                </div>
                            @endif

                            <hr class="my-3" />
                            <p class="mini-label mb-2">Sewa ({{ $rentalStatus->sum() }} total)</p>
                            @foreach(['ongoing' => ['Berjalan', 'info'], 'reserved' => ['Reservasi', 'warning'], 'completed' => ['Selesai', 'success'], 'overdue' => ['Terlambat', 'danger'], 'cancelled' => ['Dibatalkan', 'secondary']] as $key => [$label, $color])
                                <div class="stat-line">
                                    <span class="stat-name"><span class="legend-dot bg-{{ $color }}"></span> {{ $label }}</span>
                                    <span class="badge bg-label-{{ $color }}">{{ $rentalStatus->get($key, 0) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endisset

        {{-- ================= ANTRIAN OPERASIONAL ================= --}}
        @isset($pickupToday)
            <div class="row g-6 mb-6">
                <div class="col-xxl-4 col-lg-6">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="ri-takeaway-line me-1 text-info"></i> Serah Terima Hari Ini</h6>
                            <a href="{{ route('rental.index', ['status' => 'reserved']) }}" class="btn btn-sm btn-outline-primary">Semua</a>
                        </div>
                        <div class="card-body py-2">
                            @forelse($pickupToday as $r)
                                <a href="{{ route('rental.show', $r->rental_id) }}" class="queue-item">
                                    <div class="queue-main">
                                        <div class="queue-title">{{ $r->rental_code }} &mdash; {{ $r->customer?->full_name ?? '-' }}</div>
                                        <div class="queue-sub">{{ $r->vehicle?->license_plate ?? '-' }} &middot; {{ $r->pickupLocation?->location_name ?? '-' }}</div>
                                    </div>
                                    <span class="badge bg-label-info">{{ $r->rental_start_date?->format('H:i') }}</span>
                                </a>
                            @empty
                                <p class="text-muted py-2 mb-0">Tidak ada serah terima terjadwal hari ini.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="col-xxl-4 col-lg-6">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="ri-arrow-go-back-line me-1 text-success"></i> Pengembalian Hari Ini</h6>
                            <a href="{{ route('rental.index', ['status' => 'ongoing']) }}" class="btn btn-sm btn-outline-primary">Semua</a>
                        </div>
                        <div class="card-body py-2">
                            @forelse($returnToday as $r)
                                <a href="{{ route('rental.show', $r->rental_id) }}" class="queue-item">
                                    <div class="queue-main">
                                        <div class="queue-title">{{ $r->rental_code }} &mdash; {{ $r->customer?->full_name ?? '-' }}</div>
                                        <div class="queue-sub">{{ $r->vehicle?->license_plate ?? '-' }} &middot; {{ $r->returnLocation?->location_name ?? '-' }}</div>
                                    </div>
                                    <span class="badge bg-label-success">{{ $r->rental_end_date?->format('H:i') }}</span>
                                </a>
                            @empty
                                <p class="text-muted py-2 mb-0">Tidak ada pengembalian terjadwal hari ini.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="col-xxl-4 col-lg-12">
                    <div class="card h-100 {{ $overdueRentals->isNotEmpty() ? 'border-danger' : '' }}">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="ri-alarm-warning-line me-1 text-danger"></i> Unit Terlambat Kembali</h6>
                            <a href="{{ route('rental.index', ['status' => 'overdue']) }}" class="btn btn-sm btn-outline-danger">Semua</a>
                        </div>
                        <div class="card-body py-2">
                            @forelse($overdueRentals as $r)
                                @php $lateDays = $r->rental_end_date ? now()->startOfDay()->diffInDays($r->rental_end_date->copy()->startOfDay()) : 0; @endphp
                                <a href="{{ route('rental.show', $r->rental_id) }}" class="queue-item">
                                    <div class="queue-main">
                                        <div class="queue-title">{{ $r->rental_code }} &mdash; {{ $r->customer?->full_name ?? '-' }}</div>
                                        <div class="queue-sub">{{ $r->vehicle?->license_plate ?? '-' }} &middot; seharusnya kembali {{ $r->rental_end_date?->translatedFormat('d M Y') }}</div>
                                    </div>
                                    <span class="badge bg-label-danger">{{ $lateDays }} hari</span>
                                </a>
                            @empty
                                <p class="text-muted py-2 mb-0">Semua unit kembali tepat waktu. &#127881;</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @endisset

        {{-- ================= KEUANGAN & FLEET ================= --}}
        @isset($methodBreakdown)
            <div class="row g-6 mb-6">
                <div class="col-xxl-6">
                    <div class="card h-100 {{ $overdueInvoiceCount > 0 ? 'border-danger' : '' }}">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="ri-file-warning-line me-1 text-danger"></i> Invoice Jatuh Tempo</h6>
                            <a href="{{ route('finance.index') }}" class="btn btn-sm btn-outline-primary">Kelola</a>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h4 class="text-danger mb-0">{{ \App\Support\AppSettings::money($overdueInvoiceAmount) }}</h4>
                                    <small class="text-muted">dari {{ $overdueInvoiceCount }} invoice melewati jatuh tempo</small>
                                </div>
                                <span class="avatar"><div class="avatar-initial bg-label-danger rounded-3"><i class="ri-error-warning-line ri-24px"></i></div></span>
                            </div>
                            <div class="stat-line border-top pt-2">
                                <span class="stat-name">Denda belum lunas / waived</span>
                                <span class="badge bg-label-warning">{{ \App\Support\AppSettings::money($openFineAmount) }} ({{ $openFineCount }})</span>
                            </div>
                            <div class="stat-line">
                                <span class="stat-name">Deposit jaminan tertahan</span>
                                <span class="badge bg-label-info">{{ \App\Support\AppSettings::money($depositHeld) }}</span>
                            </div>
                            <div class="stat-line">
                                <span class="stat-name">Piutang total (semua invoice)</span>
                                <span class="badge bg-label-primary">{{ \App\Support\AppSettings::money($receivableAmount) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xxl-6">
                    <div class="card h-100">
                        <div class="card-header"><h6 class="mb-0"><i class="ri-pie-chart-line me-1 text-primary"></i> Komposisi Penerimaan per Metode &mdash; Bulan Ini</h6></div>
                        <div class="card-body">
                            @if($methodBreakdown->isEmpty())
                                <p class="text-muted mb-0">Belum ada penerimaan bulan ini.</p>
                            @else
                                <div id="methodChart" style="min-height: 220px;"></div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endisset

        @isset($scheduledMaintenance)
            <div class="row g-6 mb-6">
                <div class="col-xxl-6">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="ri-tools-line me-1 text-warning"></i> Maintenance Terjadwal (30 Hari)</h6>
                            <a href="{{ route('fleet.maintenance.index') }}" class="btn btn-sm btn-outline-primary">Kelola</a>
                        </div>
                        <div class="card-body py-2">
                            @forelse($scheduledMaintenance as $m)
                                <div class="queue-item">
                                    <div class="queue-main">
                                        <div class="queue-title">{{ $m->vehicle?->license_plate ?? '-' }} &mdash; {{ $m->maintenanceType?->type_name ?? $m->description ?? 'Maintenance' }}</div>
                                        <div class="queue-sub">{{ $m->workshop?->workshop_name ?? '-' }} &middot; {{ number_format((float) $m->cost, 2, ',', '.') }}</div>
                                    </div>
                                    @php $isOverdueDate = $m->scheduled_date?->isPast(); @endphp
                                    <span class="badge bg-label-{{ $m->status === 'overdue' || $isOverdueDate ? 'danger' : 'warning' }}">{{ $m->scheduled_date?->translatedFormat('d M') }}</span>
                                </div>
                            @empty
                                <p class="text-muted py-2 mb-0">Tidak ada jadwal maintenance 30 hari ke depan.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="col-xxl-6">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="ri-shield-flash-line me-1 text-danger"></i> Kerusakan &amp; Klaim Asuransi Aktif</h6>
                            <a href="{{ route('fleet.damage.index') }}" class="btn btn-sm btn-outline-primary">Kelola</a>
                        </div>
                        <div class="card-body py-2">
                            @forelse($openDamage as $d)
                                <a href="{{ route('fleet.damage.show', $d->damage_id) }}" class="queue-item">
                                    <div class="queue-main">
                                        <div class="queue-title">{{ $d->vehicle?->license_plate ?? '-' }} &mdash; {{ $d->damage_type }} ({{ $d->severity }})</div>
                                        <div class="queue-sub">{{ Str::limit($d->description ?? '-', 60) }}</div>
                                    </div>
                                    @include('components.damage-status-badge', ['status' => $d->status])
                                </a>
                            @empty
                                <p class="text-muted py-2 mb-0">Tidak ada kerusakan aktif. Armada sehat.</p>
                            @endforelse
                            @if(($claimsInProgress?->total ?? 0) > 0)
                                <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                    <small class="text-muted">Klaim asuransi dalam proses: <strong>{{ $claimsInProgress->total }}</strong> &middot; nilai {{ \App\Support\AppSettings::money($claimsInProgress->amount) }}</small>
                                    <a href="{{ route('fleet.damage.index') }}" class="btn btn-sm btn-label-info">Lihat Klaim</a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endisset

        {{-- ================= AKUNTANSI ================= --}}
        @isset($ledger)
            <div class="row g-6 mb-6">
                <div class="col-xxl-5">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="ri-book-2-line me-1 text-info"></i> Neraca Ringkas</h6>
                            @if($balanceHealthy === true)
                                <span class="badge bg-label-success">Seimbang</span>
                            @elseif($balanceHealthy === false)
                                <span class="badge bg-label-danger">Tidak Seimbang</span>
                            @endif
                        </div>
                        <div class="card-body">
                            <div id="ledgerChart" style="min-height: 220px;"></div>
                            <div class="mt-3 d-flex flex-wrap gap-2">
                                <a href="{{ route('accounting.statement.balance') }}" class="btn btn-sm btn-label-primary">Neraca</a>
                                <a href="{{ route('accounting.statement.income') }}" class="btn btn-sm btn-label-primary">Laba Rugi</a>
                                <a href="{{ route('accounting.statement.cashflow') }}" class="btn btn-sm btn-label-primary">Arus Kas</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xxl-7">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="ri-file-list-3-line me-1 text-info"></i> Jurnal Terakhir ({{ $journalCount }} total)</h6>
                            <a href="{{ route('accounting.journal.index') }}" class="btn btn-sm btn-outline-primary">Buku Jurnal</a>
                        </div>
                        <div class="table-responsive text-nowrap">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr><th>Tanggal</th><th>Deskripsi</th><th>Tipe</th><th class="text-end">Aksi</th></tr>
                                </thead>
                                <tbody>
                                    @forelse($recentJournals as $j)
                                        <tr>
                                            <td>{{ $j->transaction_date?->translatedFormat('d M Y') }}</td>
                                            <td class="text-truncate" style="max-width: 260px;">{{ $j->description }}</td>
                                            <td><span class="badge bg-label-secondary">{{ \App\Models\Journal::TYPES[$j->journal_type] ?? ucfirst($j->journal_type) }}</span></td>
                                            <td class="text-end"><a href="{{ route('accounting.journal.show', $j->journal_id) }}" class="btn btn-xs btn-label-secondary">Detail</a></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-muted">Belum ada jurnal tercatat.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endisset

        {{-- ================= AKTIVITAS PENGGUNA ================= --}}
        <div class="row g-6">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="ri-history-line me-1 text-secondary"></i> Aktivitas Pengguna Terbaru</h6>
                        @can('logs_view')
                            <a href="{{ route('system.activity-log') }}" class="btn btn-sm btn-outline-secondary">Log Lengkap</a>
                        @endcan
                    </div>
                    <div class="table-responsive text-nowrap">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr><th>Pengguna</th><th>Aksi</th><th>Menu</th><th>Detail</th><th>Waktu</th></tr>
                            </thead>
                            <tbody>
                                @forelse($recentLogs as $log)
                                    <tr>
                                        <td>{{ $log->user?->name ?? 'Sistem' }}</td>
                                        <td><span class="badge bg-label-info">{{ ucfirst($log->action ?? '-') }}</span></td>
                                        <td>{{ $log->menu ?? '-' }}</td>
                                        <td class="text-truncate" style="max-width: 380px;">{{ $log->message ?? '-' }}</td>
                                        <td class="text-muted">{{ $log->created_at?->locale('id')->diffForHumans() }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-muted">Belum ada aktivitas tercatat.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('customjs')
    <script>
        $(function() {
            // Palet selaras template (var warna Thema default)
            var C = { primary: '#666cff', success: '#28c76f', info: '#03a9f4', warning: '#ff9f40', danger: '#ea5455', secondary: '#82868b' };

            function fmtShort(v) {
                return 'Rp ' + Number(v || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
            }

            @isset($trend)
                // ---- Grafik tren 7 hari: bar sewa + line penerimaan ----
                var trendEl = document.getElementById('trendChart');
                if (trendEl && window.ApexCharts) {
                    var labels = {!! $trend->keys()->map(fn ($d) => \Carbon\Carbon::parse($d)->translatedFormat('D, d M'))->toJson() !!};
                    var rentals = {!! $trend->pluck('rentals')->values()->toJson() !!};
                    var revenue = {!! $trend->pluck('revenue')->values()->toJson() !!};

                    new ApexCharts(trendEl, {
                        chart: { type: 'line', height: 300, toolbar: { show: false }, zoom: { enabled: false }, animations: { enabled: false } },
                        series: [
                            { name: 'Sewa dibuat', type: 'column', data: rentals },
                            { name: 'Penerimaan (Rp)', type: 'line', data: revenue }
                        ],
                        colors: [C.info, C.success],
                        stroke: { width: [0, 3], curve: 'smooth' },
                        plotOptions: { bar: { columnWidth: '45%', borderRadius: 4 } },
                        dataLabels: { enabled: false },
                        legend: { offsetY: 4 },
                        xaxis: { categories: labels, axisBorder: { show: false }, axisTicks: { show: false }, labels: { style: { fontSize: '11px' } } },
                        yaxis: [
                            { title: { text: 'Sewa' }, labels: { formatter: function(v) { return Math.round(v); } }, forceNiceScale: true },
                            { opposite: true, title: { text: 'Penerimaan' }, labels: { formatter: fmtShort }, forceNiceScale: true }
                        ],
                        tooltip: { shared: true, intersect: false, y: { formatter: function(v, opts) { return opts.seriesIndex === 1 ? fmtShort(v) : v + ' sewa'; } } }
                    }).render();
                }
            @endisset

            @isset($methodBreakdown)
                // ---- Donut komposisi penerimaan per metode ----
                @if($methodBreakdown->isNotEmpty())
                    var methodEl = document.getElementById('methodChart');
                    if (methodEl && window.ApexCharts) {
                        var methodLabels = {!! $methodBreakdown->pluck('payment_method')->map(fn ($m) => ['cash' => 'Tunai', 'bank_transfer' => 'Transfer Bank', 'credit_card' => 'Kartu Kredit', 'debit_card' => 'Kartu Debit', 'e_wallet' => 'E-Wallet', 'other' => 'Lainnya'][$m] ?? $m)->values()->toJson() !!};
                        var methodData = {!! $methodBreakdown->pluck('total')->map(fn ($v) => (float) $v)->toJson() !!};
                        var methodColors = [C.success, C.primary, C.info, C.warning, C.danger, C.secondary];

                        new ApexCharts(methodEl, {
                            chart: { type: 'donut', height: 220, animations: { enabled: false } },
                            labels: methodLabels,
                            series: methodData,
                            colors: methodColors,
                            legend: { position: 'bottom', fontSize: '12px' },
                            dataLabels: { enabled: false },
                            tooltip: { y: { formatter: fmtShort } },
                            plotOptions: { pie: { donut: { size: '68%', labels: { show: true, name: { offsetY: 18 }, value: { offsetY: -6, formatter: fmtShort }, total: { show: true, label: 'Total', formatter: function(w) { return fmtShort(w.globals.seriesTotals.reduce(function(a, b) { return a + b; }, 0)); } } } } } } }
                        }).render();
                    }
                @endif
            @endisset

            @isset($ledger)
                // ---- Bar neraca ringkas ----
                var ledgerEl = document.getElementById('ledgerChart');
                if (ledgerEl && window.ApexCharts) {
                    new ApexCharts(ledgerEl, {
                        chart: { type: 'bar', height: 220, toolbar: { show: false }, animations: { enabled: false } },
                        series: [{ name: 'Nilai (Rp)', data: {!! $ledger->pluck('value')->map(fn ($v) => (float) $v)->toJson() !!} }],
                        colors: [C.primary, C.warning, C.success],
                        plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '55%', distributed: true } },
                        dataLabels: { enabled: true, formatter: fmtShort, style: { fontSize: '11px' } },
                        xaxis: { categories: {!! $ledger->pluck('label')->values()->toJson() !!}, labels: { formatter: fmtShort } },
                        legend: { show: false },
                        tooltip: { y: { formatter: fmtShort } }
                    }).render();
                }
            @endisset
        });
    </script>
@endsection

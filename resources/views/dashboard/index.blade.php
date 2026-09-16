@extends('layouts.header')

@section('customcss')
    <link rel="stylesheet" href="{{ asset('assets') }}/vendor/libs/leaflet/leaflet.css" />
    <link rel="stylesheet" href="{{ asset('assets') }}/vendor/libs/fullcalendar/fullcalendar.css" />
    <style>
        #fleetMap { height: 340px; border-radius: .5rem; z-index: 0; }
        #rentalCalendar .fc { font-size: .8rem; }
        #rentalCalendar { min-height: 340px; }
    </style>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2 row-gap-4">
            <div class="d-flex flex-column justify-content-center">
                <h4 class="mb-1">{{ $title }}</h4>
                <p class="mb-0">{{ $subtitle }}</p>
            </div>
            <form method="GET" class="d-flex align-items-center gap-2">
                <label class="text-muted small mb-0" for="periodSelect">Periode statistik:</label>
                <select name="period" id="periodSelect" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                    <option value="today" {{ $period === 'today' ? 'selected' : '' }}>Hari Ini</option>
                    <option value="week" {{ $period === 'week' ? 'selected' : '' }}>Minggu Ini</option>
                    <option value="month" {{ $period === 'month' ? 'selected' : '' }}>Bulan Ini</option>
                    <option value="year" {{ $period === 'year' ? 'selected' : '' }}>Tahun Ini</option>
                </select>
            </form>
        </div>

        @include('layouts.alert')

        <div class="row g-6 mb-6">
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div class="avatar">
                                <div class="avatar-initial bg-label-success rounded-3">
                                    <i class="ri-money-dollar-circle-line ri-24px"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-info mt-5">
                            <h5 class="mb-1">Rp {{ number_format($stats['revenueToday'] ?? 0, 0, ',', '.') }}</h5>
                            <p>Pendapatan {{ $periodLabel }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div class="avatar">
                                <div class="avatar-initial bg-label-primary rounded-3">
                                    <i class="ri-drive-line ri-24px"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-info mt-5">
                            <h5 class="mb-1">{{ $stats['ongoingCount'] ?? 0 }}</h5>
                            <p>Sewa Berjalan</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div class="avatar">
                                <div class="avatar-initial bg-label-info rounded-3">
                                    <i class="ri-calendar-todo-line ri-24px"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-info mt-5">
                            <h5 class="mb-1">{{ $stats['reservedCount'] ?? 0 }}</h5>
                            <p>Reservasi Aktif</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div class="avatar">
                                <div class="avatar-initial bg-label-warning rounded-3">
                                    <i class="ri-car-line ri-24px"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-info mt-5">
                            <h5 class="mb-1">{{ $stats['availableVehicles'] ?? 0 }} / {{ $stats['totalVehicles'] ?? 0 }}</h5>
                            <p>Kendaraan Tersedia</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-6 mb-6">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="ri-map-2-line me-1"></i> Peta Armada</h6>
                        <span class="badge bg-label-primary">Live</span>
                    </div>
                    <div class="card-body">
                        <div id="mapLoading" class="text-muted small py-2"><span class="spinner-border spinner-border-sm me-2" role="status"></span> Memuat data armada…</div>
                        <div id="fleetMap"></div>
                        <div id="mapError" class="alert alert-warning py-2 small mt-2 d-none" role="alert">
                            Gagal memuat posisi armada. Coba muat ulang halaman.
                        </div>
                        <div class="d-flex flex-wrap gap-3 mt-2 small">
                            <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#28c76f;"></span> Tersedia</span>
                            <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#666cff;"></span> Disewa</span>
                            <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#03a9f4;"></span> Reservasi</span>
                            <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#ff9f40;"></span> Maintenance</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="ri-calendar-2-line me-1"></i> Kalender Sewa</h6>
                    </div>
                    <div class="card-body">
                        <div id="rentalCalendar"></div>
                        <div id="calendarError" class="alert alert-warning py-2 small mt-2 d-none" role="alert">
                            Gagal memuat jadwal sewa — @can('rental_view')<a href="{{ route('rental.index') }}" class="alert-link">lihat daftar sewa</a>@else hubungi admin untuk akses modul Sewa@endcan.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @can('rental_view')
        <div class="row g-6">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="ri-arrow-return-right-line me-1"></i> Pengembalian Terdekat</h6>
                        <a href="{{ route('rental.index', ['status' => 'ongoing']) }}" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                    </div>
                    <div class="card-body py-2">
                        @forelse($upcomingReturns as $r)
                            <a href="{{ route('rental.show', $r->rental_id) }}"
                                class="d-flex justify-content-between align-items-center py-2 border-bottom text-decoration-none text-body">
                                <div>
                                    <strong>{{ $r->rental_code }}</strong>
                                    <span class="text-muted ms-2">{{ $r->customer?->full_name ?? '-' }}</span>
                                </div>
                                <span class="badge bg-label-danger">{{ $r->rental_end_date?->format('d/m H:i') }}</span>
                            </a>
                        @empty
                            <p class="text-muted py-2 mb-0">Tidak ada pengembalian terdekat.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="ri-takeaway-line me-1"></i> Penjemputan Terdekat</h6>
                        <a href="{{ route('rental.index', ['status' => 'reserved']) }}" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                    </div>
                    <div class="card-body py-2">
                        @forelse($upcomingPickups as $r)
                            <a href="{{ route('rental.show', $r->rental_id) }}"
                                class="d-flex justify-content-between align-items-center py-2 border-bottom text-decoration-none text-body">
                                <div>
                                    <strong>{{ $r->rental_code }}</strong>
                                    <span class="text-muted ms-2">{{ $r->customer?->full_name ?? '-' }}</span>
                                </div>
                                <span class="badge bg-label-info">{{ $r->rental_start_date?->format('d/m H:i') }}</span>
                            </a>
                        @empty
                            <p class="text-muted py-2 mb-0">Tidak ada penjemputan terdekat.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
        @endcan
    </div>
@endsection

@section('customjs')
    <script src="{{ asset('assets') }}/vendor/libs/leaflet/leaflet.js"></script>
    <script src="{{ asset('assets') }}/vendor/libs/fullcalendar/fullcalendar.js"></script>
    <script>
        $(function() {
            const esc = (s) => $('<div>').text(s ?? '').html();

            // Peta Armada interaktif (audit 2.1) + loading/error state (Fase 3)
            var map = L.map('fleetMap').setView([-6.2, 106.9], 10);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);

            var statusColor = { available: '#28c76f', rented: '#666cff', reserved: '#03a9f4', maintenance: '#ff9f40' };

            $.getJSON('{{ route('dashboard.fleet-map') }}')
                .done(function(res) {
                    $('#mapLoading').remove();
                    if (!res || !res.status || !Array.isArray(res.data)) {
                        $('#mapError').removeClass('d-none');
                        return;
                    }
                    var bounds = [];
                    res.data.forEach(function(v) {
                        var marker = L.circleMarker([v.lat, v.lng], {
                            radius: 8,
                            color: statusColor[v.status] || '#82868b',
                            fillColor: statusColor[v.status] || '#82868b',
                            fillOpacity: 0.85,
                            weight: 2
                        }).addTo(map);
                        marker.bindPopup(
                            '<strong>' + esc(v.plate) + '</strong><br>' + esc(v.name) +
                            '<br>Status: ' + esc(v.status) +
                            (v.location ? '<br>Lokasi: ' + esc(v.location) : '') +
                            (v.rental ? '<br>Sewa: ' + esc(v.rental) : '')
                        );
                        bounds.push([v.lat, v.lng]);
                    });
                    if (bounds.length) map.fitBounds(bounds, { padding: [24, 24], maxZoom: 14 });
                })
                .fail(function() {
                    $('#mapLoading').remove();
                    $('#mapError').removeClass('d-none');
                });

            // Kalender Sewa (audit 2.1) + handler gagal (Fase 3)
            var CalendarClass = (window.FullCalendar && window.FullCalendar.Calendar) || window.Calendar;
            var calendarEl = document.getElementById('rentalCalendar');
            if (CalendarClass && calendarEl) {
                var calendar = new CalendarClass(calendarEl, {
                    initialView: 'dayGridMonth',
                    height: 340,
                    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek' },
                    eventSources: [{
                        url: '{{ route('dashboard.calendar') }}',
                        failure: function() {
                            $('#calendarError').removeClass('d-none');
                        }
                    }],
                    eventDidMount: function(info) {
                        info.el.style.cursor = 'pointer';
                    }
                });
                calendar.render();
            }
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
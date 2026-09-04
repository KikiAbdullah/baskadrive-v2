@extends('layouts.header')

@section('customcss')
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex flex-column justify-content-center mb-2">
            <h4 class="mb-1">{{ $title }}</h4>
            <p class="mb-6">{{ $subtitle }}</p>
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
                            <p>Pendapatan Hari Ini</p>
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
    </div>
@endsection

@section('customjs')
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
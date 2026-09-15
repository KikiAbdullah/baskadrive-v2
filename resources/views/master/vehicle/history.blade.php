@extends('layouts.header')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2">
            <div>
                <h4 class="mb-1">{{ $item->license_plate }} - Riwayat Mutasi Lokasi</h4>
                <p class="mb-0 text-muted">{{ $item->model->model_name ?? '' }} ({{ $item->model->brand->brand_name ?? '' }})</p>
            </div>
            <a href="{{ route('master.vehicle.index') }}" class="btn btn-outline-secondary">
                <i class="ri-arrow-left-line me-1"></i> Kembali
            </a>
        </div>

        @include('layouts.alert')

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Riwayat Perpindahan Cabang</h6>
                <span class="badge bg-label-primary">{{ $histories->count() }} mutasi</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Dari</th>
                            <th>Ke</th>
                            <th>Catatan</th>
                            <th>Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($histories as $h)
                            <tr>
                                <td>{{ $h->created_at?->format('d M Y H:i') ?? '-' }}</td>
                                <td>{{ $h->fromLocation->location_name ?? '-' }}</td>
                                <td><span class="badge bg-success">{{ $h->toLocation->location_name ?? '-' }}</span></td>
                                <td>{{ $h->notes ?? '-' }}</td>
                                <td>{{ $h->creator->name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada riwayat mutasi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <h6>Lokasi Saat Ini</h6>
                <p class="mb-0"><strong>{{ $item->location->location_name ?? 'Belum ditentukan' }}</strong>
                    @if($item->location)
                        <br><small class="text-muted">{{ $item->location->address }}, {{ $item->location->city }}</small>
                    @endif
                </p>
            </div>
        </div>
    </div>
@endsection

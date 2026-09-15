@extends('layouts.header')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
            <div>
                <h4 class="mb-1">{{ $item->license_plate }}</h4>
                <p class="mb-0">{{ $item->model->model_name ?? '' }} ({{ $item->model->brand->brand_name ?? '' }}) — {{ $item->location->location_name ?? 'Tanpa Lokasi' }}</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('master.vehicle.history', $item->vehicle_id) }}" class="btn btn-outline-secondary"><i class="ri-history-line me-1"></i> Riwayat Mutasi</a>
                <a href="{{ route('master.vehicle.barcode-pdf', $item->vehicle_id) }}" class="btn btn-primary"><i class="ri-printer-line me-1"></i> Cetak Label 50×30</a>
                <a href="{{ route('master.vehicle.edit', $item->vehicle_id) }}" class="btn btn-outline-primary">Edit</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tr><th width="200">Plat Nomor</th><td>{{ $item->license_plate }}</td></tr>
                            <tr><th>Model</th><td>{{ optional($item->model)->model_name }} ({{ optional(optional($item->model)->brand)->brand_name }})</td></tr>
                            <tr><th>Lokasi</th><td>{{ $item->location->location_name ?? '-' }}</td></tr>
                            <tr><th>VIN</th><td>{{ $item->vin ?? '-' }}</td></tr>
                            <tr><th>Warna</th><td>{{ $item->color ?? '-' }}</td></tr>
                            <tr><th>Tahun</th><td>{{ $item->year ?? '-' }}</td></tr>
                            <tr><th>Kilometer</th><td>{{ $item->mileage ? number_format($item->mileage, 0, ',', '.').' KM' : '-' }}</td></tr>
                            <tr><th>Status</th><td>{!! match ($item->status) {
                                'available' => '<span class="badge bg-success">Available</span>',
                                'rented' => '<span class="badge bg-primary">Rented</span>',
                                'maintenance' => '<span class="badge bg-warning">Maintenance</span>',
                                'reserved' => '<span class="badge bg-info">Reserved</span>',
                                default => '<span class="badge bg-secondary">Retired</span>',
                            } !!}</td></tr>
                            <tr><th>Mesin</th><td>{{ $item->engine_number ?? '-' }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Barcode Plat Nomor</h6>
                        <small class="text-muted">Code128 — 50×30mm thermal</small>
                    </div>
                    <div class="card-body text-center">
                        <img src="data:image/png;base64,{{ $barcode }}" alt="Barcode {{ $item->license_plate }}" style="max-width:100%;height:80px;object-fit:contain;">
                        <p class="mt-2 mb-0 fw-bold" style="letter-spacing:2px;">{{ $item->license_plate }}</p>
                        <p class="text-muted small mb-0">{{ $item->model->model_name ?? '' }} • {{ $item->color ?? '' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
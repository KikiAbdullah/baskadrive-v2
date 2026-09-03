@extends('layouts.header')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">{{ $item->license_plate }}</h4>
                <p class="mb-0">{{ $item->subtitle ?? 'Detail Vehicle' }}</p>
            </div>
            <a href="{{ route('master.vehicle.edit', $item->vehicle_id) }}" class="btn btn-primary">Edit</a>
        </div>

        <div class="card">
            <div class="card-body">
                <table class="table table-bordered">
                    <tr><th width="200">Plat Nomor</th><td>{{ $item->license_plate }}</td></tr>
                    <tr><th>Model</th><td>{{ optional($item->model)->model_name }} ({{ optional(optional($item->model)->brand)->brand_name }})</td></tr>
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
                <div class="text-center mt-4">
                    <p class="fw-semibold">Vehicle ID: {{ $item->vehicle_id }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
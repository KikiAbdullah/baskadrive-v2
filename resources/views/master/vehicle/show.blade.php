@extends('layouts.header')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2">
            <div class="d-flex flex-column justify-content-center">
                <h4 class="mb-1">{{ $item->license_plate }}</h4>
                <p class="mb-6">{{ $subtitle }}</p>
            </div>
            <a href="{{ route($url['edit'], $item->vehicle_id) }}" class="btn btn-primary">Edit</a>
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
                    <tr><th>Foto</th><td>@if ($item->photo_url) <img src="{{ $item->photo_url }}" height="60" /> @else - @endif</td></tr>
                    <tr><th>Catatan</th><td>{{ $item->notes ?? '-' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
@endsection
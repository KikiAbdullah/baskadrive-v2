@extends('layouts.header')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2">
            <div class="d-flex flex-column justify-content-center">
                <h4 class="mb-1">{{ $item->full_name }}</h4>
                <p class="mb-6">Riwayat Sewa Sopir</p>
            </div>
            <a href="{{ route('master.driver.edit', $item->driver_id) }}" class="btn btn-primary">Edit</a>
        </div>

        <div class="card">
            <div class="card-datatable table-responsive">
                <table class="table table-xxs">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Vehicle</th>
                            <th>Tanggal Mulai</th>
                            <th>Tanggal Selesai</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($item->rentals as $rental)
                            <tr>
                                <td>{{ $rental->rental_id }}</td>
                                <td>{{ optional($rental->vehicle)->license_plate ?? '-' }}</td>
                                <td>{{ optional($rental->start_date)->format('d/m/Y') }}</td>
                                <td>{{ optional($rental->end_date)->format('d/m/Y') }}</td>
                                <td>{{ $rental->status }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">Belum ada riwayat sewa.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
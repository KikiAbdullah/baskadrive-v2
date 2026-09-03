@extends('layouts.header')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2">
            <div class="d-flex flex-column justify-content-center">
                <h4 class="mb-1">{{ $item->full_name }}</h4>
                <p class="mb-6">{{ $subtitle }}</p>
            </div>
            <a href="{{ route($url['edit'], $item->customer_id) }}" class="btn btn-primary">Edit</a>
        </div>

        <div class="card">
            <div class="card-body">
                <table class="table table-bordered">
                    <tr><th width="200">Tipe</th><td>{{ $item->customer_type === 'individual' ? 'Individu' : 'Korporasi' }}</td></tr>
                    <tr><th>Nama</th><td>{{ $item->full_name }}</td></tr>
                    <tr><th>Perusahaan</th><td>{{ $item->company_name ?? '-' }}</td></tr>
                    <tr><th>Email</th><td>{{ $item->email ?? '-' }}</td></tr>
                    <tr><th>Telepon</th><td>{{ $item->phone ?? '-' }}</td></tr>
                    <tr><th>Alamat</th><td>{{ $item->address ?? '-' }}</td></tr>
                    <tr><th>Kota</th><td>{{ $item->city ?? '-' }}</td></tr>
                    <tr><th>Provinsi</th><td>{{ $item->province ?? '-' }}</td></tr>
                    <tr><th>Kode Pos</th><td>{{ $item->postal_code ?? '-' }}</td></tr>
                    <tr><th>No. SIM</th><td>{{ $item->driver_license_number ?? '-' }}</td></tr>
                    <tr><th>Status Verifikasi</th><td>{!! $item->is_verified ? '<span class="badge bg-success">Verified</span>' : '<span class="badge bg-warning">Pending</span>' !!}</td></tr>
                    <tr><th>Catatan</th><td>{{ $item->notes ?? '-' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
@endsection
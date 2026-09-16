@extends('layouts.header')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2 row-gap-4">
            <div class="d-flex flex-column justify-content-center">
                <h4 class="mb-1">{{ $title }}</h4>
                <p class="mb-6">{{ $subtitle }} &mdash; {{ \Carbon\Carbon::parse($start)->format('d/m/Y') }} s.d {{ \Carbon\Carbon::parse($end)->format('d/m/Y') }}</p>
            </div>
            <form method="GET" class="d-flex align-items-center gap-2">
                <input type="text" name="start_date" class="form-control form-control-sm flatpickr-date" value="{{ $start }}" autocomplete="off" style="width:150px">
                <input type="text" name="end_date" class="form-control form-control-sm flatpickr-date" value="{{ $end }}" autocomplete="off" style="width:150px">
                <button type="submit" class="btn btn-sm btn-primary"><i class="ri-filter-3-line me-1"></i>Tampilkan</button>
            </form>
        </div>

        @include('layouts.alert')

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <p class="mb-1 text-muted">Arus Kas Operasional</p>
                        <h5 class="mb-0 {{ $report['operating'] >= 0 ? 'text-success' : 'text-danger' }}">Rp {{ number_format($report['operating'], 0, ',', '.') }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <p class="mb-1 text-muted">Arus Kas Investasi</p>
                        <h5 class="mb-0 {{ $report['investing'] >= 0 ? 'text-success' : 'text-danger' }}">Rp {{ number_format($report['investing'], 0, ',', '.') }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <p class="mb-1 text-muted">Arus Kas Pendanaan</p>
                        <h5 class="mb-0 {{ $report['financing'] >= 0 ? 'text-success' : 'text-danger' }}">Rp {{ number_format($report['financing'], 0, ',', '.') }}</h5>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Rincian Transaksi Kas</h6>
                <span class="badge bg-label-{{ $report['net_change'] >= 0 ? 'success' : 'danger' }}">Netto: Rp {{ number_format($report['net_change'], 0, ',', '.') }}</span>
            </div>
            <div class="card-datatable table-responsive">
                <table class="table table-xxs">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Referensi</th>
                            <th>Keterangan</th>
                            <th>Kategori</th>
                            <th class="text-end">Arus Kas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report['details'] as $d)
                            <tr>
                                <td>{{ $d['date']?->format('d/m/Y') ?? '-' }}</td>
                                <td>{{ $d['reference'] ?? '-' }}</td>
                                <td>{{ $d['description'] ?? '-' }}</td>
                                <td><span class="badge bg-label-info">{{ ucfirst($d['category']) }}</span></td>
                                <td class="text-end {{ $d['amount'] >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $d['amount'] >= 0 ? '+' : '' }}Rp {{ number_format($d['amount'], 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">Tidak ada mutasi kas/bank pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('notification')
    @include('layouts.notification')
@endsection

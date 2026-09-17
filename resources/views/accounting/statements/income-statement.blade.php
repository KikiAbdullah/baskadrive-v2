@extends('layouts.header')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2 row-gap-4">
            <div class="d-flex flex-column justify-content-center">
                <h4 class="mb-1">{{ $title }}</h4>
                <p class="mb-6">{{ $subtitle }} &mdash; {{ \Carbon\Carbon::parse($start)->format('d/m/Y') }} s.d {{ \Carbon\Carbon::parse($end)->format('d/m/Y') }}</p>
            </div>
            <form method="GET" class="d-flex align-items-center gap-2">
                <input type="text" class="form-control form-control-sm" id="flatpickr-range" data-range-start="#rangeStartDate" data-range-end="#rangeEndDate" value="{{ $start }} to {{ $end }}" autocomplete="off" style="width:250px" aria-label="Rentang tanggal laporan">
                <input type="hidden" name="start_date" id="rangeStartDate" value="{{ $start }}">
                <input type="hidden" name="end_date" id="rangeEndDate" value="{{ $end }}">
                <button type="submit" class="btn btn-sm btn-primary"><i class="ri-filter-3-line me-1"></i>Tampilkan</button>
            </form>
        </div>

        @include('layouts.alert')

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="mb-0 text-success"><i class="ri-arrow-up-circle-line me-1"></i> Pendapatan</h6>
                    </div>
                    <div class="card-datatable table-responsive">
                        <table class="table table-xxs">
                            <thead><tr><th>Kode</th><th>Akun</th><th class="text-end">Jumlah</th></tr></thead>
                            <tbody>
                                @forelse($report['incomes'] as $row)
                                    <tr>
                                        <td>{{ $row->account_code }}</td>
                                        <td>{{ $row->account_name }}</td>
                                        <td class="text-end">Rp {{ number_format($row->signed_balance, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-muted text-center">Tidak ada pendapatan pada periode ini.</td></tr>
                                @endforelse
                                <tr class="table-success fw-bold">
                                    <td colspan="2">Total Pendapatan</td>
                                    <td class="text-end">Rp {{ number_format($report['total_income'], 0, ',', '.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="mb-0 text-danger"><i class="ri-arrow-down-circle-line me-1"></i> Beban</h6>
                    </div>
                    <div class="card-datatable table-responsive">
                        <table class="table table-xxs">
                            <thead><tr><th>Kode</th><th>Akun</th><th class="text-end">Jumlah</th></tr></thead>
                            <tbody>
                                @forelse($report['expenses'] as $row)
                                    <tr>
                                        <td>{{ $row->account_code }}</td>
                                        <td>{{ $row->account_name }}</td>
                                        <td class="text-end">Rp {{ number_format($row->signed_balance, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-muted text-center">Tidak ada beban pada periode ini.</td></tr>
                                @endforelse
                                <tr class="table-danger fw-bold">
                                    <td colspan="2">Total Beban</td>
                                    <td class="text-end">Rp {{ number_format($report['total_expense'], 0, ',', '.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-body d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Laba (Rugi) Bersih</h5>
                <h4 class="mb-0 {{ $report['net_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                    Rp {{ number_format($report['net_profit'], 0, ',', '.') }}
                </h4>
            </div>
        </div>
    </div>
@endsection

@section('notification')
    @include('layouts.notification')
@endsection

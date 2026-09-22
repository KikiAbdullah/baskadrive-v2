@extends('layouts.header')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2 row-gap-4">
            <div class="d-flex flex-column justify-content-center">
                <h4 class="mb-1">{{ $title }}</h4>
                <p class="mb-6">{{ $subtitle }} &mdash; per {{ \Carbon\Carbon::parse($end)->locale('id')->translatedFormat('d M Y') }}</p>
            </div>
            <form method="GET" class="d-flex align-items-center gap-2">
                <input type="text" name="end_date" class="form-control form-control-sm flatpickr-date" value="{{ $end }}" autocomplete="off" style="width:150px">
                <button type="submit" class="btn btn-sm btn-primary"><i class="ri-filter-3-line me-1"></i>Tampilkan</button>
            </form>
        </div>

        @include('layouts.alert')

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h6 class="mb-0 text-primary"><i class="ri-bank-line me-1"></i> ASET</h6></div>
                    <div class="card-datatable table-responsive">
                        <table class="table table-xxs">
                            <thead><tr><th>Kode</th><th>Akun</th><th class="text-end">Saldo</th></tr></thead>
                            <tbody>
                                @foreach($report['assets'] as $row)
                                    <tr>
                                        <td>{{ $row->account_code }}</td>
                                        <td>{{ $row->account_name }}</td>
                                        {{-- AKN-02: dua desimal agar sen ikut tampil --}}
                                        <td class="text-end">{{ \App\Support\AppSettings::money($row->signed_balance) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="table-primary fw-bold">
                                    <td colspan="2">TOTAL ASET</td>
                                    <td class="text-end">{{ \App\Support\AppSettings::money($report['total_assets']) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header"><h6 class="mb-0 text-warning"><i class="ri-file-list-3-line me-1"></i> KEWAJIBAN</h6></div>
                    <div class="card-datatable table-responsive">
                        <table class="table table-xxs">
                            <thead><tr><th>Kode</th><th>Akun</th><th class="text-end">Saldo</th></tr></thead>
                            <tbody>
                                @foreach($report['liabilities'] as $row)
                                    <tr>
                                        <td>{{ $row->account_code }}</td>
                                        <td>{{ $row->account_name }}</td>
                                        {{-- AKN-02: dua desimal agar sen ikut tampil --}}
                                        <td class="text-end">{{ \App\Support\AppSettings::money($row->signed_balance) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="table-warning fw-bold">
                                    <td colspan="2">TOTAL KEWAJIBAN</td>
                                    <td class="text-end">{{ \App\Support\AppSettings::money($report['total_liabilities']) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header"><h6 class="mb-0 text-success"><i class="ri-vip-crown-line me-1"></i> EKUITAS</h6></div>
                    <div class="card-datatable table-responsive">
                        <table class="table table-xxs">
                            <thead><tr><th>Kode</th><th>Akun</th><th class="text-end">Saldo</th></tr></thead>
                            <tbody>
                                @foreach($report['equity'] as $row)
                                    <tr>
                                        <td>{{ $row->account_code }}</td>
                                        <td>{{ $row->account_name }}</td>
                                        {{-- AKN-02: dua desimal agar sen ikut tampil --}}
                                        <td class="text-end">{{ \App\Support\AppSettings::money($row->signed_balance) }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td>&mdash;</td>
                                    <td>Laba Tahun Berjalan</td>
                                    <td class="text-end">{{ \App\Support\AppSettings::money($report['current_period_profit']) }}</td>
                                </tr>
                                {{-- AKN-03: laba tahun-tahun sebelumnya yang belum ditutup ke ekuitas --}}
                                <tr>
                                    <td>&mdash;</td>
                                    <td>Laba Tahun Lalu (belum ditutup)</td>
                                    <td class="text-end">{{ \App\Support\AppSettings::money($report['prior_years_profit']) }}</td>
                                </tr>
                                <tr class="table-success fw-bold">
                                    <td colspan="2">TOTAL EKUITAS</td>
                                    <td class="text-end">{{ \App\Support\AppSettings::money($report['total_equity_with_profit']) }}</td>
                                </tr>
                                <tr class="fw-bold">
                                    <td colspan="2">TOTAL KEWAJIBAN + EKUITAS</td>
                                    <td class="text-end">{{ \App\Support\AppSettings::money($report['total_liabilities'] + $report['total_equity_with_profit']) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('notification')
    @include('layouts.notification')
@endsection

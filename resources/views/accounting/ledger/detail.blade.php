@extends('layouts.header')

@section('customcss')
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2 row-gap-4">
            <div class="d-flex flex-column justify-content-center">
                <h4 class="mb-1">{{ $account->account_code }} - {{ $account->account_name }}</h4>
                <p class="mb-6">Buku Besar &mdash; {{ $account->account_type }}</p>
            </div>
            <div class="d-flex align-content-center flex-wrap gap-2">
                <a href="{{ route('accounting.ledger.index') }}" class="btn btn-outline-secondary">
                    <i class="ri-arrow-left-line me-1"></i> Kembali
                </a>
            </div>
        </div>

        @include('layouts.alert')

        <div class="card">
            <div class="card-datatable table-responsive">
                <table class="table table-xxs">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>No. Referensi</th>
                            <th>Keterangan</th>
                            <th class="text-end">Debit</th>
                            <th class="text-end">Kredit</th>
                            <th class="text-end">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $e)
                            <tr>
                                <td>{{ $e->journal?->transaction_date?->format('d/m/Y') ?? '-' }}</td>
                                <td>{{ $e->journal?->reference_number ?? '-' }}</td>
                                <td>{{ $e->description ?? '-' }}</td>
                                <td class="text-end">Rp {{ number_format($e->debit ?? 0, 0, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($e->credit ?? 0, 0, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($e->balance ?? 0, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">Belum ada transaksi pada akun ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
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
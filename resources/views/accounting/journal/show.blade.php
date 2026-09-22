@extends('layouts.header')

@section('customcss')
    <style>
        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.5rem 0;
            border-bottom: 1px dashed #e5e7eb;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #6b7280;
            font-weight: 500;
            white-space: nowrap;
        }

        .info-value {
            text-align: right;
            font-weight: 600;
        }
    </style>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2 row-gap-4">
            <div class="d-flex flex-column justify-content-center">
                <h4 class="mb-1">{{ $item->reference_number ?? 'Jurnal' }}</h4>
                <p class="mb-6">{{ $subtitle }}</p>
            </div>
            <div class="d-flex align-content-center flex-wrap gap-4">
                <a href="{{ route('accounting.journal.index') }}" class="action-link-icon-text">
                    <i class="ri-arrow-left-line"></i>
                    <span class="fw-semibold text-uppercase">Kembali</span>
                </a>
                <a href="{{ route('accounting.journal.export', $item->journal_id) }}" class="action-link-icon-text">
                    <i class="ri-download-line"></i>
                    <span class="fw-semibold text-uppercase">Export CSV</span>
                </a>
            </div>
        </div>

        @include('layouts.alert')

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="ri-information-line me-1"></i> Informasi</h6>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <span class="info-label">No. Referensi</span>
                            <span class="info-value">{{ $item->reference_number ?? '-' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Tanggal</span>
                            <span class="info-value">{{ $item->transaction_date?->locale('id')->translatedFormat('d F Y') ?? '-' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Tipe</span>
                            <span class="info-value">{{ \App\Models\Journal::TYPES[$item->journal_type] ?? ($item->journal_type ?? '-') }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Dibuat Oleh</span>
                            <span class="info-value">{{ $item->creator?->full_name ?? '-' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Keterangan</span>
                            <span class="info-value text-wrap">{{ $item->description ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="ri-list-check-2 me-1"></i> Entri Jurnal</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Kode</th>
                                        <th>Akun</th>
                                        <th>Keterangan</th>
                                        <th class="text-end">Debit</th>
                                        <th class="text-end">Kredit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($item->details as $d)
                                        <tr>
                                            <td>{{ $d->account?->account_code ?? '-' }}</td>
                                            <td>{{ $d->account?->account_name ?? '-' }}</td>
                                            <td>{{ $d->description ?? '-' }}</td>
                                            {{-- AKN-02: dua desimal agar sen ikut tampil --}}
                                            <td class="text-end">{{ \App\Support\AppSettings::money($d->debit ?? 0) }}</td>
                                            <td class="text-end">{{ \App\Support\AppSettings::money($d->credit ?? 0) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="table-active">
                                        <th colspan="3" class="text-end">Total</th>
                                        <th class="text-end">{{ \App\Support\AppSettings::money($item->details->sum('debit') ?? 0) }}</th>
                                        <th class="text-end">{{ \App\Support\AppSettings::money($item->details->sum('credit') ?? 0) }}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
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
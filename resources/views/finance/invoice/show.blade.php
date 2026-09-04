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
                <div class="d-flex align-items-center gap-2">
                    <h4 class="mb-1">{{ $item->invoice_number ?? 'Invoice' }}</h4>
                    @include('components.invoice-status-badge', ['status' => $item->status])
                </div>
                <p class="mb-6">{{ $subtitle }}</p>
            </div>
            <div class="d-flex align-content-center flex-wrap gap-2">
                <a href="{{ route('finance.invoice.index') }}" class="btn btn-outline-secondary">
                    <i class="ri-arrow-left-line me-1"></i> Kembali
                </a>
                <a href="{{ route('finance.invoice.print', $item->invoice_id) }}" target="_blank"
                    class="btn btn-primary">
                    <i class="ri-printer-line me-1"></i> Cetak
                </a>
            </div>
        </div>

        @include('layouts.alert')

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="ri-file-text-line me-1"></i> Rincian Tagihan</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Harga</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($item->rental?->details ?? [] as $d)
                                        <tr>
                                            <td>{{ $d->item_name }}</td>
                                            <td class="text-end">{{ $d->quantity }}</td>
                                            <td class="text-end">Rp {{ number_format($d->unit_price ?? 0, 0, ',', '.') }}</td>
                                            <td class="text-end">Rp {{ number_format($d->total_price ?? 0, 0, ',', '.') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">Tidak ada rincian item.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3" class="text-end">Subtotal</th>
                                        <th class="text-end">Rp {{ number_format($item->sub_total ?? 0, 0, ',', '.') }}</th>
                                    </tr>
                                    <tr>
                                        <th colspan="3" class="text-end">Pajak</th>
                                        <th class="text-end">Rp {{ number_format($item->tax ?? 0, 0, ',', '.') }}</th>
                                    </tr>
                                    <tr>
                                        <th colspan="3" class="text-end">Diskon</th>
                                        <th class="text-end">(Rp {{ number_format($item->discount ?? 0, 0, ',', '.') }})</th>
                                    </tr>
                                    <tr class="table-active">
                                        <th colspan="3" class="text-end">Total</th>
                                        <th class="text-end">Rp {{ number_format($item->total_amount ?? 0, 0, ',', '.') }}</th>
                                    </tr>
                                    <tr>
                                        <th colspan="3" class="text-end">Terbayar</th>
                                        <th class="text-end">Rp {{ number_format($item->paid_amount ?? 0, 0, ',', '.') }}</th>
                                    </tr>
                                    <tr class="text-danger">
                                        <th colspan="3" class="text-end">Sisa</th>
                                        <th class="text-end">Rp {{ number_format(($item->total_amount ?? 0) - ($item->paid_amount ?? 0), 0, ',', '.') }}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="ri-information-line me-1"></i> Informasi</h6>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <span class="info-label">Kode Sewa</span>
                            <span class="info-value">{{ $item->rental?->rental_code ?? '-' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Pelanggan</span>
                            <span class="info-value">{{ $item->rental?->customer?->full_name ?? '-' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Kendaraan</span>
                            <span class="info-value">{{ $item->rental?->vehicle?->license_plate ?? '-' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Tgl Terbit</span>
                            <span class="info-value">{{ $item->issue_date?->format('d/m/Y') ?? '-' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Tgl Jatuh Tempo</span>
                            <span class="info-value">{{ $item->due_date?->format('d/m/Y') ?? '-' }}</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="ri-bank-card-line me-1"></i> Riwayat Pembayaran</h6>
                    </div>
                    <div class="card-body">
                        @forelse($item->payments as $p)
                            <div class="info-row">
                                <span class="info-label">
                                    {{ $p->payment_date?->format('d/m/Y') }} ({{ ucfirst($p->payment_method) }})
                                </span>
                                <span class="info-value">Rp {{ number_format($p->amount ?? 0, 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <p class="text-muted mb-0">Belum ada pembayaran.</p>
                        @endforelse
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
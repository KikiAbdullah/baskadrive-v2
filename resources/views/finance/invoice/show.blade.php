@extends('layouts.header')

@section('customcss')
    <style>
        .inv-hero {
            background: linear-gradient(135deg, #575df0, #666cff 55%, #8a5cf6);
            color: #fff;
            border: 0;
            overflow: hidden;
        }
        .inv-hero::after {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            right: -80px;
            top: -140px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .1);
        }
        .inv-avatar {
            display: grid;
            place-items: center;
            width: 68px;
            height: 68px;
            flex: none;
            border-radius: 18px;
            background: rgba(255, 255, 255, .2);
            color: #fff;
            font-size: 1.9rem;
        }
        .inv-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: .5rem 0;
            border-bottom: 1px solid #f0f1f7;
            font-size: .875rem;
        }
        .inv-row:last-child { border-bottom: 0; }
        .inv-row .lbl { color: #7a8096; font-weight: 600; }
        .inv-row .val { font-weight: 700; color: #1d1d33; text-align: right; }
    </style>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex justify-content-end mb-4">
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('finance.index') }}" class="btn btn-label-secondary">
                    <i class="ri-arrow-go-back-line me-1"></i> Kembali
                </a>
                <a href="{{ route('finance.invoice.print', $item->invoice_id) }}" class="btn btn-primary" target="_blank">
                    <i class="ri-printer-line me-1"></i> Cetak
                </a>
            </div>
        </div>

        <div class="card inv-hero shadow-sm mb-4">
            <div class="card-body position-relative p-4">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="inv-avatar"><i class="ri-file-list-3-line"></i></div>
                    <div class="flex-grow-1">
                        <h4 class="mb-1 text-white">{{ $item->invoice_number ?: 'Invoice' }}</h4>
                        <div class="d-flex flex-wrap gap-2">
                            @include('components.invoice-status-badge', ['status' => $item->status])
                            <span class="badge bg-label-light">{{ $item->rental?->rental_code ?: '-' }}</span>
                        </div>
                    </div>
                    <div class="d-none d-md-block text-end">
                        <small class="d-block" style="color:rgba(255,255,255,.75)">Jatuh tempo</small>
                        <strong>{{ $item->due_date?->format('d M Y') ?? '-' }}</strong>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-4 mt-4 pt-3" style="border-top:1px solid rgba(255,255,255,.2)">
                    <div>
                        <small class="d-block" style="color:rgba(255,255,255,.75)">Total tagihan</small>
                        <strong>{{ \App\Support\AppSettings::money($item->total_amount ?? 0) }}</strong>
                    </div>
                    <div>
                        <small class="d-block" style="color:rgba(255,255,255,.75)">Terbayar</small>
                        <strong>{{ \App\Support\AppSettings::money($item->paid_amount ?? 0) }}</strong>
                    </div>
                    <div>
                        <small class="d-block" style="color:rgba(255,255,255,.75)">Sisa</small>
                        <strong>{{ \App\Support\AppSettings::money(($item->total_amount ?? 0) - ($item->paid_amount ?? 0)) }}</strong>
                    </div>
                    <div>
                        <small class="d-block" style="color:rgba(255,255,255,.75)">Pelanggan</small>
                        <strong>{{ $item->rental?->customer?->full_name ?: '-' }}</strong>
                    </div>
                </div>
            </div>
        </div>

        @include('layouts.alert')

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card h-100">
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
                                    {{-- FIN-13: snapshot line item yang sama dengan PDF --}}
                                    @php $lineItems = \App\Support\InvoiceLineItems::for($item); @endphp
                                    @forelse ($lineItems as $line)
                                        <tr>
                                            <td>{{ $line['name'] }}<br><small class="text-muted">{{ $line['sub'] }}</small></td>
                                            <td class="text-end">{{ $line['qty'] }}</td>
                                            <td class="text-end">{{ \App\Support\AppSettings::money($line['unit']) }}</td>
                                            <td class="text-end">{{ \App\Support\AppSettings::money($line['total']) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted py-4">Tidak ada rincian item.</td></tr>
                                    @endforelse
                                </tbody>
                                <tfoot>
                                    <tr><th colspan="3" class="text-end">Subtotal</th><th class="text-end">{{ \App\Support\AppSettings::money($item->sub_total ?? 0) }}</th></tr>
                                    <tr><th colspan="3" class="text-end">Pajak</th><th class="text-end">{{ \App\Support\AppSettings::money($item->tax ?? 0) }}</th></tr>
                                    <tr><th colspan="3" class="text-end">Diskon</th><th class="text-end">({{ \App\Support\AppSettings::money($item->discount ?? 0) }})</th></tr>
                                    <tr class="table-active"><th colspan="3" class="text-end">Total</th><th class="text-end">{{ \App\Support\AppSettings::money($item->total_amount ?? 0) }}</th></tr>
                                    <tr><th colspan="3" class="text-end">Terbayar</th><th class="text-end">{{ \App\Support\AppSettings::money($item->paid_amount ?? 0) }}</th></tr>
                                    <tr class="text-danger"><th colspan="3" class="text-end">Sisa</th><th class="text-end">{{ \App\Support\AppSettings::money(($item->total_amount ?? 0) - ($item->paid_amount ?? 0)) }}</th></tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="ri-information-line me-1"></i> Informasi</h6>
                    </div>
                    <div class="card-body">
                        <div class="inv-row"><span class="lbl">Kode sewa</span><span class="val">{{ $item->rental?->rental_code ?? '-' }}</span></div>
                        <div class="inv-row"><span class="lbl">Pelanggan</span><span class="val">{{ $item->rental?->customer?->full_name ?? '-' }}</span></div>
                        <div class="inv-row"><span class="lbl">Kendaraan</span><span class="val">{{ $item->rental?->vehicle?->license_plate ?? '-' }}</span></div>
                        <div class="inv-row"><span class="lbl">Tgl terbit</span><span class="val">{{ $item->issue_date?->format('d/m/Y') ?? '-' }}</span></div>
                        <div class="inv-row"><span class="lbl">Jatuh tempo</span><span class="val">{{ $item->due_date?->format('d/m/Y') ?? '-' }}</span></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="ri-bank-card-line me-1"></i> Riwayat Pembayaran</h6>
                    </div>
                    <div class="card-body">
                        @forelse ($item->payments as $p)
                            <div class="inv-row">
                                <span class="lbl">{{ $p->payment_date?->format('d/m/Y') }} ({{ ucfirst($p->payment_method) }})</span>
                                <span class="val">{{ \App\Support\AppSettings::money($p->amount ?? 0) }}</span>
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

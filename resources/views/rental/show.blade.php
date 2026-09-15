@extends('layouts.header')

@section('customcss')
<style>
    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 0.4rem 0;
        border-bottom: 1px dashed #e9ecef;
    }

    .info-row:last-child {
        border-bottom: none;
    }

    .info-label {
        color: #697a8d;
        font-size: 0.85rem;
    }

    .info-value {
        font-weight: 600;
        text-align: right;
    }
</style>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2 row-gap-4">
            <div class="d-flex flex-column justify-content-center">
                <div class="d-flex align-items-center gap-2">
                    <h4 class="mb-1">{{ $rental->rental_code }}</h4>
                    @include('components.rental-status-badge', ['status' => $rental->status])
                </div>
                <p class="mb-6">{{ $subtitle }}</p>
            </div>
            <div class="d-flex align-content-center flex-wrap gap-4">
                <a href="{{ route('rental.index') }}" class="action-link-icon-text">
                    <i class="ri-arrow-left-line"></i>
                    <span class="fw-semibold text-uppercase">Kembali</span>
                </a>
                <a href="{{ route('rental.print', $rental->rental_id) }}" class="action-link-icon-text">
                    <i class="ri-printer-line"></i>
                    <span class="fw-semibold text-uppercase">Cetak Kontrak</span>
                </a>
                @if(in_array($rental->status, ['reserved','ongoing']))
                    <a href="{{ route('rental.detail.handover.form', [$rental->rental_id, 'out']) }}" class="action-link-icon-text">
                        <i class="ri-car-line"></i>
                        <span class="fw-semibold text-uppercase">Inspeksi Awal</span>
                    </a>
                @endif
                @if($rental->status === 'ongoing')
                    <a href="{{ route('rental.detail.handover.form', [$rental->rental_id, 'in']) }}" class="action-link-icon-text">
                        <i class="ri-checkbox-line"></i>
                        <span class="fw-semibold text-uppercase">Inspeksi Akhir</span>
                    </a>
                @endif
                <a href="{{ route('rental.edit', $rental->rental_id) }}" class="action-link-icon-text">
                    <i class="ri-edit-line"></i>
                    <span class="fw-semibold text-uppercase">Edit</span>
                </a>
            </div>
        </div>

        @include('layouts.alert')

        <div class="row">
            <div class="col-md-8">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="ri-user-star-line me-1"></i> Pelanggan</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-1"><strong>{{ $rental->customer?->full_name ?? '-' }}</strong></p>
                                <p class="mb-1 text-muted small"><i class="ri-phone-line"></i> {{ $rental->customer?->phone ?? '-' }}</p>
                                <p class="mb-1 text-muted small"><i class="ri-mail-line"></i> {{ $rental->customer?->email ?? '-' }}</p>
                                <span class="badge bg-label-{{ $rental->customer?->customer_type == 'corporate' ? 'primary' : 'info' }}">
                                    {{ $rental->customer?->customer_type == 'corporate' ? 'Perusahaan' : 'Individu' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="ri-car-line me-1"></i> Kendaraan</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-1"><strong>{{ $rental->vehicle?->license_plate ?? '-' }}</strong></p>
                                <p class="mb-1 text-muted small">
                                    {{ $rental->vehicle?->model?->brand?->brand_name ?? '' }}
                                    {{ $rental->vehicle?->model?->model_name ?? '' }}
                                    ({{ $rental->vehicle?->year ?? '' }})
                                </p>
                                <p class="mb-1 text-muted small">
                                    {{ $rental->vehicle?->color ?? '-' }} |
                                    {{ $rental->vehicle?->model?->transmission ?? '-' }} |
                                    {{ $rental->vehicle?->model?->seat_capacity ?? '-' }} kursi
                                </p>
                                @if($rental->is_with_driver)
                                    <span class="badge bg-label-info">Dengan Sopir: {{ $rental->driver?->full_name ?? '-' }}</span>
                                @else
                                    <span class="badge bg-label-secondary">Tanpa Sopir</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="ri-calendar-line me-1"></i> Periode & Lokasi</h6>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <span class="info-label">Mulai Sewa</span>
                            <span class="info-value">{{ $rental->rental_start_date?->format('d F Y H:i') ?? '-' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Selesai Sewa</span>
                            <span class="info-value">{{ $rental->rental_end_date?->format('d F Y H:i') ?? '-' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Durasi</span>
                            <span class="info-value">{{ $rental->rental_days ?? 0 }} hari</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Lokasi Penjemputan</span>
                            <span class="info-value">{{ $rental->pickupLocation?->location_name ?? '-' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Lokasi Pengembalian</span>
                            <span class="info-value">{{ $rental->returnLocation?->location_name ?? '-' }}</span>
                        </div>
                        @if($rental->promo)
                            <div class="info-row">
                                <span class="info-label">Promo</span>
                                <span class="info-value">{{ $rental->promo->promo_code }}</span>
                            </div>
                        @endif
                        @if($rental->notes)
                            <div class="info-row">
                                <span class="info-label">Catatan</span>
                                <span class="info-value text-wrap">{{ $rental->notes }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                @if($rental->details && $rental->details->count())
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="ri-list-check-2 me-1"></i> Add-on / Biaya Tambahan</h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Qty</th>
                                        <th class="text-end">Harga Satuan</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rental->details as $detail)
                                        <tr>
                                            <td>{{ $detail->item_name }}</td>
                                            <td>{{ $detail->quantity }}</td>
                                            <td class="text-end">Rp {{ number_format($detail->unit_price, 0, ',', '.') }}</td>
                                            <td class="text-end">Rp {{ number_format($detail->total_price, 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="ri-money-dollar-circle-line me-1"></i> Rincian Harga</h6>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <span class="info-label">Tarif Dasar/Hari</span>
                            <span class="info-value">Rp {{ number_format($rental->base_rate_per_day ?? 0, 0, ',', '.') }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Durasi</span>
                            <span class="info-value">{{ $rental->rental_days ?? 0 }} hari</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Total Tarif Dasar</span>
                            <span class="info-value">Rp {{ number_format($rental->total_base_price ?? 0, 0, ',', '.') }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Asuransi</span>
                            <span class="info-value">Rp {{ number_format($rental->insurance_fee ?? 0, 0, ',', '.') }}</span>
                        </div>
                        @if($rental->driver_fee)
                            <div class="info-row">
                                <span class="info-label">Biaya Sopir</span>
                                <span class="info-value">Rp {{ number_format($rental->driver_fee, 0, ',', '.') }}</span>
                            </div>
                        @endif
                        @if($rental->young_driver_fee)
                            <div class="info-row">
                                <span class="info-label">Biaya Young Driver</span>
                                <span class="info-value">Rp {{ number_format($rental->young_driver_fee, 0, ',', '.') }}</span>
                            </div>
                        @endif
                        @if($rental->discount_amount)
                            <div class="info-row">
                                <span class="info-label">Diskon</span>
                                <span class="info-value text-success">-Rp {{ number_format($rental->discount_amount, 0, ',', '.') }}</span>
                            </div>
                        @endif
                        <div class="info-row">
                            <span class="info-label">Pajak (11%)</span>
                            <span class="info-value">Rp {{ number_format($rental->tax_amount ?? 0, 0, ',', '.') }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Deposit</span>
                            <span class="info-value">Rp {{ number_format($rental->deposit_amount ?? 0, 0, ',', '.') }}</span>
                        </div>
                        <div class="info-row border-top mt-2 pt-2">
                            <span class="info-label fw-bold">Total</span>
                            <span class="info-value text-primary fs-5">Rp {{ number_format($rental->total_amount ?? 0, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="ri-bank-card-line me-1"></i> Pembayaran</h6>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <span class="info-label">Status</span>
                            <span class="info-value">
                                @php
                                    $payMap = [
                                        'unpaid' => ['label' => 'Belum Bayar', 'class' => 'bg-label-danger'],
                                        'partial' => ['label' => 'Sebagian', 'class' => 'bg-label-warning'],
                                        'paid' => ['label' => 'Lunas', 'class' => 'bg-label-success'],
                                        'refunded' => ['label' => 'Dikembalikan', 'class' => 'bg-label-info'],
                                    ];
                                    $pay = $payMap[$rental->payment_status] ?? ['label' => ucfirst($rental->payment_status), 'class' => 'bg-label-secondary'];
                                @endphp
                                <span class="badge {{ $pay['class'] }}">{{ $pay['label'] }}</span>
                            </span>
                        </div>
                        @if($rental->payments && $rental->payments->count())
                            <div class="table-responsive mt-2">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th class="text-end">Jumlah</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($rental->payments as $payment)
                                            <tr>
                                                <td>{{ $payment->created_at?->format('d/m/Y') ?? '-' }}</td>
                                                <td class="text-end">Rp {{ number_format($payment->amount ?? 0, 0, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted small mb-0 mt-2">Belum ada pembayaran.</p>
                        @endif
                    </div>
                </div>

                @if($rental->extensions && $rental->extensions->count())
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="ri-time-line me-1"></i> Perpanjangan</h6>
                        </div>
                        <div class="card-body">
                            @foreach($rental->extensions as $ext)
                                <div class="info-row">
                                    <span class="info-label">
                                        {{ $ext->old_end_date?->format('d/m/Y') }} → {{ $ext->new_end_date?->format('d/m/Y') }}
                                        <br>
                                        <span class="badge bg-label-{{ $ext->status == 'approved' ? 'success' : ($ext->status == 'pending' ? 'warning' : 'danger') }}">{{ ucfirst($ext->status) }}</span>
                                    </span>
                                    <span class="info-value">Rp {{ number_format($ext->additional_total ?? 0, 0, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($rental->fines && $rental->fines->count())
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="ri-alarm-warning-line me-1"></i> Denda</h6>
                        </div>
                        <div class="card-body">
                            @foreach($rental->fines as $fine)
                                <div class="info-row">
                                    <span class="info-label">{{ $fine->reason ?? '-' }}</span>
                                    <span class="info-value">Rp {{ number_format($fine->amount ?? 0, 0, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
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
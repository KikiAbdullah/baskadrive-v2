@extends('layouts.header')

@section('customcss')
    <style>
        .veh-hero { border: 0; overflow: hidden; }
        .veh-photo {
            display: grid;
            place-items: center;
            width: 100%;
            height: 190px;
            border-radius: 12px;
            background: linear-gradient(135deg, #eef0ff, #f7f7ff);
            color: #666cff;
            font-size: 4rem;
            overflow: hidden;
        }
        .veh-photo img { width: 100%; height: 100%; object-fit: cover; }
        .veh-plate {
            display: inline-block;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-weight: 800;
            letter-spacing: 1px;
            background: #1b1b2f;
            color: #fff;
            border-radius: 7px;
            padding: .35rem .8rem;
            font-size: 1.05rem;
        }
        .veh-info-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: .55rem 0;
            border-bottom: 1px solid #f0f1f7;
            font-size: .875rem;
        }
        .veh-info-row:last-child { border-bottom: 0; }
        .veh-info-row .lbl { color: #7a8096; font-weight: 600; }
        .veh-info-row .val { font-weight: 700; color: #1d1d33; text-align: right; }
        .veh-section-title {
            font-size: .8rem;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #666cff;
            margin-bottom: .75rem;
        }
    </style>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 row-gap-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="{{ route('master.vehicle.index') }}">Armada</a></li>
                        <li class="breadcrumb-item active">Detail Unit</li>
                    </ol>
                </nav>
                <h4 class="mb-1">Detail Unit</h4>
                <p class="mb-0 text-muted">{{ $subtitle }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('master.vehicle.index') }}" class="btn btn-label-secondary">
                    <i class="ri-arrow-go-back-line me-1"></i> Kembali
                </a>
                <a href="{{ route('master.vehicle.history', $item->vehicle_id) }}" class="btn btn-label-info">
                    <i class="ri-history-line me-1"></i> Riwayat
                </a>
                <a href="{{ route('master.vehicle.barcode', $item->vehicle_id) }}" class="btn btn-label-warning" target="_blank">
                    <i class="ri-qr-code-line me-1"></i> Barcode
                </a>
                @can('master_edit')
                    <a href="{{ route($url['edit'], $item->vehicle_id) }}" class="btn btn-primary">
                        <i class="ri-edit-line me-1"></i> Edit Unit
                    </a>
                @endcan
            </div>
        </div>

        {{-- Hero --}}
        <div class="card veh-hero shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="row g-4 align-items-center">
                    <div class="col-md-4 col-lg-3">
                        <div class="veh-photo">
                            @if ($item->photo_url)
                                <img src="{{ str_starts_with($item->photo_url, 'http') ? $item->photo_url : asset('storage/vehicle/'.$item->photo_url) }}"
                                    alt="{{ $item->license_plate }}" />
                            @else
                                <i class="ri-car-line"></i>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-8 col-lg-9">
                        <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                            <span class="veh-plate">{{ $item->license_plate }}</span>
                            {!! match ($item->status) {
                                'available' => '<span class="badge bg-label-success">Tersedia</span>',
                                'rented' => '<span class="badge bg-label-primary">Disewa</span>',
                                'maintenance' => '<span class="badge bg-label-warning">Maintenance</span>',
                                'reserved' => '<span class="badge bg-label-info">Reservasi</span>',
                                default => '<span class="badge bg-label-secondary">Nonaktif</span>',
                            } !!}
                        </div>
                        <h4 class="mb-1">{{ trim((optional($item->model)->brand?->brand_name ?? '').' '.(optional($item->model)->model_name ?? '')) ?: '-' }}</h4>
                        <p class="text-muted mb-3">Unit armada · {{ optional($item->location)->location_name ?? 'Lokasi belum diatur' }}</p>

                        <div class="row g-3">
                            <div class="col-6 col-md-3">
                                <div class="rounded-3 p-3" style="background:#f8f9ff">
                                    <small class="text-muted d-block">Tahun</small>
                                    <strong>{{ $item->year ?? '-' }}</strong>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="rounded-3 p-3" style="background:#f8f9ff">
                                    <small class="text-muted d-block">Warna</small>
                                    <strong>{{ $item->color ?? '-' }}</strong>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="rounded-3 p-3" style="background:#f8f9ff">
                                    <small class="text-muted d-block">Kilometer</small>
                                    <strong>{{ $item->mileage ? number_format($item->mileage, 0, ',', '.').' km' : '-' }}</strong>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="rounded-3 p-3" style="background:#f8f9ff">
                                    <small class="text-muted d-block">Tarif / hari</small>
                                    <strong class="text-primary">
                                        {{ \App\Support\AppSettings::money(optional($item->model)->base_price_per_day ?? 0) }}
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="veh-section-title"><i class="ri-settings-3-line me-1"></i> Spesifikasi</div>
                        <div class="veh-info-row"><span class="lbl">No. VIN</span><span class="val">{{ $item->vin ?? '-' }}</span></div>
                        <div class="veh-info-row"><span class="lbl">No. Mesin</span><span class="val">{{ $item->engine_number ?? '-' }}</span></div>
                        <div class="veh-info-row"><span class="lbl">Merek / Model</span><span class="val">{{ optional($item->model)->brand?->brand_name ?? '-' }} {{ optional($item->model)->model_name ?? '' }}</span></div>
                        <div class="veh-info-row"><span class="lbl">Kategori</span><span class="val">{{ optional($item->model)->category ?? '-' }}</span></div>
                        <div class="veh-info-row"><span class="lbl">Warna</span><span class="val">{{ $item->color ?? '-' }}</span></div>
                        <div class="veh-info-row"><span class="lbl">Tahun</span><span class="val">{{ $item->year ?? '-' }}</span></div>
                        <div class="veh-info-row"><span class="lbl">Kilometer</span><span class="val">{{ $item->mileage ? number_format($item->mileage, 0, ',', '.').' km' : '-' }}</span></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="veh-section-title"><i class="ri-map-pin-line me-1"></i> Operasional</div>
                        <div class="veh-info-row"><span class="lbl">Lokasi</span><span class="val">{{ optional($item->location)->location_name ?? '-' }}</span></div>
                        <div class="veh-info-row"><span class="lbl">Status</span><span class="val">{{ ucfirst($item->status ?? '-') }}</span></div>
                        <div class="veh-info-row"><span class="lbl">Plat nomor</span><span class="val">{{ $item->license_plate }}</span></div>
                        <div class="veh-info-row"><span class="lbl">Latitude</span><span class="val">{{ $item->latitude ?? '-' }}</span></div>
                        <div class="veh-info-row"><span class="lbl">Longitude</span><span class="val">{{ $item->longitude ?? '-' }}</span></div>
                        @if ($item->latitude && $item->longitude)
                            <a href="https://www.openstreetmap.org/?mlat={{ $item->latitude }}&mlon={{ $item->longitude }}#map=16/{{ $item->latitude }}/{{ $item->longitude }}"
                                target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary mt-3">
                                <i class="ri-map-pin-2-line me-1"></i> Lihat di Peta
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="veh-section-title"><i class="ri-money-dollar-circle-line me-1"></i> Keuangan</div>
                        <div class="veh-info-row"><span class="lbl">Tanggal beli</span><span class="val">{{ optional($item->purchase_date)->format('d M Y') ?? '-' }}</span></div>
                        <div class="veh-info-row"><span class="lbl">Harga beli</span><span class="val">{{ $item->purchase_price ? \App\Support\AppSettings::money($item->purchase_price) : '-' }}</span></div>
                        <div class="veh-info-row"><span class="lbl">Nilai saat ini</span><span class="val">{{ $item->current_value ? \App\Support\AppSettings::money($item->current_value) : '-' }}</span></div>
                        <div class="veh-info-row"><span class="lbl">Deposit model</span><span class="val">{{ \App\Support\AppSettings::money(optional($item->model)->deposit_amount ?? 0) }}</span></div>
                        <div class="veh-info-row"><span class="lbl">Dibuat</span><span class="val">{{ optional($item->created_at)->format('d M Y H:i') }}</span></div>
                        <div class="veh-info-row"><span class="lbl">Diubah</span><span class="val">{{ optional($item->updated_at)->format('d M Y H:i') }}</span></div>
                        <div class="mt-3 p-3 rounded-3" style="background:#f8f9ff;min-height:70px">
                            <small class="text-muted d-block mb-1">Catatan</small>
                            <span class="small">{{ $item->notes ?? 'Tidak ada catatan.' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

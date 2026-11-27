@extends('layouts.header')

@section('customcss')
    <style data-detail-style>
        .emp-hero { background: linear-gradient(135deg, #575df0, #666cff 55%, #8a5cf6); color: #fff; border: 0; overflow: hidden; }
        .emp-hero::after { content: ""; position: absolute; width: 260px; height: 260px; right: -80px; top: -140px; border-radius: 50%; background: rgba(255,255,255,.1); }
        .emp-hero .badge { color: #1d1d33 !important; }
        .emp-hero .badge.bg-label-success { color: #16794a !important; }
        .emp-hero .badge.bg-label-warning { color: #a16207 !important; }
        .emp-hero .badge.bg-label-danger { color: #c72e29 !important; }
        .emp-avatar { display: grid; place-items: center; width: 76px; height: 76px; flex: none; border-radius: 20px; background: rgba(255,255,255,.2); color: #fff; font-size: 2rem; }
        .emp-info-row { display: flex; justify-content: space-between; gap: 1rem; padding: .55rem 0; border-bottom: 1px solid #f0f1f7; font-size: .875rem; }
        .emp-info-row:last-child { border-bottom: 0; }
        .emp-info-row .lbl { color: #7a8096; font-weight: 600; }
        .emp-info-row .val { font-weight: 700; color: #1d1d33; text-align: right; }
        .emp-section-title { font-size: .8rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; color: #666cff; margin-bottom: .75rem; }
    </style>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex justify-content-end mb-4">
            <div class="d-flex flex-wrap gap-2" data-show-actions>
                <a href="{{ route('master.maintenance-type.index') }}" class="btn btn-label-secondary">
                    <i class="ri-arrow-go-back-line me-1"></i> Kembali
                </a>
                @can('master_edit')
                    <a href="{{ route($url['edit'], $item->type_id) }}" class="btn btn-primary">
                        <i class="ri-edit-line me-1"></i> Edit Tipe
                    </a>
                @endcan
            </div>
        </div>

        <div class="card emp-hero shadow-sm mb-4">
            <div class="card-body position-relative p-4">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="emp-avatar"><i class="ri-tools-line"></i></div>
                    <div class="flex-grow-1">
                        <h4 class="mb-1 text-white">{{ $item->type_name }}</h4>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-label-light">{{ number_format((int) $item->interval_km, 0, ',', '.') }} km</span>
                            <span class="badge bg-label-light">{{ (int) $item->interval_months }} bulan</span>
                            {!! $item->is_active ? '<span class="badge bg-label-success">Aktif</span>' : '<span class="badge bg-label-danger">Nonaktif</span>' !!}
                        </div>
                    </div>
                    <div class="d-none d-md-block text-end">
                        <small class="d-block" style="color:rgba(255,255,255,.75)">Dibuat</small>
                        <strong>{{ optional($item->created_at)->format('d M Y') }}</strong>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-4 mt-4 pt-3" style="border-top:1px solid rgba(255,255,255,.2)">
                    <div>
                        <small class="d-block" style="color:rgba(255,255,255,.75)">Interval KM</small>
                        <strong>{{ number_format((int) $item->interval_km, 0, ',', '.') }} km</strong>
                    </div>
                    <div>
                        <small class="d-block" style="color:rgba(255,255,255,.75)">Interval bulan</small>
                        <strong>{{ (int) $item->interval_months }} bulan</strong>
                    </div>
                    <div>
                        <small class="d-block" style="color:rgba(255,255,255,.75)">Deskripsi</small>
                        <strong>{{ $item->description ?: '-' }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="emp-section-title"><i class="ri-settings-3-line me-1"></i> Detail Servis</div>
                        <div class="emp-info-row"><span class="lbl">Nama tipe</span><span class="val">{{ $item->type_name }}</span></div>
                        <div class="emp-info-row"><span class="lbl">Interval KM</span><span class="val">{{ number_format((int) $item->interval_km, 0, ',', '.') }} km</span></div>
                        <div class="emp-info-row"><span class="lbl">Interval bulan</span><span class="val">{{ (int) $item->interval_months }} bulan</span></div>
                        <div class="emp-info-row"><span class="lbl">Deskripsi</span><span class="val">{{ $item->description ?: '-' }}</span></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="emp-section-title"><i class="ri-information-line me-1"></i> Status</div>
                        <div class="emp-info-row"><span class="lbl">Status</span><span class="val">{!! $item->is_active ? '<span class="badge bg-label-success">Aktif</span>' : '<span class="badge bg-label-danger">Nonaktif</span>' !!}</span></div>
                        <div class="emp-info-row"><span class="lbl">Dibuat</span><span class="val">{{ optional($item->created_at)->format('d M Y H:i') }}</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

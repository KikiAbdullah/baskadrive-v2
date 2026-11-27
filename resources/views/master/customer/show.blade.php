@extends('layouts.header')

@section('customcss')
    <style>
        .cust-hero {
            background: linear-gradient(135deg, #575df0, #666cff 55%, #8a5cf6);
            color: #fff;
            border: 0;
            overflow: hidden;
        }
        .cust-hero::after {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            right: -80px;
            top: -140px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .1);
        }
        /* Badge di atas hero terang: paksa teks gelap agar tetap terbaca */
        .cust-hero .badge { color: #1d1d33 !important; }
        .cust-hero .badge.bg-label-success { color: #16794a !important; }
        .cust-hero .badge.bg-label-warning { color: #a16207 !important; }
        .cust-hero .badge.bg-label-danger { color: #c72e29 !important; }

        .cust-avatar {
            display: grid;
            place-items: center;
            width: 76px;
            height: 76px;
            flex: none;
            border-radius: 20px;
            background: rgba(255, 255, 255, .2);
            color: #fff;
            font-size: 1.9rem;
            font-weight: 800;
            letter-spacing: -.03em;
        }
        .cust-info-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: .55rem 0;
            border-bottom: 1px solid #f0f1f7;
            font-size: .875rem;
        }
        .cust-info-row:last-child { border-bottom: 0; }
        .cust-info-row .lbl { color: #7a8096; font-weight: 600; }
        .cust-info-row .val { font-weight: 700; color: #1d1d33; text-align: right; }
        .cust-section-title {
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
                        <li class="breadcrumb-item"><a href="{{ route('master.customer.index') }}">Pelanggan</a></li>
                        <li class="breadcrumb-item active">Detail</li>
                    </ol>
                </nav>
                <h4 class="mb-1">Profil Pelanggan</h4>
                <p class="mb-0 text-muted">{{ $subtitle }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('master.customer.index') }}" class="btn btn-label-secondary">
                    <i class="ri-arrow-go-back-line me-1"></i> Kembali
                </a>
                @can('master_edit')
                    <a href="{{ route($url['edit'], $item->customer_id) }}" class="btn btn-primary">
                        <i class="ri-edit-line me-1"></i> Edit Pelanggan
                    </a>
                @endcan
            </div>
        </div>

        {{-- Hero --}}
        <div class="card cust-hero shadow-sm mb-4">
            <div class="card-body position-relative p-4">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="cust-avatar">{{ strtoupper(mb_substr($item->full_name ?? '?', 0, 1)) }}</div>
                    <div class="flex-grow-1">
                        <h4 class="mb-1 text-white">{{ $item->full_name }}</h4>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-label-light">
                                {{ $item->customer_type === 'individual' ? 'Individu' : 'Korporasi' }}
                            </span>
                            {!! $item->is_verified
                                ? '<span class="badge bg-label-success">Terverifikasi</span>'
                                : '<span class="badge bg-label-warning">Belum verifikasi</span>' !!}
                            {!! $item->is_blacklisted
                                ? '<span class="badge bg-label-danger">Blacklist</span>'
                                : '<span class="badge bg-label-success">Aman</span>' !!}
                        </div>
                    </div>
                    <div class="d-none d-md-block text-end">
                        <small class="d-block" style="color:rgba(255,255,255,.75)">Bergabung sejak</small>
                        <strong>{{ optional($item->created_at)->format('d M Y') }}</strong>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-4 mt-4 pt-3" style="border-top:1px solid rgba(255,255,255,.2)">
                    <div>
                        <small class="d-block" style="color:rgba(255,255,255,.75)">Telepon</small>
                        <strong><i class="ri-phone-line me-1"></i>{{ $item->phone ?? '-' }}</strong>
                    </div>
                    <div>
                        <small class="d-block" style="color:rgba(255,255,255,.75)">Email</small>
                        <strong><i class="ri-mail-line me-1"></i>{{ $item->email ?? '-' }}</strong>
                    </div>
                    <div>
                        <small class="d-block" style="color:rgba(255,255,255,.75)">Lokasi</small>
                        <strong><i class="ri-map-pin-line me-1"></i>{{ trim(($item->city ?? '').' '.($item->province ?? '')) ?: '-' }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="cust-section-title"><i class="ri-id-card-line me-1"></i> Identitas</div>
                        <div class="cust-info-row"><span class="lbl">Nama lengkap</span><span class="val">{{ $item->full_name }}</span></div>
                        <div class="cust-info-row"><span class="lbl">Perusahaan</span><span class="val">{{ $item->company_name ?? '-' }}</span></div>
                        <div class="cust-info-row"><span class="lbl">NIK</span><span class="val">{{ $item->id_card_number ?? '-' }}</span></div>
                        <div class="cust-info-row"><span class="lbl">No. SIM</span><span class="val">{{ $item->driver_license_number ?? '-' }}</span></div>
                        <div class="cust-info-row"><span class="lbl">Berlaku SIM</span><span class="val">{{ optional($item->driver_license_expiry)->format('d M Y') ?? '-' }}</span></div>
                        <div class="cust-info-row"><span class="lbl">Tanggal lahir</span><span class="val">{{ optional($item->date_of_birth)->format('d M Y') ?? '-' }}</span></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="cust-section-title"><i class="ri-contacts-line me-1"></i> Kontak &amp; Alamat</div>
                        <div class="cust-info-row"><span class="lbl">Email</span><span class="val">{{ $item->email ?? '-' }}</span></div>
                        <div class="cust-info-row"><span class="lbl">Telepon</span><span class="val">{{ $item->phone ?? '-' }}</span></div>
                        <div class="cust-info-row"><span class="lbl">Alamat</span><span class="val">{{ $item->address ?? '-' }}</span></div>
                        <div class="cust-info-row"><span class="lbl">Kota</span><span class="val">{{ $item->city ?? '-' }}</span></div>
                        <div class="cust-info-row"><span class="lbl">Provinsi</span><span class="val">{{ $item->province ?? '-' }}</span></div>
                        <div class="cust-info-row"><span class="lbl">Kode pos</span><span class="val">{{ $item->postal_code ?? '-' }}</span></div>
                        <div class="cust-info-row"><span class="lbl">Negara</span><span class="val">{{ $item->country ?? '-' }}</span></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="cust-section-title"><i class="ri-shield-check-line me-1"></i> Status Akun</div>
                        <div class="cust-info-row">
                            <span class="lbl">Verifikasi</span>
                            <span class="val">{!! $item->is_verified ? '<span class="badge bg-label-success">Terverifikasi</span>' : '<span class="badge bg-label-warning">Pending</span>' !!}</span>
                        </div>
                        <div class="cust-info-row">
                            <span class="lbl">Blacklist</span>
                            <span class="val">{!! $item->is_blacklisted ? '<span class="badge bg-label-danger">Ya</span>' : '<span class="badge bg-label-success">Tidak</span>' !!}</span>
                        </div>
                        @if ($item->is_blacklisted)
                            <div class="cust-info-row"><span class="lbl">Alasan</span><span class="val text-danger">{{ $item->blacklist_reason ?? '-' }}</span></div>
                        @endif
                        <div class="cust-info-row"><span class="lbl">Dibuat</span><span class="val">{{ optional($item->created_at)->format('d M Y H:i') }}</span></div>
                        <div class="cust-info-row"><span class="lbl">Diubah</span><span class="val">{{ optional($item->updated_at)->format('d M Y H:i') }}</span></div>
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

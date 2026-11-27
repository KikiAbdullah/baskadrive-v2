@extends('layouts.header')

@section('customcss')
    <style data-detail-style>
        .emp-hero { background: linear-gradient(135deg, #575df0, #666cff 55%, #8a5cf6); color: #fff; border: 0; overflow: hidden; }
        .emp-hero::after { content: ""; position: absolute; width: 260px; height: 260px; right: -80px; top: -140px; border-radius: 50%; background: rgba(255,255,255,.1); }
        .emp-hero .badge { color: #1d1d33 !important; }
        .emp-hero .badge.bg-label-success { color: #16794a !important; }
        .emp-hero .badge.bg-label-danger { color: #c72e29 !important; }
        .emp-avatar { display: grid; place-items: center; width: 76px; height: 76px; flex: none; border-radius: 20px; background: rgba(255,255,255,.2); color: #fff; font-size: 1.9rem; font-weight: 800; letter-spacing: -.03em; }
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
                <a href="{{ route('user-setup.user.index') }}" class="btn btn-label-secondary">
                    <i class="ri-arrow-go-back-line me-1"></i> Kembali
                </a>
                @can('users_edit')
                    <a href="{{ route($url['edit'], $item->id) }}" class="btn btn-primary">
                        <i class="ri-edit-line me-1"></i> Edit User
                    </a>
                @endcan
            </div>
        </div>

        <div class="card emp-hero shadow-sm mb-4">
            <div class="card-body position-relative p-4">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="emp-avatar">{{ strtoupper(mb_substr($item->name ?: $item->username ?: '?', 0, 1)) }}</div>
                    <div class="flex-grow-1">
                        <h4 class="mb-1 text-white">{{ $item->name ?: $item->username }}</h4>
                        <div class="d-flex flex-wrap gap-2">
                            @forelse ($item->roles as $role)
                                <span class="badge bg-label-light">{{ $role->name }}</span>
                            @empty
                                <span class="badge bg-label-light">Tanpa role</span>
                            @endforelse
                            {!! $item->deleted_at
                                ? '<span class="badge bg-label-danger">Nonaktif</span>'
                                : '<span class="badge bg-label-success">Aktif</span>' !!}
                        </div>
                    </div>
                    <div class="d-none d-md-block text-end">
                        <small class="d-block" style="color:rgba(255,255,255,.75)">Bergabung sejak</small>
                        <strong>{{ optional($item->created_at)->format('d M Y') }}</strong>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-4 mt-4 pt-3" style="border-top:1px solid rgba(255,255,255,.2)">
                    <div>
                        <small class="d-block" style="color:rgba(255,255,255,.75)">Username</small>
                        <strong><i class="ri-user-line me-1"></i>{{ $item->username }}</strong>
                    </div>
                    <div>
                        <small class="d-block" style="color:rgba(255,255,255,.75)">Email</small>
                        <strong><i class="ri-mail-line me-1"></i>{{ $item->email ?: '-' }}</strong>
                    </div>
                    <div>
                        <small class="d-block" style="color:rgba(255,255,255,.75)">No. HP</small>
                        <strong><i class="ri-phone-line me-1"></i>{{ $item->nowa ?: '-' }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="emp-section-title"><i class="ri-id-card-line me-1"></i> Identitas &amp; Kontak</div>
                        <div class="emp-info-row"><span class="lbl">Username</span><span class="val">{{ $item->username }}</span></div>
                        <div class="emp-info-row"><span class="lbl">Nama</span><span class="val">{{ $item->name ?: '-' }}</span></div>
                        <div class="emp-info-row"><span class="lbl">Email</span><span class="val">{{ $item->email ?: '-' }}</span></div>
                        <div class="emp-info-row"><span class="lbl">Email terverifikasi</span><span class="val">{{ $item->email_verified_at ? optional($item->email_verified_at)->format('d M Y H:i') : 'Belum' }}</span></div>
                        <div class="emp-info-row"><span class="lbl">No. HP</span><span class="val">{{ $item->nowa ?: '-' }}</span></div>
                        <div class="emp-info-row"><span class="lbl">Karyawan terkait</span><span class="val">{{ optional($item->employee)->full_name ?: '-' }}</span></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="emp-section-title"><i class="ri-shield-user-line me-1"></i> Akses &amp; Akun</div>
                        <div class="emp-info-row">
                            <span class="lbl">Role</span>
                            <span class="val">{{ $item->roles->pluck('name')->implode(', ') ?: '-' }}</span>
                        </div>
                        <div class="emp-info-row"><span class="lbl">Status</span><span class="val">{!! $item->deleted_at ? '<span class="badge bg-label-danger">Nonaktif</span>' : '<span class="badge bg-label-success">Aktif</span>' !!}</span></div>
                        <div class="emp-info-row"><span class="lbl">Two-factor</span><span class="val">{{ $item->two_factor_cookie_expires_at ? 'Aktif' : 'Nonaktif' }}</span></div>
                        <div class="emp-info-row"><span class="lbl">Token terakhir</span><span class="val">{{ optional($item->token_last_request)->format('d M Y H:i') ?: '-' }}</span></div>
                        <div class="emp-info-row"><span class="lbl">Dibuat</span><span class="val">{{ optional($item->created_at)->format('d M Y H:i') }}</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

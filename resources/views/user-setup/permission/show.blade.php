@extends('layouts.header')

@section('customcss')
    <style data-detail-style>
        .emp-hero { background: linear-gradient(135deg, #575df0, #666cff 55%, #8a5cf6); color: #fff; border: 0; overflow: hidden; }
        .emp-hero::after { content: ""; position: absolute; width: 260px; height: 260px; right: -80px; top: -140px; border-radius: 50%; background: rgba(255,255,255,.1); }
        .emp-hero .badge { color: #1d1d33 !important; }
        .emp-hero .badge.bg-label-success { color: #16794a !important; }
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
                <a href="{{ route('user-setup.permission.index') }}" class="btn btn-label-secondary">
                    <i class="ri-arrow-go-back-line me-1"></i> Kembali
                </a>
                @can('permissions_edit')
                    <a href="{{ route($url['edit'], $item->id) }}" class="btn btn-primary">
                        <i class="ri-edit-line me-1"></i> Edit Permission
                    </a>
                @endcan
            </div>
        </div>

        <div class="card emp-hero shadow-sm mb-4">
            <div class="card-body position-relative p-4">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="emp-avatar"><i class="ri-key-line"></i></div>
                    <div class="flex-grow-1">
                        <h4 class="mb-1 text-white">{{ $item->name }}</h4>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-label-light">{{ $item->guard_name ?: 'web' }}</span>
                            <span class="badge bg-label-light">{{ $item->roles->count() }} role</span>
                        </div>
                    </div>
                    <div class="d-none d-md-block text-end">
                        <small class="d-block" style="color:rgba(255,255,255,.75)">Dibuat</small>
                        <strong>{{ optional($item->created_at)->format('d M Y') }}</strong>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-4 mt-4 pt-3" style="border-top:1px solid rgba(255,255,255,.2)">
                    <div>
                        <small class="d-block" style="color:rgba(255,255,255,.75)">Nama permission</small>
                        <strong>{{ $item->name }}</strong>
                    </div>
                    <div>
                        <small class="d-block" style="color:rgba(255,255,255,.75)">Guard</small>
                        <strong>{{ $item->guard_name ?: 'web' }}</strong>
                    </div>
                    <div>
                        <small class="d-block" style="color:rgba(255,255,255,.75)">Digunakan oleh</small>
                        <strong>{{ $item->roles->count() }} role</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="emp-section-title"><i class="ri-key-line me-1"></i> Detail Permission</div>
                        <div class="emp-info-row"><span class="lbl">ID</span><span class="val">{{ $item->id }}</span></div>
                        <div class="emp-info-row"><span class="lbl">Nama</span><span class="val">{{ $item->name }}</span></div>
                        <div class="emp-info-row"><span class="lbl">Guard name</span><span class="val">{{ $item->guard_name ?: 'web' }}</span></div>
                        <div class="emp-info-row"><span class="lbl">Dibuat</span><span class="val">{{ optional($item->created_at)->format('d M Y H:i') }}</span></div>
                        <div class="emp-info-row"><span class="lbl">Diubah</span><span class="val">{{ optional($item->updated_at)->format('d M Y H:i') }}</span></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="emp-section-title"><i class="ri-shield-user-line me-1"></i> Role Terkait</div>
                        @forelse ($item->roles as $role)
                            <div class="emp-info-row"><span class="lbl">{{ $role->name }}</span><span class="val">Role</span></div>
                        @empty
                            <div class="text-muted small py-3">Permission ini belum digunakan oleh role manapun.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

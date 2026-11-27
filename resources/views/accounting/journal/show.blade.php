@extends('layouts.header')

@section('customcss')
    <style>
        .jr-hero {
            background: linear-gradient(135deg, #575df0, #666cff 55%, #8a5cf6);
            color: #fff;
            border: 0;
            overflow: hidden;
        }
        .jr-hero::after {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            right: -80px;
            top: -140px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .1);
        }
        .jr-hero .badge { color: #1d1d33 !important; }
        .jr-hero .badge.bg-label-success { color: #16794a !important; }
        .jr-avatar {
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
        .jr-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: .5rem 0;
            border-bottom: 1px solid #f0f1f7;
            font-size: .875rem;
        }
        .jr-row:last-child { border-bottom: 0; }
        .jr-row .lbl { color: #7a8096; font-weight: 600; }
        .jr-row .val { font-weight: 700; color: #1d1d33; text-align: right; }
        .jr-title {
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
        <div class="d-flex justify-content-end mb-4">
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('accounting.journal.index') }}" class="btn btn-label-secondary">
                    <i class="ri-arrow-go-back-line me-1"></i> Kembali
                </a>
                @can('accounting_export')
                    <a href="{{ route('accounting.journal.export', $item->journal_id) }}" class="btn btn-primary">
                        <i class="ri-download-line me-1"></i> Export CSV
                    </a>
                @endcan
            </div>
        </div>

        <div class="card jr-hero shadow-sm mb-4">
            <div class="card-body position-relative p-4">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="jr-avatar"><i class="ri-book-2-line"></i></div>
                    <div class="flex-grow-1">
                        <h4 class="mb-1 text-white">{{ $item->reference_number ?: 'Jurnal' }}</h4>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-label-light">
                                {{ \App\Models\Journal::TYPES[$item->journal_type] ?? ($item->journal_type ?: '-') }}
                            </span>
                            <span class="badge bg-label-light">{{ $item->details->count() }} entri</span>
                            @if (abs(($item->details->sum('debit') ?? 0) - ($item->details->sum('credit') ?? 0)) < 0.01)
                                <span class="badge bg-label-success">Seimbang</span>
                            @else
                                <span class="badge bg-label-danger">Tidak seimbang</span>
                            @endif
                        </div>
                    </div>
                    <div class="d-none d-md-block text-end">
                        <small class="d-block" style="color:rgba(255,255,255,.75)">Tanggal</small>
                        <strong>{{ $item->transaction_date?->locale('id')->translatedFormat('d M Y') ?? '-' }}</strong>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-4 mt-4 pt-3" style="border-top:1px solid rgba(255,255,255,.2)">
                    <div>
                        <small class="d-block" style="color:rgba(255,255,255,.75)">Total debit</small>
                        <strong>{{ \App\Support\AppSettings::money($item->details->sum('debit')) }}</strong>
                    </div>
                    <div>
                        <small class="d-block" style="color:rgba(255,255,255,.75)">Total kredit</small>
                        <strong>{{ \App\Support\AppSettings::money($item->details->sum('credit')) }}</strong>
                    </div>
                    <div>
                        <small class="d-block" style="color:rgba(255,255,255,.75)">Dibuat oleh</small>
                        <strong>{{ $item->creator?->full_name ?: '-' }}</strong>
                    </div>
                </div>
            </div>
        </div>

        @include('layouts.alert')

        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="jr-title"><i class="ri-information-line me-1"></i> Informasi Jurnal</div>
                        <div class="jr-row"><span class="lbl">No. referensi</span><span class="val">{{ $item->reference_number ?: '-' }}</span></div>
                        <div class="jr-row"><span class="lbl">Tanggal</span><span class="val">{{ $item->transaction_date?->locale('id')->translatedFormat('d F Y') ?? '-' }}</span></div>
                        <div class="jr-row"><span class="lbl">Tipe</span><span class="val">{{ \App\Models\Journal::TYPES[$item->journal_type] ?? ($item->journal_type ?: '-') }}</span></div>
                        <div class="jr-row"><span class="lbl">Dibuat oleh</span><span class="val">{{ $item->creator?->full_name ?: '-' }}</span></div>
                        <div class="jr-row"><span class="lbl">Keterangan</span><span class="val text-wrap">{{ $item->description ?: '-' }}</span></div>
                        <div class="jr-row"><span class="lbl">Dibuat</span><span class="val">{{ optional($item->created_at)->format('d M Y H:i') }}</span></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card h-100">
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
                                    @forelse ($item->details as $d)
                                        <tr>
                                            <td>{{ $d->account?->account_code ?? '-' }}</td>
                                            <td>{{ $d->account?->account_name ?? '-' }}</td>
                                            <td>{{ $d->description ?: '-' }}</td>
                                            <td class="text-end">{{ \App\Support\AppSettings::money($d->debit ?? 0) }}</td>
                                            <td class="text-end">{{ \App\Support\AppSettings::money($d->credit ?? 0) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada entri pada jurnal ini.</td></tr>
                                    @endforelse
                                </tbody>
                                <tfoot>
                                    <tr class="table-active">
                                        <th colspan="3" class="text-end">Total</th>
                                        <th class="text-end">{{ \App\Support\AppSettings::money($item->details->sum('debit')) }}</th>
                                        <th class="text-end">{{ \App\Support\AppSettings::money($item->details->sum('credit')) }}</th>
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

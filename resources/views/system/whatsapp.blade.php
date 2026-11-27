@extends('layouts.header')

@section('customcss')
    <style>
        .wa-card .card-header {
            background: #666cff14;
            border-bottom: 1px solid #e5e5e8;
            padding: 0.85rem 1.15rem;
        }

        .wa-card .card-header h6 {
            color: #4c52f0;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 1px;
        }

        .wa-status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }

        .wa-status-dot.on { background: #28c76f; box-shadow: 0 0 0 3px #28c76f29; }
        .wa-status-dot.off { background: #b4b8c4; box-shadow: 0 0 0 3px #b4b8c429; }
        .wa-status-dot.err { background: #ea5455; box-shadow: 0 0 0 3px #ea545529; }

        .wa-qr {
            width: 240px;
            height: 240px;
            background: #f7f7f9;
            border: 1px dashed #d9dee3;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            overflow: hidden;
        }

        .wa-qr img { width: 100%; height: 100%; object-fit: contain; }

        .wa-muted { font-size: 0.78rem; color: #697a8d; }

        .wa-toggle-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 0.6rem 0.9rem;
            background: #f7f7f9;
            border: 1px solid #e5e5e8;
            border-radius: 8px;
        }

        .form-check.form-switch .form-check-input {
            width: 2.6rem;
            height: 1.35rem;
            cursor: pointer;
        }

        .wa-badge {
            font-size: 0.68rem;
            font-weight: 700;
            padding: 0.3rem 0.65rem;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .wa-badge.on { background: #72e12829; color: #3ea016; }
        .wa-badge.off { background: #82868b1f; color: #6a6f85; }

        .wa-provider-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
    </style>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2 row-gap-3">
            <div class="d-flex flex-column justify-content-center">
                <h4 class="mb-1">{{ $title }}</h4>
                <p class="mb-0">{{ $subtitle }}</p>
            </div>
            <div class="d-flex gap-2">
                <span class="badge {{ $gatewayConfigured ? 'bg-label-success' : 'bg-label-secondary' }}">
                    Gateway internal {{ $gatewayConfigured ? 'terkonfigurasi' : 'belum dikonfigurasi (.env)' }}
                </span>
            </div>
        </div>

        @include('layouts.alert')

        <div class="alert alert-info d-flex" role="alert">
            <i class="ri-information-line me-2 mt-0"></i>
            <div class="small">
                <strong>Baileys</strong> (baileys.wiki) adalah provider <strong>utama</strong> — berjalan via WebSocket
                tanpa browser. <strong>WhatsApp Web JS</strong> (wwebjs.dev) adalah provider <strong>cadangan</strong>
                via Chrome headless. Keduanya multi-device: satu scan QR menghubungkan perangkat tambahan pada nomor
                yang sama, dan sesi tersimpan sehingga tidak perlu scan ulang. Masing-masing punya saklar on/off
                sendiri; pesan dikirim lewat provider utama dan otomatis pindah ke cadangan bila utama tidak terhubung.
            </div>
        </div>

        <div class="row g-4">
            @foreach(['baileys' => 'Utama — WebSocket', 'wwebjs' => 'Cadangan — Chrome headless'] as $key => $role)
                @php $p = $providers[$key]; @endphp
                <div class="col-md-6">
                    <div class="card wa-card h-100" data-provider="{{ $key }}">
                        <div class="card-header">
                            <div class="wa-provider-header">
                                <h6 class="mb-0">
                                    <i class="ri-whatsapp-line me-1"></i>
                                    {{ $key === 'baileys' ? 'Baileys' : 'WhatsApp Web JS' }}
                                    <span class="badge bg-label-primary ms-1">{{ $role }}</span>
                                </h6>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input wa-switch" type="checkbox"
                                        data-provider="{{ $key }}" {{ $p['enabled'] ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="wa-status-dot" data-el="dot"></span>
                                <strong data-el="state">Memuat status…</strong>
                                <span class="wa-badge ms-auto {{ $p['enabled'] ? 'on' : 'off' }}" data-el="badge">
                                    {{ $p['enabled'] ? 'ON' : 'OFF' }}
                                </span>
                            </div>

                            <div class="wa-muted mb-1">
                                Nomor terhubung: <span data-el="user">{{ $p['user']['id'] ?? '—' }}</span>
                            </div>
                            <div class="wa-muted mb-3">
                                Pesan terkirim: <span data-el="sent">{{ $p['messages_sent'] }}</span> · Sesi:
                                <span>default</span>
                            </div>

                            <div class="wa-qr mb-3" data-el="qr-box">
                                @if(!empty($p['qr']))
                                    <img src="{{ $p['qr'] }}" alt="QR" data-el="qr-img">
                                @else
                                    <span class="wa-muted" data-el="qr-empty">QR muncul di sini saat perlu pairing</span>
                                @endif
                            </div>

                            @if(!empty($p['error']))
                                <div class="alert alert-warning py-2 px-3 small mb-3" data-el="error">
                                    {{ $p['error'] }}
                                </div>
                            @endif

                            <div class="d-flex gap-2 flex-wrap">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-action="start"
                                    data-provider="{{ $key }}">
                                    <i class="ri-play-circle-line me-1"></i> Mulai Sesi
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-action="stop"
                                    data-provider="{{ $key }}">
                                    <i class="ri-pause-circle-line me-1"></i> Stop
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-action="logout"
                                    data-provider="{{ $key }}"
                                    onclick="return confirm('Logout perangkat ini dari WhatsApp & hapus sesi tersimpan? Scan QR ulang diperlukan.')">
                                    <i class="ri-logout-box-line me-1"></i> Logout
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- ===== Uji kirim ===== --}}
            <div class="col-12">
                <div class="card wa-card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="ri-send-plane-line me-1"></i> Uji Pengiriman</h6>
                    </div>
                    <div class="card-body">
                        <form id="waTestForm" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-md-4">
                                <label class="form-label">Nomor Tujuan</label>
                                <input type="text" class="form-control" id="waTestPhone" placeholder="08xxxxxxxxxx"
                                    required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Pesan</label>
                                <input type="text" class="form-control" id="waTestMessage"
                                    value="Tes gateway WhatsApp BaskaDrive" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="ri-send-plane-fill me-1"></i> Kirim
                                </button>
                            </div>
                        </form>
                        <div class="wa-muted mt-2">Pesan dikirim via provider utama yang menyala; otomatis pindah ke
                            cadangan bila utama tidak terhubung.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('customjs')
    <script>
        const WA_ROUTES = {
            status: '{{ route('system.whatsapp.status') }}',
            toggle: '{{ route('system.whatsapp.toggle') }}',
            start: '{{ route('system.whatsapp.start', ':provider') }}',
            stop: '{{ route('system.whatsapp.stop', ':provider') }}',
            logout: '{{ route('system.whatsapp.logout', ':provider') }}',
        };

        function waCard(provider) {
            return document.querySelector('.wa-card[data-provider="' + provider + '"]');
        }

        function waRender(provider, s) {
            const card = waCard(provider);
            if (!card) return;
            const $ = (el) => card.querySelector('[data-el="' + el + '"]');

            const dot = $('dot');
            dot.className = 'wa-status-dot ' + (s.connected ? 'on' : (s.error ? 'err' : 'off'));

            $('state').textContent = s.connected
                ? 'Terhubung' + (s.user && s.user.id ? ' — ' + s.user.id : '')
                : (s.enabled ? (s.qr_ready ? 'Menunggu scan QR' : 'Tidak terhubung') : 'Dimatikan');

            $('user').textContent = (s.user && s.user.id) || '—';
            $('sent').textContent = s.messages_sent || 0;

            const badge = $('badge');
            badge.className = 'wa-badge ms-auto ' + (s.enabled ? 'on' : 'off');
            badge.textContent = s.enabled ? 'ON' : 'OFF';

            // QR
            const box = $('qr-box');
            if (s.qr_ready && s.qr) {
                if ($('qr-img')) {
                    $('qr-img').src = s.qr;
                } else {
                    box.innerHTML = '<img src="' + s.qr + '" alt="QR">';
                }
            } else if (!$('qr-img')) {
                // biarkan teks placeholder
            } else if (!s.qr_ready) {
                box.innerHTML = '<span class="wa-muted">QR muncul di sini saat perlu pairing</span>';
            }

            // Error
            const errBox = card.querySelector('[data-el="error"]');
            if (s.error) {
                if (errBox) errBox.textContent = s.error;
                else $('.wa-qr').insertAdjacentHTML('afterend',
                    '<div class="alert alert-warning py-2 px-3 small mb-3" data-el="error"></div>');
                card.querySelector('[data-el="error"]').textContent = s.error;
            } else if (errBox) {
                errBox.remove();
            }
        }

        async function waPoll() {
            try {
                const res = await fetch(WA_ROUTES.status, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                if (json.status && json.data) {
                    Object.keys(json.data).forEach((p) => waRender(p, json.data[p]));
                }
            } catch (e) { /* gateway mati — biarkan status terakhir */ }
        }

        async function waPost(url, body) {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(body || {}),
            });
            return res.json();
        }

        // Saklar on/off per provider
        document.querySelectorAll('.wa-switch').forEach((sw) => {
            sw.addEventListener('change', async function() {
                const provider = this.dataset.provider;
                const on = this.checked;
                this.disabled = true;
                try {
                    const json = await waPost(WA_ROUTES.toggle, { provider, enabled: on });
                    if (!json.status) {
                        this.checked = !on;
                        alert(json.msg || 'Gagal mengubah status provider.');
                    } else {
                        waPoll();
                    }
                } finally {
                    this.disabled = false;
                }
            });
        });

        // Tombol sesi
        document.querySelectorAll('[data-action]').forEach((btn) => {
            btn.addEventListener('click', async function() {
                const provider = this.dataset.provider;
                const action = this.dataset.action;
                this.disabled = true;
                try {
                    const json = await waPost(WA_ROUTES[action].replace(':provider', provider));
                    if (json.msg && !json.status) alert(json.msg);
                    setTimeout(waPoll, 500);
                } finally {
                    this.disabled = false;
                }
            });
        });

        // Uji kirim
        document.getElementById('waTestForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            try {
                const json = await waPost('{{ route('system.whatsapp.test-send') }}', {
                    phone: document.getElementById('waTestPhone').value,
                    message: document.getElementById('waTestMessage').value,
                });
                alert(json.msg);
            } finally {
                btn.disabled = false;
            }
        });

        // Polling status tiap 5 detik
        waPoll();
        setInterval(waPoll, 5000);
    </script>
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

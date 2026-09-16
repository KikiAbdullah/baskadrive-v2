@extends('layouts.header')

@section('customcss')
<style>
    .car-view-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: .75rem;
    }
    @@media (max-width: 575.98px) {
        .car-view-grid { grid-template-columns: 1fr; }
    }
    .car-view { margin: 0; }
    .car-view figcaption {
        font-size: .75rem; font-weight: 600; text-transform: uppercase;
        letter-spacing: .4px; color: #697a8d; margin-bottom: .25rem; text-align: center;
    }
    .car-view-frame {
        position: relative;
        width: 100%;
        min-height: 150px;
        aspect-ratio: 4 / 3;
        margin: 0;
        background: #f8f9fa;
        border: 2px solid #d9dee3;
        border-radius: 12px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: crosshair;
        user-select: none;
        touch-action: none;
    }
    .car-view-frame img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        -webkit-user-drag: none;
        user-select: none;
    }
    .car-view-frame.img-missing::after {
        content: "Foto " attr(data-view-label) " belum tersedia";
        color: #adb5bd;
        font-size: .8rem;
        text-align: center;
        padding: 0 .5rem;
    }
    .damage-point {
        position: absolute;
        width: 16px; height: 16px;
        background: #ff4d49;
        border: 2px solid #fff;
        border-radius: 50%;
        transform: translate(-50%, -50%);
        cursor: pointer;
        box-shadow: 0 1px 4px rgba(0,0,0,0.3);
    }
    .damage-point::after {
        content: attr(data-idx);
        position: absolute;
        top: -8px; right: -8px;
        background: #ff4d49;
        color: #fff;
        font-size: 9px;
        width: 14px; height: 14px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
    }
</style>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex flex-column justify-content-center mb-2">
            <h4 class="mb-1">{{ $title }}</h4>
            <p class="mb-1">{{ $subtitle }}</p>
            <p class="text-muted small">{{ $rental->vehicle->license_plate ?? '' }} — {{ $rental->vehicle->model->model_name ?? '' }}</p>
        </div>
        @include('layouts.alert')
        @if($inspection)
            <div class="alert alert-info"><i class="ri-information-line me-1"></i> Inspeksi terakhir: {{ $inspection->created_at->format('d M Y H:i') }} oleh {{ $inspection->creator->name ?? '-' }}</div>
        @elseif(!empty($inherited))
            <div class="alert alert-warning py-2 small"><i class="ri-history-line me-1"></i> Kolom di bawah <strong>dibawa dari inspeksi kendaraan terakhir</strong> ({{ $inherited->created_at?->format('d M Y H:i') ?? '-' }}) sebagai kondisi awal. Sesuaikan titik/catatan dengan kondisi saat serah terima ini.</div>
        @endif
        <form method="POST" action="{{ route('rental.detail.handover.store', [$rental->rental_id, $type]) }}">
            @csrf
            <div class="row">
                <div class="col-md-4">
                    <div class="card mb-3">
                        <div class="card-header"><h6 class="mb-0">Kondisi Umum</h6></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Odometer (KM)</label>
                                <input type="number" name="odometer" value="{{ old('odometer', $inspection->odometer ?? $rental->vehicle->mileage) }}" class="form-control {{ $errors->has('odometer') ? 'is-invalid' : '' }}">
                                @error('odometer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Level BBM</label>
                                <select name="fuel_level" class="form-select">
                                    @foreach(['full'=>'Full','three_quarter'=>'3/4','half'=>'1/2','quarter'=>'1/4','empty'=>'Empty'] as $k=>$v)
                                        <option value="{{ $k }}" {{ old('fuel_level', $seed->fuel_level ?? '') == $k ? 'selected' : '' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Catatan Eksterior</label>
                                <textarea name="exterior_notes" rows="2" class="form-control" placeholder="Baret, penyok, dll.">{{ old('exterior_notes', $seed->exterior_notes ?? '') }}</textarea>
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Catatan Interior</label>
                                <textarea name="interior_notes" rows="2" class="form-control" placeholder="Kebersihan, bau, dll.">{{ old('interior_notes', $seed->interior_notes ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-3">
                        <div class="card-header"><h6 class="mb-0">Kelengkapan</h6></div>
                        <div class="card-body">
                            @php $check = old('checklist', $seed->checklist ?? []); @endphp
                            @foreach(['STNK','Dongkrak','Ban Serep','Kunci Roda','Segitiga Pengaman','P3K','APAR','Karpet','Toolkit','Payung'] as $item)
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="checklist[]" value="{{ $item }}" id="chk_{{ Str::slug($item) }}" {{ in_array($item, (array)$check) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="chk_{{ Str::slug($item) }}">{{ $item }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Foto Bodi — Klik untuk tandai kerusakan</h6>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearPoints">Hapus Semua</button>
                        </div>
                        <div class="card-body">
                            @php
                                $carViews = ['depan' => 'Depan', 'belakang' => 'Belakang', 'kiri' => 'Kiri', 'kanan' => 'Kanan'];
                                $vmodel = $rental->vehicle->model ?? null;
                            @endphp
                            <div class="car-view-grid">
                                @foreach($carViews as $vk => $vl)
                                    @php $sidePhoto = $vmodel && !empty($vmodel->{'photo_' . $vk}) ? asset('storage/vehicle_model/' . $vmodel->{'photo_' . $vk}) : asset('assets/img/car-placeholder/' . $vk . '.jpg'); @endphp
                                    <figure class="car-view" data-view="{{ $vk }}">
                                        <figcaption>{{ $vl }}</figcaption>
                                        <div class="car-view-frame" data-view="{{ $vk }}" data-view-label="{{ $vl }}">
                                            <img src="{{ $sidePhoto }}" alt="Sisi {{ $vl }}" draggable="false"
                                                onerror="this.style.display='none'; this.closest('.car-view-frame').classList.add('img-missing');">
                                        </div>
                                    </figure>
                                @endforeach
                            </div>
                            <small class="text-muted">Klik pada foto untuk menandai titik goresan/penyok pada sisi terkait. Klik titik merah untuk menghapus (dengan konfirmasi).</small>
                            <input type="hidden" name="body_damage_points" id="bodyDamagePoints" value="{{ old('body_damage_points', !empty($seed->body_damage_points) ? json_encode($seed->body_damage_points) : '') }}">
                            <div id="pointsList" class="mt-2"></div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <label class="form-label">Catatan Umum</label>
                            <textarea name="notes" rows="3" class="form-control" placeholder="Catatan tambahan...">{{ old('notes', $inspection->notes ?? '') }}</textarea>
                            <div class="text-end mt-3">
                                <a href="{{ route('rental.show', $rental->rental_id) }}" class="btn btn-outline-secondary">Batal</a>
                                <button type="submit" class="btn btn-primary">Simpan Inspeksi</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('customjs')
<script>
    let points = [];
    try { points = JSON.parse($('#bodyDamagePoints').val() || '[]'); } catch(e) { points = []; }
    if (!Array.isArray(points)) points = [];

    const esc = (s) => $('<div>').text(s ?? '').html();
    const VIEW_LABEL = { depan: 'Depan', belakang: 'Belakang', kiri: 'Kiri', kanan: 'Kanan' };

    function confirmRemove(i) {
        const p = points[i];
        if (!p) return;
        const detail = p.note ? p.note : (p.x.toFixed(0) + '%,' + p.y.toFixed(0) + '%');
        Swal.fire({
            icon: 'question',
            title: 'Hapus titik ke-' + (i + 1) + '?',
            html: '<div class="text-start small">Sisi <strong>' + esc(VIEW_LABEL[p.view] || p.view) + '</strong><br>Keterangan: ' + esc(detail) + '</div>',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#d33',
            reverseButtons: true,
        }).then(function (r) {
            if (r.isConfirmed) { points.splice(i, 1); sync(); }
        });
    }

    function renderPoints() {
        $('.car-view-frame .damage-point').remove();
        $('#pointsList').empty();

        points.forEach((p, i) => {
            const frame = $('.car-view-frame[data-view="' + p.view + '"]');
            if (!frame.length) return;
            const el = $(`<div class="damage-point" data-idx="${i+1}" title="Klik untuk hapus" style="left:${p.x}%;top:${p.y}%"></div>`);
            el.on('click', function(e) { e.stopPropagation(); confirmRemove(i); });
            frame.append(el);
        });

        const groups = {};
        points.forEach((p, i) => { (groups[p.view] = groups[p.view] || []).push(i); });
        Object.keys(groups).forEach(function(v) {
            const badges = groups[v].map(function(i) {
                const p = points[i];
                return `<span class="badge bg-danger me-1 mb-1">${esc((i + 1) + '. ' + (p.note || (p.x.toFixed(0) + ',' + p.y.toFixed(0))))} <a href="#" data-i="${i}" class="text-white ms-1 rm-point">x</a></span>`;
            }).join('');
            $('#pointsList').append(`<div class="mb-1"><span class="small text-muted me-1">${esc(VIEW_LABEL[v] || v)}:</span>${badges}</div>`);
        });

        $('#pointsList .rm-point').on('click', function(e) { e.preventDefault(); confirmRemove($(this).data('i')); });
    }

    function sync() {
        $('#bodyDamagePoints').val(JSON.stringify(points));
        renderPoints();
    }

    function pointFromEvent(e, frame) {
        const rect = frame.getBoundingClientRect();
        const cx = e.clientX ?? (e.touches && e.touches[0] ? e.touches[0].clientX : 0);
        const cy = e.clientY ?? (e.touches && e.touches[0] ? e.touches[0].clientY : 0);
        return {
            x: Math.min(100, Math.max(0, (cx - rect.left) / rect.width * 100)),
            y: Math.min(100, Math.max(0, (cy - rect.top) / rect.height * 100)),
        };
    }

    function askAndAdd(e, frame) {
        e.preventDefault();
        const view = frame.getAttribute('data-view');
        const pos = pointFromEvent(e, frame);
        const x = parseFloat(pos.x.toFixed(1));
        const y = parseFloat(pos.y.toFixed(1));
        Swal.fire({
            icon: 'question',
            title: 'Titik kerusakan — ' + (VIEW_LABEL[view] || view),
            html: 'Posisi <strong>' + x + '%, ' + y + '%</strong>.<br><small class="text-muted">Isi keterangan (opsional) lalu Tambah, atau Batal untuk tidak menambah titik.</small>',
            input: 'text',
            inputPlaceholder: 'Keterangan kerusakan (cth: baret pintu, penyok bumper)',
            showCancelButton: true,
            confirmButtonText: 'Tambah',
            cancelButtonText: 'Batal',
            reverseButtons: true,
            allowOutsideClick: false,
        }).then(function (r) {
            if (!r.isConfirmed) return;
            points.push({ view: view, x: x, y: y, note: (r.value || '').trim() });
            sync();
        });
    }

    $('.car-view-frame').each(function() {
        const frame = this;
        $(frame).on('click', function(e) { askAndAdd(e, frame); });
        $(frame).on('touchend', function(e) {
            if (e.cancelable) e.preventDefault();
            askAndAdd(e.originalEvent || e, frame);
        });
    });

    $('#clearPoints').on('click', function() {
        if (!points.length) return;
        Swal.fire({
            icon: 'warning',
            title: 'Hapus semua titik?',
            text: 'Semua ' + points.length + ' tanda kerusakan akan dihapus.',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus semua',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#d33',
            reverseButtons: true,
        }).then(function(r) { if (r.isConfirmed) { points = []; sync(); } });
    });
    renderPoints();
</script>
@endsection

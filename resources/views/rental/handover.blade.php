@extends('layouts.header')

@section('customcss')
<style>
    .car-diagram {
        position: relative;
        width: 100%;
        max-width: 500px;
        height: 220px;
        margin: 0 auto;
        background: #f8f9fa;
        border: 2px solid #d9dee3;
        border-radius: 12px;
        overflow: hidden;
        cursor: crosshair;
    }
    .car-diagram .car-body {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 70%;
        height: 55%;
        border: 2px solid #3b4055;
        border-radius: 18px;
        background: #fff;
    }
    .car-diagram .wheel { position: absolute; width: 14%; height: 22%; background: #3b4055; border-radius: 4px; }
    .car-diagram .wheel.fl { top: 12%; left: 18%; } .car-diagram .wheel.fr { top: 12%; right: 18%; }
    .car-diagram .wheel.rl { bottom: 12%; left: 18%; } .car-diagram .wheel.rr { bottom: 12%; right: 18%; }
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
                                <input type="number" name="odometer" value="{{ old('odometer', $inspection->odometer ?? $rental->vehicle->mileage) }}" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Level BBM</label>
                                <select name="fuel_level" class="form-select">
                                    @foreach(['full'=>'Full','three_quarter'=>'3/4','half'=>'1/2','quarter'=>'1/4','empty'=>'Empty'] as $k=>$v)
                                        <option value="{{ $k }}" {{ old('fuel_level', $inspection->fuel_level ?? '') == $k ? 'selected' : '' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Catatan Eksterior</label>
                                <textarea name="exterior_notes" rows="2" class="form-control" placeholder="Baret, penyok, dll.">{{ old('exterior_notes', $inspection->exterior_notes ?? '') }}</textarea>
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Catatan Interior</label>
                                <textarea name="interior_notes" rows="2" class="form-control" placeholder="Kebersihan, bau, dll.">{{ old('interior_notes', $inspection->interior_notes ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-3">
                        <div class="card-header"><h6 class="mb-0">Kelengkapan</h6></div>
                        <div class="card-body">
                            @php $check = old('checklist', $inspection->checklist ?? []); @endphp
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
                            <h6 class="mb-0">Diagram Bodi — Klik untuk tandai kerusakan</h6>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearPoints">Hapus Semua</button>
                        </div>
                        <div class="card-body">
                            <div class="car-diagram" id="carDiagram">
                                <div class="car-body"></div>
                                <div class="wheel fl"></div><div class="wheel fr"></div><div class="wheel rl"></div><div class="wheel rr"></div>
                            </div>
                            <small class="text-muted">Klik pada diagram untuk menandai titik goresan/penyok. Klik titik untuk hapus.</small>
                            <input type="hidden" name="body_damage_points" id="bodyDamagePoints" value="{{ old('body_damage_points', isset($inspection->body_damage_points) ? json_encode($inspection->body_damage_points) : '') }}">
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
    function renderPoints() {
        $('#carDiagram .damage-point').remove();
        $('#pointsList').empty();
        points.forEach((p, i) => {
            const el = $(`<div class="damage-point" data-idx="${i+1}" style="left:${p.x}%;top:${p.y}%"></div>`);
            el.on('click', function(e){ e.stopPropagation(); points.splice(i,1); sync(); });
            $('#carDiagram').append(el);
            $('#pointsList').append(`<span class="badge bg-danger me-1">${i+1}. ${p.note || p.x.toFixed(0)+','+p.y.toFixed(0)} <a href="#" data-i="${i}" class="text-white ms-1 rm-point">x</a></span>`);
        });
        $('#pointsList .rm-point').on('click', function(e){ e.preventDefault(); points.splice($(this).data('i'),1); sync(); });
    }
    function sync() {
        $('#bodyDamagePoints').val(JSON.stringify(points));
        renderPoints();
    }
    $('#carDiagram').on('click', function(e){
        const rect = this.getBoundingClientRect();
        const x = ((e.clientX - rect.left) / rect.width * 100).toFixed(1);
        const y = ((e.clientY - rect.top) / rect.height * 100).toFixed(1);
        const note = prompt('Keterangan kerusakan di titik '+x+'%,'+y+'% (opsional):', '');
        if (note === null) return;
        points.push({x: parseFloat(x), y: parseFloat(y), note: note});
        sync();
    });
    $('#clearPoints').on('click', function(){ points=[]; sync(); });
    renderPoints();
</script>
@endsection

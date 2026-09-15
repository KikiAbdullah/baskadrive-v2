@php $item = $item ?? null; @endphp
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Plat Nomor</label>
    <div class="col-lg-9">
        <input type="text" name="license_plate" value="{{ $item->license_plate ?? old('license_plate') }}"
            class="{{ in_array('license_plate', $errors->keys()) ? 'form-control is-invalid' : 'form-control' }}"
            placeholder="B 1234 ABC" required />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Model</label>
    <div class="col-lg-9">
        <select name="model_id" class="select" required>
            <option value="">Pilih Model</option>
            @foreach ($data['list_model'] as $key => $value)
                <option value="{{ $key }}" {{ old('model_id', $item->model_id ?? '') == $key ? 'selected' : '' }}>
                    {{ $value }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Lokasi Saat Ini</label>
    <div class="col-lg-9">
        <select name="location_id" class="select">
            <option value="">Pilih Lokasi</option>
            @foreach ($data['list_location'] as $key => $value)
                <option value="{{ $key }}" {{ old('location_id', $item->location_id ?? '') == $key ? 'selected' : '' }}>
                    {{ $value }}
                </option>
            @endforeach
        </select>
        <small class="text-muted">Pindah lokasi akan tercatat sebagai riwayat mutasi.</small>
    </div>
</div>
<div class="row mb-3 mutation-notes" style="display:none;">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Catatan Mutasi</label>
    <div class="col-lg-9">
        <input type="text" name="mutation_notes" class="form-control" placeholder="Alasan pindah lokasi (opsional)" />
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sel = document.querySelector('select[name="location_id"]');
    const notesRow = document.querySelector('.mutation-notes');
    if (sel && notesRow) {
        const initial = sel.value;
        sel.addEventListener('change', function() {
            notesRow.style.display = this.value && this.value !== initial ? '' : 'none';
        });
    }
});
</script>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">VIN</label>
    <div class="col-lg-9">
        <input type="text" name="vin" value="{{ $item->vin ?? old('vin') }}" class="form-control"
            placeholder="Nomor VIN (opsional)" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Warna</label>
    <div class="col-lg-9">
        <input type="text" name="color" value="{{ $item->color ?? old('color') }}" class="form-control"
            placeholder="Putih, Hitam, Silver..." />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Tahun</label>
    <div class="col-lg-9">
        <input type="number" name="year" value="{{ $item->year ?? old('year') }}" class="form-control"
            placeholder="2024" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Kilometer</label>
    <div class="col-lg-9">
        <input type="number" name="mileage" value="{{ $item->mileage ?? old('mileage') }}" class="form-control"
            placeholder="0" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Status</label>
    <div class="col-lg-9">
        <select name="status" class="select">
            <option value="available" {{ old('status', $item->status ?? '') == 'available' ? 'selected' : '' }}>Available</option>
            <option value="rented" {{ old('status', $item->status ?? '') == 'rented' ? 'selected' : '' }}>Rented</option>
            <option value="maintenance" {{ old('status', $item->status ?? '') == 'maintenance' ? 'selected' : '' }}>Maintenance</option>
            <option value="reserved" {{ old('status', $item->status ?? '') == 'reserved' ? 'selected' : '' }}>Reserved</option>
            <option value="retired" {{ old('status', $item->status ?? '') == 'retired' ? 'selected' : '' }}>Retired</option>
        </select>
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Foto Unit</label>
    <div class="col-lg-9">
        @php $cur = $item->photo_url ?? null; @endphp
        @if(!empty($cur))
            <div class="mb-2"><img src="{{ str_starts_with($cur, 'http') ? $cur : asset('storage/vehicle/'.$cur) }}" alt="Foto" style="max-width:120px;border-radius:6px;border:1px solid #e5e5e8;"></div>
        @endif
        <input type="file" name="photo" accept="image/*" class="form-control" />
        <small class="text-muted">JPG/PNG/WebP maks 2MB — tersimpan di storage/vehicle/. Kosongkan untuk mempertahankan foto saat ini.</small>
    </div>
</div>

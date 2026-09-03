@php $item = $item ?? null; @endphp
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Nama Tipe</label>
    <div class="col-lg-9">
        <input type="text" name="type_name" value="{{ $item->type_name ?? old('type_name') }}"
            class="{{ in_array('type_name', $errors->keys()) ? 'form-control is-invalid' : 'form-control' }}"
            placeholder="Servis Rutin, Ganti Oli..." required />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Interval KM</label>
    <div class="col-lg-9">
        <input type="number" name="interval_km" value="{{ $item->interval_km ?? old('interval_km') }}"
            class="form-control" placeholder="10000" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Interval Bulan</label>
    <div class="col-lg-9">
        <input type="number" name="interval_months" value="{{ $item->interval_months ?? old('interval_months') }}"
            class="form-control" placeholder="6" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Deskripsi</label>
    <div class="col-lg-9">
        <textarea name="description" rows="2" class="form-control" placeholder="Deskripsi (opsional)">{{ $item->description ?? old('description') }}</textarea>
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Status</label>
    <div class="col-lg-9">
        <select name="is_active" class="select">
            <option value="1" {{ old('is_active', $item->is_active ?? '') == '1' ? 'selected' : '' }}>Aktif</option>
            <option value="0" {{ old('is_active', $item->is_active ?? '') == '0' ? 'selected' : '' }}>Nonaktif</option>
        </select>
    </div>
</div>
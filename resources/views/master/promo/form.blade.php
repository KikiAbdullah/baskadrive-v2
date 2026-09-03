@php $item = $item ?? null; @endphp
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Kode Promo</label>
    <div class="col-lg-9">
        <input type="text" name="promo_code" value="{{ $item->promo_code ?? old('promo_code') }}"
            class="{{ in_array('promo_code', $errors->keys()) ? 'form-control is-invalid' : 'form-control' }}"
            placeholder="HEMAT50" required />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Deskripsi</label>
    <div class="col-lg-9">
        <textarea name="description" rows="2" class="form-control" placeholder="Deskripsi promo">{{ $item->description ?? old('description') }}</textarea>
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Tipe Diskon</label>
    <div class="col-lg-9">
        <select name="discount_type" class="select">
            <option value="percentage" {{ old('discount_type', $item->discount_type ?? '') == 'percentage' ? 'selected' : '' }}>Persentase (%)</option>
            <option value="nominal" {{ old('discount_type', $item->discount_type ?? '') == 'nominal' ? 'selected' : '' }}>Nominal (Rp)</option>
        </select>
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Nilai Diskon</label>
    <div class="col-lg-9">
        <input type="number" step="0.01" name="discount_value" value="{{ $item->discount_value ?? old('discount_value') }}"
            class="form-control" placeholder="10 / 50000" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Min. Hari Sewa</label>
    <div class="col-lg-9">
        <input type="number" name="min_rental_days" value="{{ $item->min_rental_days ?? old('min_rental_days') }}"
            class="form-control" placeholder="3" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Berlaku Dari</label>
    <div class="col-lg-9">
        <input type="date" name="valid_from" value="{{ $item->valid_from ?? old('valid_from') }}" class="form-control" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Berlaku Sampai</label>
    <div class="col-lg-9">
        <input type="date" name="valid_to" value="{{ $item->valid_to ?? old('valid_to') }}" class="form-control" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Maks. Penggunaan</label>
    <div class="col-lg-9">
        <input type="number" name="max_usage" value="{{ $item->max_usage ?? old('max_usage') }}" class="form-control"
            placeholder="100" />
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
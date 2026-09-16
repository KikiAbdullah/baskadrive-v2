@php $item = $item ?? null; @endphp
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Nama Depan</label>
    <div class="col-lg-9">
        <input type="text" name="first_name" value="{{ $item->first_name ?? old('first_name') }}"
            class="{{ in_array('first_name', $errors->keys()) ? 'form-control is-invalid' : 'form-control' }}"
            placeholder="Nama depan" required />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Nama Belakang</label>
    <div class="col-lg-9">
        <input type="text" name="last_name" value="{{ $item->last_name ?? old('last_name') }}"
            class="{{ in_array('last_name', $errors->keys()) ? 'form-control is-invalid' : 'form-control' }}"
            placeholder="Nama belakang" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">No. SIM</label>
    <div class="col-lg-9">
        <input type="text" name="license_number" value="{{ $item->license_number ?? old('license_number') }}"
            class="{{ in_array('license_number', $errors->keys()) ? 'form-control is-invalid' : 'form-control' }}"
            placeholder="Nomor SIM" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Masa Berlaku SIM</label>
    <div class="col-lg-9">
        <input type="text" name="license_expiry" value="{{ $item->license_expiry?->format('Y-m-d') ?? old('license_expiry') }}"
            class="form-control flatpickr-date" autocomplete="off" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Telepon</label>
    <div class="col-lg-9">
        <input type="text" name="phone" value="{{ $item->phone ?? old('phone') }}"
            class="{{ in_array('phone', $errors->keys()) ? 'form-control is-invalid' : 'form-control' }}"
            placeholder="08xxxxxxxxxx" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Catatan</label>
    <div class="col-lg-9">
        <textarea name="notes" rows="2" class="form-control" placeholder="Catatan (opsional)">{{ $item->notes ?? old('notes') }}</textarea>
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
@php $item = $item ?? null; @endphp
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Tipe Pelanggan</label>
    <div class="col-lg-9">
        <select name="customer_type" class="select" required>
            <option value="individual" {{ old('customer_type', $item->customer_type ?? '') == 'individual' ? 'selected' : '' }}>Individu</option>
            <option value="corporate" {{ old('customer_type', $item->customer_type ?? '') == 'corporate' ? 'selected' : '' }}>Korporasi</option>
        </select>
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Nama Depan</label>
    <div class="col-lg-9">
        <input type="text" name="first_name" value="{{ $item->first_name ?? old('first_name') }}"
            class="{{ in_array('first_name', $errors->keys()) ? 'form-control is-invalid' : 'form-control' }}"
            placeholder="Nama depan" />
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
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Nama Perusahaan</label>
    <div class="col-lg-9">
        <input type="text" name="company_name" value="{{ $item->company_name ?? old('company_name') }}"
            class="form-control" placeholder="Nama perusahaan (korporasi)" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Email</label>
    <div class="col-lg-9">
        <input type="email" name="email" value="{{ $item->email ?? old('email') }}"
            class="{{ in_array('email', $errors->keys()) ? 'form-control is-invalid' : 'form-control' }}"
            placeholder="email@contoh.com" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Telepon</label>
    <div class="col-lg-9">
        <input type="text" name="phone" value="{{ $item->phone ?? old('phone') }}"
            class="{{ in_array('phone', $errors->keys()) ? 'form-control is-invalid' : 'form-control' }}"
            placeholder="08xxxxxxxxxx" required />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Alamat</label>
    <div class="col-lg-9">
        <textarea name="address" rows="2" class="form-control"
            placeholder="Alamat">{{ $item->address ?? old('address') }}</textarea>
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Kota</label>
    <div class="col-lg-9">
        <input type="text" name="city" value="{{ $item->city ?? old('city') }}" class="form-control"
            placeholder="Kota" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Provinsi</label>
    <div class="col-lg-9">
        <input type="text" name="province" value="{{ $item->province ?? old('province') }}" class="form-control"
            placeholder="Provinsi" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Kode Pos</label>
    <div class="col-lg-9">
        <input type="text" name="postal_code" value="{{ $item->postal_code ?? old('postal_code') }}"
            class="form-control" placeholder="Kode pos" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">No. SIM</label>
    <div class="col-lg-9">
        <input type="text" name="driver_license_number"
            value="{{ $item->driver_license_number ?? old('driver_license_number') }}" class="form-control"
            placeholder="Nomor SIM (opsional)" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">NIK (KTP)</label>
    <div class="col-lg-9">
        <input type="text" name="id_card_number" value="{{ $item->id_card_number ?? old('id_card_number') }}"
            class="form-control" placeholder="16 digit NIK" pattern="\d{16}" maxlength="16" />
        <small class="text-muted">Harus 16 digit angka (opsional).</small>
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Blacklist</label>
    <div class="col-lg-9">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_blacklisted" value="1" id="is_blacklisted"
                {{ old('is_blacklisted', $item->is_blacklisted ?? false) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_blacklisted">Pelanggan bermasalah (blacklist)</label>
        </div>
        <textarea name="blacklist_reason" rows="2" class="form-control mt-2" placeholder="Alasan blacklist (wajib jika dicentang)">{{ $item->blacklist_reason ?? old('blacklist_reason') }}</textarea>
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Catatan</label>
    <div class="col-lg-9">
        <textarea name="notes" rows="2" class="form-control"
            placeholder="Catatan (opsional)">{{ $item->notes ?? old('notes') }}</textarea>
    </div>
</div>
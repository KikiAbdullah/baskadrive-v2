@php $item = $item ?? null; @endphp
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Nama Lokasi</label>
    <div class="col-lg-9">
        <input type="text" name="location_name" value="{{ $item->location_name ?? old('location_name') }}"
            class="{{ in_array('location_name', $errors->keys()) ? 'form-control is-invalid' : 'form-control' }}"
            placeholder="Kantor Pusat, Cabang..." required />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Alamat</label>
    <div class="col-lg-9">
        <textarea name="address" rows="2" class="form-control" placeholder="Alamat">{{ $item->address ?? old('address') }}</textarea>
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Kota</label>
    <div class="col-lg-9">
        <input type="text" name="city" value="{{ $item->city ?? old('city') }}" class="form-control" placeholder="Kota" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Provinsi</label>
    <div class="col-lg-9">
        <input type="text" name="province" value="{{ $item->province ?? old('province') }}" class="form-control" placeholder="Provinsi" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Telepon</label>
    <div class="col-lg-9">
        <input type="text" name="contact_phone" value="{{ $item->contact_phone ?? old('contact_phone') }}"
            class="form-control" placeholder="08xxxxxxxxxx" />
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
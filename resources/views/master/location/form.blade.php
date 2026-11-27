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
        <textarea name="address" id="locationAddress" rows="2" class="form-control" placeholder="Alamat">{{ $item->address ?? old('address') }}</textarea>
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Kota</label>
    <div class="col-lg-9">
        <input type="text" name="city" id="locationCity" value="{{ $item->city ?? old('city') }}" class="form-control" placeholder="Kota" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Provinsi</label>
    <div class="col-lg-9">
        <input type="text" name="province" id="locationProvince" value="{{ $item->province ?? old('province') }}" class="form-control" placeholder="Provinsi" />
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
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Jam Operasional</label>
    <div class="col-lg-9">
        <input type="text" name="opening_hours" value="{{ $item->opening_hours ?? old('opening_hours') }}"
            class="form-control" placeholder="08:00-17:00 atau 24 Jam" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Koordinat</label>
    <div class="col-lg-9">
        <div class="row g-2">
            <div class="col-sm-6">
                <input type="number" step="any" id="latitude" name="latitude"
                    value="{{ old('latitude', $item->latitude ?? '') }}"
                    class="form-control {{ $errors->has('latitude') ? 'is-invalid' : '' }}"
                    placeholder="-6.2000000" />
                <small class="text-muted">Latitude</small>
            </div>
            <div class="col-sm-6">
                <input type="number" step="any" id="longitude" name="longitude"
                    value="{{ old('longitude', $item->longitude ?? '') }}"
                    class="form-control {{ $errors->has('longitude') ? 'is-invalid' : '' }}"
                    placeholder="106.8000000" />
                <small class="text-muted">Longitude</small>
            </div>
        </div>
        <div id="locationMap" class="location-map border rounded mt-2"></div>
        <div class="d-flex align-items-center flex-wrap gap-2 mt-2">
            <button type="button" class="btn btn-sm btn-outline-primary" id="btnReverseGeocode">
                <i class="ri-map-pin-2-line me-1"></i> Ambil alamat dari peta
            </button>
            <small class="text-muted" id="geoStatus">Klik peta / geser pin — koordinat &amp; alamat terisi otomatis.</small>
        </div>
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
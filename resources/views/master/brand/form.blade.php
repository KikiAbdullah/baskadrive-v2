@php $item = $item ?? null; @endphp
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Nama Brand</label>
    <div class="col-lg-9">
        <input type="text" name="brand_name" value="{{ $item->brand_name ?? old('brand_name') }}"
            class="{{ in_array('brand_name', $errors->keys()) ? 'form-control is-invalid' : 'form-control' }}"
            placeholder="Toyota, Honda, Suzuki..." required />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">URL Logo</label>
    <div class="col-lg-9">
        <input type="text" name="logo_url" value="{{ $item->logo_url ?? old('logo_url') }}" class="form-control"
            placeholder="https://example.com/logo.png (opsional)" />
    </div>
</div>
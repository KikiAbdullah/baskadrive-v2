@php $item = $item ?? null; @endphp
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Brand</label>
    <div class="col-lg-9">
        <select name="brand_id" class="select" required>
            <option value="">Pilih Brand</option>
            @foreach ($data['list_brand'] as $key => $value)
                <option value="{{ $key }}" {{ old('brand_id', $item->brand_id ?? '') == $key ? 'selected' : '' }}>
                    {{ $value }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Nama Model</label>
    <div class="col-lg-9">
        <input type="text" name="model_name" value="{{ $item->model_name ?? old('model_name') }}"
            class="{{ in_array('model_name', $errors->keys()) ? 'form-control is-invalid' : 'form-control' }}"
            placeholder="Avanza, Innova, Xenia..." required />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Kategori</label>
    <div class="col-lg-9">
        <select name="category" class="select">
            <option value="">Pilih Kategori</option>
            @foreach (['sedan', 'suv', 'mpv', 'hatchback', 'pickup', 'van', 'luxury'] as $cat)
                <option value="{{ $cat }}" {{ old('category', $item->category ?? '') == $cat ? 'selected' : '' }}>
                    {{ ucfirst($cat) }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Tipe Bahan Bakar</label>
    <div class="col-lg-9">
        <select name="fuel_type" class="select">
            <option value="">Pilih Bahan Bakar</option>
            @foreach (['bensin', 'diesel', 'listrik', 'hybrid'] as $fuel)
                <option value="{{ $fuel }}" {{ old('fuel_type', $item->fuel_type ?? '') == $fuel ? 'selected' : '' }}>
                    {{ ucfirst($fuel) }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Transmisi</label>
    <div class="col-lg-9">
        <select name="transmission" class="select">
            <option value="">Pilih Transmisi</option>
            <option value="manual" {{ old('transmission', $item->transmission ?? '') == 'manual' ? 'selected' : '' }}>Manual</option>
            <option value="automatic" {{ old('transmission', $item->transmission ?? '') == 'automatic' ? 'selected' : '' }}>Automatic</option>
            <option value="cvt" {{ old('transmission', $item->transmission ?? '') == 'cvt' ? 'selected' : '' }}>CVT</option>
        </select>
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Kapasitas Kursi</label>
    <div class="col-lg-9">
        <input type="number" name="seat_capacity" value="{{ $item->seat_capacity ?? old('seat_capacity') }}"
            class="form-control" placeholder="7" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Harga Sewa/Hari</label>
    <div class="col-lg-9">
        <input type="number" step="0.01" name="base_price_per_day"
            value="{{ $item->base_price_per_day ?? old('base_price_per_day') }}" class="form-control"
            placeholder="350000" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Harga Sewa/KM</label>
    <div class="col-lg-9">
        <input type="number" step="0.01" name="base_price_per_km"
            value="{{ $item->base_price_per_km ?? old('base_price_per_km') }}" class="form-control"
            placeholder="2500" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Asuransi</label>
    <div class="col-lg-9">
        <input type="number" step="0.01" name="insurance_rate"
            value="{{ $item->insurance_rate ?? old('insurance_rate') }}" class="form-control" placeholder="0" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Deposit</label>
    <div class="col-lg-9">
        <input type="number" step="0.01" name="deposit_amount"
            value="{{ $item->deposit_amount ?? old('deposit_amount') }}" class="form-control" placeholder="500000" />
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
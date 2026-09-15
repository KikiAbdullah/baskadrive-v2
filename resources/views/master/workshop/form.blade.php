@php $item = $item ?? null; @endphp
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Nama Bengkel</label>
    <div class="col-lg-9">
        <input type="text" name="name" value="{{ $item->name ?? old('name') }}"
            class="{{ in_array('name', $errors->keys()) ? 'form-control is-invalid' : 'form-control' }}"
            placeholder="Nama bengkel" required />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Alamat</label>
    <div class="col-lg-9">
        <textarea name="address" rows="2" class="form-control" placeholder="Alamat">{{ $item->address ?? old('address') }}</textarea>
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Telepon</label>
    <div class="col-lg-9">
        <input type="text" name="phone" value="{{ $item->phone ?? old('phone') }}" class="form-control"
            placeholder="08xxxxxxxxxx" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Kontak Person</label>
    <div class="col-lg-9">
        <input type="text" name="contact_person" value="{{ $item->contact_person ?? old('contact_person') }}"
            class="form-control" placeholder="Nama kontak person" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Rating</label>
    <div class="col-lg-9">
        <select name="rating" class="select">
            <option value="">Pilih Rating</option>
            @for($i=5; $i>=1; $i--)
                <option value="{{ $i }}" {{ old('rating', $item->rating ?? '') == $i ? 'selected' : '' }}>{{ $i }} ★</option>
                <option value="{{ $i-0.5 }}" {{ old('rating', $item->rating ?? '') == $i-0.5 ? 'selected' : '' }}>{{ $i-0.5 }} ★</option>
            @endfor
        </select>
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Spesialisasi</label>
    <div class="col-lg-9">
        <select name="specialization" class="select">
            <option value="">Pilih Spesialisasi</option>
            @foreach(['Mesin', 'Body Repair', 'Cat', 'AC', 'Kaki-kaki', 'Kelistrikan', 'Umum'] as $spec)
                <option value="{{ $spec }}" {{ old('specialization', $item->specialization ?? '') == $spec ? 'selected' : '' }}>{{ $spec }}</option>
            @endforeach
        </select>
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
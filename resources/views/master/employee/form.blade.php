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
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Email</label>
    <div class="col-lg-9">
        <input type="email" name="email" value="{{ $item->email ?? old('email') }}" class="form-control"
            placeholder="email@perusahaan.com" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Telepon</label>
    <div class="col-lg-9">
        <input type="text" name="phone" value="{{ $item->phone ?? old('phone') }}" class="form-control"
            placeholder="08xxxxxxxxxx" required />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Posisi</label>
    <div class="col-lg-9">
        <input type="text" name="position" value="{{ $item->position ?? old('position') }}" class="form-control"
            placeholder="Staff, Supervisor, Manager..." />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Role</label>
    <div class="col-lg-9">
        <input type="text" name="role" value="{{ $item->role ?? old('role') }}" class="form-control"
            placeholder="admin, staff, driver..." />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Username</label>
    <div class="col-lg-9">
        <input type="text" name="username" value="{{ $item->username ?? old('username') }}"
            class="{{ in_array('username', $errors->keys()) ? 'form-control is-invalid' : 'form-control' }}"
            placeholder="Username" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Password</label>
    <div class="col-lg-9">
        <input type="password" name="password_hash" class="form-control"
            placeholder="{{ empty($item) ? 'Password' : 'Kosongkan jika tidak diubah' }}" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Tgl. Masuk</label>
    <div class="col-lg-9">
        <input type="date" name="hire_date" value="{{ $item->hire_date ?? old('hire_date') }}" class="form-control" />
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
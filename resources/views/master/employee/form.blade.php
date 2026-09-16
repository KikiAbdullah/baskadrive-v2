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
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Email <span class="text-danger">*</span></label>
    <div class="col-lg-9">
        <input type="email" name="email" value="{{ $item->email ?? old('email') }}" class="form-control"
            placeholder="email@perusahaan.com" required />
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
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Posisi <span class="text-danger">*</span></label>
    <div class="col-lg-9">
        <input type="text" name="position" value="{{ $item->position ?? old('position') }}" class="form-control"
            placeholder="Staff, Supervisor, Manager..." required />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Role <span class="text-danger">*</span></label>
    <div class="col-lg-9">
        <select name="role" class="select" required>
            @foreach (['admin' => 'Admin', 'manager' => 'Manager', 'cashier' => 'Cashier', 'mechanic' => 'Mekanik', 'accountant' => 'Akuntan', 'director' => 'Direktur'] as $val => $lbl)
                <option value="{{ $val }}" {{ old('role', $item->role ?? '') == $val ? 'selected' : '' }}>{{ $lbl }}</option>
            @endforeach
        </select>
        <div class="form-text">Sesuai pilihan role sistem (enum), bukan teks bebas.</div>
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Username <span class="text-danger">*</span></label>
    <div class="col-lg-9">
        <input type="text" name="username" value="{{ $item->username ?? old('username') }}"
            class="{{ in_array('username', $errors->keys()) ? 'form-control is-invalid' : 'form-control' }}"
            placeholder="Username" minlength="4" required />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Password @if(empty($item))<span class="text-danger">*</span>@endif</label>
    <div class="col-lg-9">
        <input type="password" name="password_hash" class="form-control" minlength="8" autocomplete="new-password"
            @if(empty($item)) required @endif
            placeholder="{{ empty($item) ? 'Minimal 8 karakter' : 'Kosongkan jika tidak diubah' }}" />
        @if(empty($item))
            <div class="form-text">Wajib diisi, minimal 8 karakter (audit M-05).</div>
        @endif
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Tgl. Masuk <span class="text-danger">*</span></label>
    <div class="col-lg-9">
        <input type="text" name="hire_date" value="{{ $item->hire_date?->format('Y-m-d') ?? old('hire_date') }}" class="form-control flatpickr-date" autocomplete="off" required />
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
@if(empty($item))
<div class="row mb-3">
    <div class="col-lg-9 offset-lg-3">
        <div class="card bg-light p-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="create_account" value="1" id="create_account">
                <label class="form-check-label fw-semibold" for="create_account">Buatkan Akun Login otomatis</label>
            </div>
            <small class="text-muted">Jika dicentang, akun login akan dibuat dengan username & email karyawan.</small>
            <div class="mt-2" id="roleSelect" style="display:none;">
                <label class="form-label">Role Akun</label>
                <select name="user_role" class="select">
                    <option value="staff">Staff</option>
                    <option value="admin">Admin</option>
                    <option value="manager">Manager</option>
                </select>
            </div>
        </div>
    </div>
</div>
<script>document.getElementById('create_account')?.addEventListener('change', e => { document.getElementById('roleSelect').style.display = e.target.checked ? '' : 'none'; });</script>
@endif
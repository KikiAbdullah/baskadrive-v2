@php $item = $item ?? null; @endphp
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Username</label>
    <div class="col-lg-9">
        @if (empty($item))
            <input type="text" name="username" value="{{ old('username') }}"
                class="{{ in_array('username', $errors->keys()) ? 'form-control is-invalid' : 'form-control' }}"
                placeholder="Username" />
        @else
            <input type="text" name="username" value="{{ $item->username }}" class="form-control"
                placeholder="Username" disabled />
        @endif

    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Name</label>
    <div class="col-lg-9">
        <input type="text" name="name" value="{{ $item->name ?? old('name') }}"
            class="{{ in_array('name', $errors->keys()) ? 'form-control is-invalid' : 'form-control' }}"
            placeholder="Name" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Roles</label>
    <div class="col-lg-9">
        @if (empty($item))
            <select name="role" class="select">
                <option value="">Select Role</option>
                @foreach ($data['list_role'] as $key => $value)
                    <option value="{{ $key }}">{{ $value }}</option>
                @endforeach
            </select>
        @else
            <select name="role" class="select">
                <option value="">Select Role</option>
                @foreach ($data['list_role'] as $key => $value)
                    <option value="{{ $key }}" {{ $item->roles->first()->id ?? null == $key ? 'selected' : '' }}>
                        {{ $value }}
                    </option>
                @endforeach
            </select>
        @endif
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Email</label>
    <div class="col-lg-9">
        <input type="text" name="email" value="{{ $item->email ?? old('email') }}" class="form-control"
            placeholder="Email" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">WhatsApp</label>
    <div class="col-lg-9">
        <input type="text" name="nowa" value="{{ $item->nowa ?? old('nowa') }}" class="form-control"
            placeholder="WhatsApp Number" />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Password</label>
    <div class="col-lg-9">
        <input type="password" name="password"
            class="{{ in_array('password', $errors->keys()) ? 'form-control is-invalid' : 'form-control' }}"
            placeholder="Password" />
    </div>
</div>
@if (!empty($item))
    <div class="row mb-3">
        <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Status</label>
        <div class="col-lg-9">
            <select name="deleted_at_baru" class="select">
                <option value="">Status</option>
                <option value="1" {{ $item->deleted_at_baru == '1' ? 'selected' : '' }}>Enabled</option>
                <option value="0" {{ $item->deleted_at_baru == '0' ? 'selected' : '' }}>Disabled</option>
            </select>
        </div>
    </div>
@endif

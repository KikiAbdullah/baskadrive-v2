@php $item = $item ?? null; @endphp
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Kode Akun</label>
    <div class="col-lg-9">
        <input type="text" name="account_code" value="{{ $item->account_code ?? old('account_code') }}"
            class="{{ in_array('account_code', $errors->keys()) ? 'form-control is-invalid' : 'form-control' }}"
            placeholder="1-1100" required />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Nama Akun</label>
    <div class="col-lg-9">
        <input type="text" name="account_name" value="{{ $item->account_name ?? old('account_name') }}"
            class="{{ in_array('account_name', $errors->keys()) ? 'form-control is-invalid' : 'form-control' }}"
            placeholder="Kas" required />
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Tipe Akun</label>
    <div class="col-lg-9">
        <select name="account_type" class="select">
            <option value="asset" {{ old('account_type', $item->account_type ?? '') == 'asset' ? 'selected' : '' }}>Aset</option>
            <option value="liability" {{ old('account_type', $item->account_type ?? '') == 'liability' ? 'selected' : '' }}>Kewajiban</option>
            <option value="equity" {{ old('account_type', $item->account_type ?? '') == 'equity' ? 'selected' : '' }}>Ekuitas</option>
            <option value="income" {{ old('account_type', $item->account_type ?? '') == 'income' ? 'selected' : '' }}>Pendapatan</option>
            <option value="expense" {{ old('account_type', $item->account_type ?? '') == 'expense' ? 'selected' : '' }}>Beban</option>
        </select>
    </div>
</div>
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Akun Induk</label>
    <div class="col-lg-9">
        <select name="parent_id" class="select">
            <option value="">-</option>
            @foreach ($data['list_parent'] as $key => $value)
                <option value="{{ $key }}" {{ old('parent_id', $item->parent_id ?? '') == $key ? 'selected' : '' }}>
                    {{ $value }}
                </option>
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
<div class="row mb-3">
    <label class="col-lg-3 col-form-label text-lg-end d-none d-lg-block">Name</label>
    <div class="col-lg-9">
        <input type="text" name="name" value="{{ $item->name ?? old('name') }}"
            class="{{ in_array('name', $errors->keys()) ? 'form-control is-invalid' : 'form-control' }}"
            placeholder="Name" />
    </div>
</div>

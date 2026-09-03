@if (session('success'))
    <div class="alert alert-success border-0 alert-dismissible">
        <span class="fw-semibold">Success!</span>
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@elseif(session('error'))
    <div class="alert alert-danger border-0 alert-dismissible">
        <span class="fw-semibold">Error!</span>
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (count($errors) > 0)
    <div class="alert alert-danger border-0 alert-dismissible">
        <span class="fw-semibold">Error!</span>
        @foreach ($errors->all() as $error)
            {{ $error }}
        @endforeach
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

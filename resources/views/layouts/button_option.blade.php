@if (array_key_exists('vedit', $url))
    <a href="{{ route($url['vedit'], $id) }}" class="action-link-icon-text editBtn">
        <i class="ri-edit-line"></i>
        <span class="fw-semibold text-uppercase">VIEW / EDIT</span>
    </a>
@endif

@if (array_key_exists('edit', $url))
    <a href="{{ route($url['edit'], $id) }}" class="action-link-icon-text editBtn">
        <i class="ri-edit-line"></i>
        <span class="fw-semibold text-uppercase">EDIT</span>
    </a>
@endif

@if (array_key_exists('show', $url))
    <a href="{{ route($url['show'], $id) }}" class="action-link-icon-text btnShow">
        <i class="ri-search-line"></i>
        <span class="fw-semibold text-uppercase">SHOW</span>
    </a>
@endif

@if (array_key_exists('destroy', $url))
    <form method="POST" action="{{ route($url['destroy'], $id) }}" class="delete form-delete-inline">
        @csrf
        @method('DELETE')
    <a href="#" class="action-link-icon-text text-danger deleteBtn">
        <i class="ri-close-circle-line"></i>
        <span class="fw-semibold text-uppercase">DELETE</span>
    </a>
    </form>
@endif

@if (array_key_exists('barcode', $url))
    <a href="{{ route($url['barcode'], $id) }}" class="action-link-icon-text" target="_blank">
        <i class="ri-qr-code-line"></i>
        <span class="fw-semibold text-uppercase">BARCODE</span>
    </a>
@endif

@if (array_key_exists('history', $url))
    <a href="{{ route($url['history'], $id) }}" class="action-link-icon-text">
        <i class="ri-history-line"></i>
        <span class="fw-semibold text-uppercase">HISTORY</span>
    </a>
@endif

@if (array_key_exists('barcode_pdf', $url))
    <a href="{{ route($url['barcode_pdf'], $id) }}" class="action-link-icon-text" target="_blank">
        <i class="ri-printer-line"></i>
        <span class="fw-semibold text-uppercase">LABEL PDF</span>
    </a>
@endif

@if (array_key_exists('verify', $url) && auth()->user()->can('master_edit'))
    <form method="POST" action="{{ route($url['verify'], $id) }}" class="d-inline">
        @csrf
        @method('PUT')
        <a href="#" class="action-link-icon-text text-success" onclick="this.closest('form').submit(); return false;">
            <i class="ri-shield-check-line"></i>
            <span class="fw-semibold text-uppercase">VERIFIKASI</span>
        </a>
    </form>
@endif

@if (array_key_exists('rental_now', $url))
    <form method="POST" action="{{ route($url['rental_now'], $id) }}" class="d-inline">
        @csrf
        <a href="#" class="action-link-icon-text text-primary" onclick="this.closest('form').submit(); return false;">
            <i class="ri-car-line"></i>
            <span class="fw-semibold text-uppercase">SEWA SEKARANG</span>
        </a>
    </form>
@endif

@if (array_key_exists('toggle', $url) && auth()->user()->can('master_edit'))
    <form method="POST" action="{{ route($url['toggle'], $id) }}" class="d-inline">
        @csrf
        @method('PUT')
        <a href="#" class="action-link-icon-text text-warning" onclick="this.closest('form').submit(); return false;">
            <i class="ri-toggle-line"></i>
            <span class="fw-semibold text-uppercase">TOGGLE AKTIF</span>
        </a>
    </form>
@endif

@if (array_key_exists('toggle_active', $url) && auth()->user()->can('master_edit'))
    <form method="POST" action="{{ route($url['toggle_active'], $id) }}" class="d-inline">
        @csrf
        @method('PUT')
        <a href="#" class="action-link-icon-text text-warning" onclick="this.closest('form').submit(); return false;">
            <i class="ri-toggle-line"></i>
            <span class="fw-semibold text-uppercase">TOGGLE AKTIF</span>
        </a>
    </form>
@endif

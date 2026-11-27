<a href="{{ route($url['show'], $id) }}" class="action-link-icon-text btnShow">
    <i class="ri-search-line"></i>
    <span class="fw-semibold text-uppercase">Show</span>
</a>

@can('master_edit')
    <a href="{{ route($url['vedit'], $id) }}" class="action-link-icon-text editBtn">
        <i class="ri-edit-line"></i>
        <span class="fw-semibold text-uppercase">View / Edit</span>
    </a>
@endcan

@can('master_edit')
    <form method="POST" action="{{ route($url['verify'], $id) }}" class="d-inline">
        @csrf
        @method('PUT')
        <a href="#" class="action-link-icon-text text-success"
            onclick="this.closest('form').submit(); return false;">
            <i class="ri-shield-check-line"></i>
            <span class="fw-semibold text-uppercase">Verifikasi</span>
        </a>
    </form>
@endcan

@can('master_add')
    <form method="POST" action="{{ route($url['rental_now'], $id) }}" class="d-inline">
        @csrf
        <a href="#" class="action-link-icon-text text-primary"
            onclick="this.closest('form').submit(); return false;">
            <i class="ri-car-line"></i>
            <span class="fw-semibold text-uppercase">Sewa Sekarang</span>
        </a>
    </form>
@endcan

@can('master_delete')
    <form method="POST" action="{{ route($url['destroy'], $id) }}" class="delete form-delete-inline">
        @csrf
        @method('DELETE')
        <a href="#" class="action-link-icon-text text-danger deleteBtn">
            <i class="ri-close-circle-line"></i>
            <span class="fw-semibold text-uppercase">Delete</span>
        </a>
    </form>
@endcan

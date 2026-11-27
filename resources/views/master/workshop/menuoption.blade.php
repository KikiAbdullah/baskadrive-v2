<a href="#" class="action-link-icon-text detailBtn">
    <i class="ri-search-line"></i>
    <span class="fw-semibold text-uppercase">Detail</span>
</a>

@can('master_edit')
    <a href="{{ route($url['vedit'], $id) }}" class="action-link-icon-text editBtn">
        <i class="ri-edit-line"></i>
        <span class="fw-semibold text-uppercase">View / Edit</span>
    </a>
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

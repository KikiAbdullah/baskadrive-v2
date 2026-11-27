<a href="#" class="action-link-icon-text detailBtn">
    <i class="ri-search-line"></i>
    <span class="fw-semibold text-uppercase">Detail</span>
</a>

@can('permissions_edit')
    <a href="{{ route($url['vedit'], $id) }}" class="action-link-icon-text editBtn">
        <i class="ri-edit-line"></i>
        <span class="fw-semibold text-uppercase">View / Edit</span>
    </a>
@endcan

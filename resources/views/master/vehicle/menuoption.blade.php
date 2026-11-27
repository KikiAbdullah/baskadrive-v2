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

<a href="{{ route($url['barcode'], $id) }}" class="action-link-icon-text" target="_blank">
    <i class="ri-qr-code-line"></i>
    <span class="fw-semibold text-uppercase">Barcode</span>
</a>

<a href="{{ route($url['history'], $id) }}" class="action-link-icon-text">
    <i class="ri-history-line"></i>
    <span class="fw-semibold text-uppercase">History</span>
</a>

<a href="{{ route($url['barcode_pdf'], $id) }}" class="action-link-icon-text" target="_blank">
    <i class="ri-printer-line"></i>
    <span class="fw-semibold text-uppercase">Label PDF</span>
</a>

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

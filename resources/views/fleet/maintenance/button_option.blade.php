<a href="{{ route('fleet.maintenance.edit', $item->maintenance_id) }}" class="action-link-icon-text editBtn">
    <i class="ri-edit-line"></i>
    <span class="fw-semibold text-uppercase">EDIT</span>
</a>

@if ($item->status !== 'completed')
    <a href="#!" data-id="{{ $item->maintenance_id }}" class="action-link-icon-text text-success btn-complete">
        <i class="ri-check-double-line"></i>
        <span class="fw-semibold text-uppercase">SELESAI</span>
    </a>
@endif

<a href="{{ route('rental.show', $rental->rental_id) }}" class="action-link-icon-text btnShow">
    <i class="ri-search-line"></i>
    <span class="fw-semibold text-uppercase">SHOW</span>
</a>

<a href="{{ route('rental.edit', $rental->rental_id) }}" class="action-link-icon-text editBtn">
    <i class="ri-edit-line"></i>
    <span class="fw-semibold text-uppercase">EDIT</span>
</a>

@if ($rental->status === 'reserved')
    <a href="#!" data-id="{{ $rental->rental_id }}" class="action-link-icon-text text-success btn-confirm">
        <i class="ri-check-double-line"></i>
        <span class="fw-semibold text-uppercase">KONFIRMASI</span>
    </a>
    @can('rental_cancel')
        <a href="#!" data-id="{{ $rental->rental_id }}" class="action-link-icon-text text-danger btn-cancel">
            <i class="ri-close-circle-line"></i>
            <span class="fw-semibold text-uppercase">BATALKAN</span>
        </a>
    @endcan
@endif

@if ($rental->status === 'ongoing')
    <a href="{{ route('rental.detail.return.form', $rental->rental_id) }}"
        class="action-link-icon-text btnReturn">
        <i class="ri-arrow-go-back-line"></i>
        <span class="fw-semibold text-uppercase">PENGEMBALIAN</span>
    </a>
@endif

@if ($rental->status === 'completed')
    <a href="{{ route('rental.detail.invoice.generate', $rental->rental_id) }}"
        class="action-link-icon-text btnInvoice">
        <i class="ri-file-list-3-line"></i>
        <span class="fw-semibold text-uppercase">INVOICE</span>
    </a>
@endif

@if (in_array($rental->status, ['reserved', 'ongoing']))
    <a href="{{ route('rental.print', $rental->rental_id) }}"
        class="action-link-icon-text btnPrint">
        <i class="ri-printer-line"></i>
        <span class="fw-semibold text-uppercase">CETAK</span>
    </a>
@endif

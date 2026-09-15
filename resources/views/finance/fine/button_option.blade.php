@if ($item->status === 'unpaid')
    <a href="#!" data-id="{{ $item->fine_id }}" class="action-link-icon-text text-success btn-pay">
        <i class="ri-check-double-line"></i>
        <span class="fw-semibold text-uppercase">BAYAR</span>
    </a>
    @can('fine_waive')
        <a href="#!" data-id="{{ $item->fine_id }}" class="action-link-icon-text text-secondary btn-waive">
            <i class="ri-hand-coin-line"></i>
            <span class="fw-semibold text-uppercase">BEBASKAN</span>
        </a>
    @endcan
@else
    <span class="text-muted small fst-italic">
        {{ $item->status === 'paid' ? 'Denda sudah dibayar' : 'Denda dibebaskan' }}
    </span>
@endif

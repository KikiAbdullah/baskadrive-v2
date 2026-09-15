<a href="{{ route('finance.invoice.show', $item->invoice_id) }}" class="action-link-icon-text btnShow">
    <i class="ri-search-line"></i>
    <span class="fw-semibold text-uppercase">SHOW</span>
</a>

<a href="{{ route('finance.invoice.print', $item->invoice_id) }}"
    class="action-link-icon-text btnPrint">
    <i class="ri-printer-line"></i>
    <span class="fw-semibold text-uppercase">CETAK</span>
</a>

@if (!in_array($item->status, ['cancelled']))
    <a href="#!" data-id="{{ $item->invoice_id }}" class="action-link-icon-text text-info btn-send">
        <i class="ri-mail-send-line"></i>
        <span class="fw-semibold text-uppercase">KIRIM EMAIL</span>
    </a>
@endif

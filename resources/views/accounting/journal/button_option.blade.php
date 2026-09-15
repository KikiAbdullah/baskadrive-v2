<a href="{{ route('accounting.journal.show', $item->journal_id) }}" class="action-link-icon-text btnShow">
    <i class="ri-search-line"></i>
    <span class="fw-semibold text-uppercase">SHOW</span>
</a>

<a href="{{ route('accounting.journal.export', $item->journal_id) }}" class="action-link-icon-text btnExport">
    <i class="ri-download-line"></i>
    <span class="fw-semibold text-uppercase">EXPORT CSV</span>
</a>

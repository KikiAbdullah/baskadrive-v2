<a href="{{ route('accounting.journal.show', $item->journal_id) }}" class="action-link-icon-text btnShow">
    <i class="ri-search-line"></i>
    <span class="fw-semibold text-uppercase">SHOW</span>
</a>

@can('accounting_export')
    {{-- AKN-01: rute kini /journal/{id}/export --}}
    <a href="{{ route('accounting.journal.export', $item->journal_id) }}" class="action-link-icon-text btnExport">
        <i class="ri-download-line"></i>
        <span class="fw-semibold text-uppercase">EXPORT CSV</span>
    </a>
@endcan

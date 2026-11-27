<div class="modal fade" id="masterDetailModal" tabindex="-1" aria-labelledby="masterDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="masterDetailTitle">Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="masterDetailBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const hiddenKeys = ['detail', 'created_at', 'updated_at', 'deleted_at'];
    const labels = window.detailLabels || {};

    function humanize(key) {
        if (labels[key]) return labels[key];
        return key.replace(/_/g, ' ')
            .replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    function renderValue(value) {
        if (value === null || value === undefined || value === '') return '-';
        if (Array.isArray(value)) return value.join(', ');
        if (typeof value === 'object') return JSON.stringify(value);
        return String(value).replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.detailBtn');
        if (!btn) return;
        e.preventDefault();

        const table = window.dtable;
        const row = table ? table.rows({ selected: true }).data().toArray()[0] : null;
        if (!row) return;

        const data = row.detail || row;
        const titleKey = window.detailTitleKey || 'name';
        const title = data[titleKey] || row[titleKey] || data.location_name || data.promo_code || data.plate || '';

        let html = '<div class="table-responsive"><table class="table table-sm table-borderless mb-0">';
        Object.keys(data).forEach(function (key) {
            if (hiddenKeys.indexOf(key) !== -1) return;
            html += '<tr><th class="text-muted fw-medium" style="width:38%">' + humanize(key) + '</th>'
                + '<td class="fw-semibold">' + renderValue(data[key]) + '</td></tr>';
        });
        html += '</table></div>';

        document.getElementById('masterDetailBody').innerHTML = html;
        document.getElementById('masterDetailTitle').textContent = title ? 'Detail · ' + title : 'Detail';
        if (window.bootstrap) {
            window.bootstrap.Modal.getOrCreateInstance(document.getElementById('masterDetailModal')).show();
        }
    });
})();
</script>

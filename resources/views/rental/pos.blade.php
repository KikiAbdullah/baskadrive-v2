@extends('layouts.header-pos')

@section('customcss')
<style>
    .pos-receipt { position: sticky; top: 1rem; }
    .pos-vehicle-card { cursor: pointer; border: 1px solid #e9eaf4; border-radius: 12px; padding: .75rem; transition: all .15s; height: 100%; }
    .pos-vehicle-card:hover { border-color: #666CFF; box-shadow: 0 4px 14px rgba(102,108,255,.12); }
    .pos-vehicle-card.selected { border-color: #666CFF; background: #f2f2ff; box-shadow: 0 0 0 2px rgba(102,108,255,.25); }
    .pos-plate { font-family: monospace; font-weight: 700; letter-spacing: 1px; background: #14142b; color: #fff; border-radius: 6px; padding: 2px 8px; font-size: .8rem; }
    .pos-total { font-size: 1.9rem; font-weight: 800; color: #666CFF; line-height: 1.1; }
    .pos-line { display: flex; justify-content: space-between; padding: 3px 0; font-size: .875rem; }
    .pos-line .lbl { color: #6b7194; }
    .pos-line .val { font-weight: 600; }
    .pos-line.discount .val { color: #22c55e; }
    .receipt-divider { border-top: 1px dashed #d9dbee; margin: .5rem 0; }
    .pos-customer-result { cursor: pointer; }
    .pos-customer-result:hover { background: #f4f4ff; }
    .pos-empty { color: #9ca3b5; text-align: center; padding: 2rem 1rem; }
    .pos-day-badge { background: rgba(102,108,255,.1); color: #666CFF; border-radius: 8px; padding: 2px 10px; font-weight: 700; font-size: .8rem; }
</style>
@endsection

@section('content')
<div class="row g-3">

    {{-- ================= KOLOM KIRI: INPUT ================= --}}
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="mb-3"><i class="ri-user-3-line me-2"></i>① Pelanggan</h5>

                <div class="row g-2">
                    <div class="col-md-8 position-relative">
                        <input type="text" id="pos-customer-search" class="form-control"
                               placeholder="Cari nama / HP / No. KTP…" autocomplete="off">
                        <div id="pos-customer-results" class="list-group position-absolute w-100 shadow-sm" style="z-index:1050; display:none;"></div>
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-outline-primary w-100" id="pos-btn-walkin">
                            <i class="ri-user-add-line me-1"></i> Walk-in
                        </button>
                    </div>
                </div>

                {{-- Form walk-in cepat (tersembunyi) --}}
                <div id="pos-walkin-form" class="border rounded-3 p-3 mt-2 d-none" style="background:#f8f9ff;">
                    <div class="row g-2">
                        <div class="col-md-5">
                            <input type="text" id="pos-walkin-name" class="form-control" placeholder="Nama pelanggan *" maxlength="100">
                        </div>
                        <div class="col-md-4">
                            <input type="text" id="pos-walkin-phone" class="form-control" placeholder="No. HP *" maxlength="20">
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-primary w-100" id="pos-walkin-save">Simpan</button>
                        </div>
                    </div>
                    <small class="text-muted">Cukup nama + HP — data lengkap menyusul di menu Master.</small>
                </div>

                <div id="pos-customer-selected" class="mt-2 fw-semibold d-none">
                    <i class="ri-checkbox-circle-fill text-success me-1"></i>
                    <span id="pos-customer-name"></span>
                    <button type="button" class="btn btn-sm btn-link text-muted ms-2" id="pos-customer-clear">ganti</button>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h5 class="mb-3"><i class="ri-calendar-line me-2"></i>② Periode Sewa</h5>
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Mulai</label>
                        <input type="date" id="pos-start" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Selesai</label>
                        <input type="date" id="pos-end" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <span class="pos-day-badge" id="pos-days">— hari</span>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" id="pos-driver">
                            <label class="form-check-label" for="pos-driver">Dengan sopir</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h5 class="mb-3">
                    <i class="ri-car-line me-2"></i>③ Unit Tersedia
                    <input type="text" id="pos-vehicle-q" class="form-control form-control-sm d-inline-block ms-2" style="width:180px" placeholder="Filter plat/model…">
                </h5>
                <div id="pos-vehicle-grid" class="row g-2">
                    <div class="col-12 pos-empty">Pilih tanggal untuk melihat unit bebas.</div>
                    <div class="col-12 pos-empty d-none" id="pos-vehicle-loading"><span class="spinner-border spinner-border-sm me-2"></span>Memuat unit…</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="mb-3"><i class="ri-equalizer-line me-2"></i>④ Opsi</h5>
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label">Promo</label>
                        <select id="pos-promo" class="form-select"><option value="">— Tanpa promo —</option></select>
                        <div class="form-text text-danger d-none" id="pos-promo-note"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Sopir</label>
                        <select id="pos-driver-select" class="form-select"><option value="">— Tanpa sopir tetap —</option></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Catatan</label>
                        <input type="text" id="pos-notes" class="form-control" maxlength="500" placeholder="Opsional">
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ================= KOLOM KANAN: STRUK ================= --}}
    <div class="col-lg-4">
        <div class="pos-receipt">
            <div class="card border-primary shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0 fw-bold">STRUK SEWA</h6>
                        <span class="badge bg-label-primary" id="pos-receipt-days">—</span>
                    </div>
                    <div id="pos-receipt-lines">
                        <div class="pos-empty">Belum ada unit terpilih.</div>
                    </div>
                    <div class="receipt-divider"></div>
                    <div class="d-flex justify-content-between align-items-end">
                        <span class="text-muted">TOTAL</span>
                        <span class="pos-total" id="pos-total">Rp 0</span>
                    </div>
                    <div class="pos-line mt-1"><span class="lbl">Deposit (refundable)</span><span class="val" id="pos-deposit">Rp 0</span></div>

                    <button type="button" class="btn btn-primary w-100 mt-3 py-2 fw-bold" id="pos-save" disabled>
                        <i class="ri-save-line me-1"></i> SIMPAN BOOKING
                    </button>
                    <small class="d-block text-center text-muted mt-2" id="pos-hint">
                        Lengkapi pelanggan, periode, dan unit untuk mengaktifkan.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('customjs')
<script>
(function () {
    'use strict';

    const $ = (id) => document.getElementById(id);
    const state = { options: null, customer: null, vehicle: null, vehicles: [], quote: null, debounce: null };
    const rp = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');

    // ---------- Bootstrap ----------
    document.addEventListener('DOMContentLoaded', async () => {
        const today = new Date();
        const fmt = (d) => d.toISOString().slice(0, 10);
        $('pos-start').value = fmt(new Date(today.getTime() + 864e5));
        $('pos-end').value = fmt(new Date(today.getTime() + 3 * 864e5));

        const res = await fetch('{{ route('rental.pos.options') }}');
        const json = await res.json();
        if (!json.status) return;
        state.options = json.data;

        json.data.promos.forEach((p) => {
            $('pos-promo').insertAdjacentHTML('beforeend',
                `<option value="${p.promo_id}">${p.promo_code} — ${p.description || ''}</option>`);
        });
        json.data.drivers.forEach((d) => {
            $('pos-driver-select').insertAdjacentHTML('beforeend',
                `<option value="${d.driver_id}">${d.name}${d.phone ? ' · ' + d.phone : ''}</option>`);
        });

        bindEvents();
        reloadVehicles();
    });

    function bindEvents() {
        // Pencarian pelanggan (debounce)
        $('pos-customer-search').addEventListener('input', (e) => {
            clearTimeout(state.debounce);
            state.debounce = setTimeout(() => searchCustomer(e.target.value), 300);
        });
        $('pos-btn-walkin').addEventListener('click', () => $('pos-walkin-form').classList.toggle('d-none'));
        $('pos-walkin-save').addEventListener('click', saveWalkin);
        $('pos-customer-clear').addEventListener('click', () => {
            state.customer = null;
            $('pos-customer-selected').classList.add('d-none');
            $('pos-customer-search').classList.remove('d-none');
            $('pos-customer-search').value = '';
        });

        $('pos-start').addEventListener('change', reloadVehicles);
        $('pos-end').addEventListener('change', reloadVehicles);
        $('pos-vehicle-q').addEventListener('input', () => {
            clearTimeout(state.debounce);
            state.debounce = setTimeout(reloadVehicles, 300);
        });
        $('pos-driver').addEventListener('change', refreshQuote);
        $('pos-promo').addEventListener('change', refreshQuote);
        $('pos-driver-select').addEventListener('change', refreshQuote);
        $('pos-save').addEventListener('click', saveBooking);
    }

    // ---------- Pelanggan ----------
    async function searchCustomer(q) {
        if (!q || q.length < 2) { $('pos-customer-results').style.display = 'none'; return; }
        const res = await fetch(`{{ route('rental.pos.customers') }}?q=${encodeURIComponent(q)}`);
        const json = await res.json();
        if (!json.status) return;
        const box = $('pos-customer-results');
        box.innerHTML = '';
        (json.data.customers || []).forEach((c) => {
            const a = document.createElement('a');
            a.className = 'list-group-item list-group-item-action pos-customer-result';
            a.textContent = c.label;
            a.addEventListener('click', () => pickCustomer(c));
            box.appendChild(a);
        });
        box.style.display = 'block';
    }

    function pickCustomer(c) {
        state.customer = c;
        $('pos-customer-results').style.display = 'none';
        $('pos-customer-search').classList.add('d-none');
        $('pos-customer-selected').classList.remove('d-none');
        $('pos-customer-name').textContent = c.label;
        updateSaveState();
    }

    async function saveWalkin() {
        const name = $('pos-walkin-name').value.trim();
        const phone = $('pos-walkin-phone').value.trim();
        if (!name || !phone) { alert('Nama dan No. HP wajib diisi.'); return; }
        const res = await fetch('{{ route('rental.pos.customers.store') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            body: JSON.stringify({ name, phone }),
        });
        const json = await res.json();
        if (!json.status) { alert(json.msg || 'Gagal menyimpan pelanggan.'); return; }
        $('pos-walkin-form').classList.add('d-none');
        $('pos-walkin-name').value = '';
        $('pos-walkin-phone').value = '';
        pickCustomer(json.data.customer);
    }

    // ---------- Unit ----------
    async function reloadVehicles() {
        const start = $('pos-start').value;
        const end = $('pos-end').value;
        if (!start || !end || end <= start) {
            $('pos-days').textContent = '— hari';
            return;
        }
        const days = Math.max(1, Math.round((new Date(end) - new Date(start)) / 864e5));
        $('pos-days').textContent = days + ' hari';

        $('pos-vehicle-loading').classList.remove('d-none');
        const q = $('pos-vehicle-q').value.trim();
        const res = await fetch(`{{ route('rental.pos.vehicles') }}?start=${start}&end=${end}&q=${encodeURIComponent(q)}`);
        const json = await res.json();
        $('pos-vehicle-loading').classList.add('d-none');
        if (!json.status) return;

        state.vehicles = json.data.vehicles || [];
        state.vehicle = null;
        renderVehicles();
        refreshQuote();
    }

    function renderVehicles() {
        const grid = $('pos-vehicle-grid');
        grid.querySelectorAll('.pos-vehicle-card').forEach((el) => el.remove());
        const empty = grid.querySelector('.pos-empty');
        if (!state.vehicles.length) {
            empty.textContent = 'Tidak ada unit bebas untuk rentang ini (termasuk buffer pembersihan).';
            empty.classList.remove('d-none');
            return;
        }
        empty.classList.add('d-none');
        state.vehicles.forEach((v) => {
            const col = document.createElement('div');
            col.className = 'col-md-4 col-sm-6';
            col.innerHTML = `
                <div class="pos-vehicle-card" data-id="${v.vehicle_id}">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="pos-plate">${v.plate}</span>
                        <small class="text-muted">${v.year || ''}</small>
                    </div>
                    <div class="fw-semibold" style="font-size:.85rem">${v.model}</div>
                    <div class="d-flex justify-content-between mt-1">
                        <small class="text-primary fw-bold">${rp(v.base_rate_per_day)}/hari</small>
                        ${v.deposit_amount > 0 ? `<small class="text-muted">Dep. ${rp(v.deposit_amount)}</small>` : ''}
                    </div>
                </div>`;
            col.querySelector('.pos-vehicle-card').addEventListener('click', () => pickVehicle(v));
            grid.appendChild(col);
        });
    }

    function pickVehicle(v) {
        state.vehicle = v;
        document.querySelectorAll('.pos-vehicle-card').forEach((el) =>
            el.classList.toggle('selected', Number(el.dataset.id) === v.vehicle_id));
        refreshQuote();
    }

    // ---------- Quote (struk hidup) ----------
    async function refreshQuote() {
        if (!state.vehicle) {
            $('pos-receipt-lines').innerHTML = '<div class="pos-empty">Belum ada unit terpilih.</div>';
            $('pos-total').textContent = rp(0);
            $('pos-deposit').textContent = rp(0);
            $('pos-receipt-days').textContent = '—';
            updateSaveState();
            return;
        }
        const payload = {
            vehicle_id: state.vehicle.vehicle_id,
            customer_id: state.customer ? state.customer.customer_id : null,
            rental_start_date: $('pos-start').value,
            rental_end_date: $('pos-end').value,
            is_with_driver: $('pos-driver').checked,
            driver_daily_fee: null,
            promo_id: $('pos-promo').value || null,
        };
        const res = await fetch('{{ route('rental.pos.quote') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (!json.status) return;
        state.quote = json.data;
        renderReceipt(json.data);
        updateSaveState();
    }

    function renderReceipt(q) {
        const line = (lbl, val, cls = '') =>
            `<div class="pos-line ${cls}"><span class="lbl">${lbl}</span><span class="val">${val}</span></div>`;
        let html = line(`${state.vehicle.plate} · ${state.vehicle.model} × ${q.rental_days} hari`, rp(q.total_base_price));
        html += line('Asuransi', rp(q.insurance_fee));
        if (q.driver_fee > 0) html += line('Sopir', rp(q.driver_fee));
        if (q.young_driver_fee > 0) html += line('Young driver', rp(q.young_driver_fee));
        if (q.discount_amount > 0) html += line(`Promo`, '−' + rp(q.discount_amount), 'discount');
        html += line(`PPN ${q.tax_percent}%`, rp(q.tax_amount));
        $('pos-receipt-lines').innerHTML = html;
        $('pos-total').textContent = rp(q.total_amount);
        $('pos-deposit').textContent = rp(q.deposit_amount);
        $('pos-receipt-days').textContent = q.rental_days + ' hari';

        const note = $('pos-promo-note');
        if (q.promo_note) { note.textContent = q.promo_note; note.classList.remove('d-none'); }
        else { note.classList.add('d-none'); }
    }

    function updateSaveState() {
        const ok = state.customer && state.vehicle && $('pos-start').value && $('pos-end').value > $('pos-start').value;
        $('pos-save').disabled = !ok;
        $('pos-hint').textContent = ok
            ? 'Siap disimpan — pelanggan akan menerima notifikasi WhatsApp.'
            : 'Lengkapi pelanggan, periode, dan unit untuk mengaktifkan.';
    }

    // ---------- Simpan ----------
    async function saveBooking() {
        const btn = $('pos-save');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan…';
        try {
            const res = await fetch('{{ route('rental.pos.store') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({
                    customer_id: state.customer.customer_id,
                    vehicle_id: state.vehicle.vehicle_id,
                    rental_start_date: $('pos-start').value,
                    rental_end_date: $('pos-end').value,
                    pickup_location_id: (state.options.locations[0] || {}).location_id,
                    return_location_id: (state.options.locations[0] || {}).location_id,
                    is_with_driver: $('pos-driver').checked,
                    driver_id: $('pos-driver-select').value || null,
                    promo_id: $('pos-promo').value || null,
                    notes: $('pos-notes').value.trim() || null,
                }),
            });
            const json = await res.json();
            if (!json.status) {
                alert(json.msg || 'Gagal menyimpan booking.');
                return;
            }
            window.location.href = json.data.redirect_url;
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-save-line me-1"></i> SIMPAN BOOKING';
        }
    }
})();
</script>
@endsection

@extends('layouts.header-pos')

@section('customcss')
<style>
    :root {
        --pos-brand: #666cff;
        --pos-brand-dark: #5258e8;
        --pos-ink: #1d1d33;
        --pos-muted: #7a8096;
        --pos-border: #e9eaf4;
        --pos-bg: #f6f7fb;
    }

    .pos-page { max-width: 1520px; margin: 0 auto; }

    .pos-heading {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.25rem;
    }

    .pos-heading h1 {
        margin: 0 0 .2rem;
        font-size: 1.55rem;
        font-weight: 800;
        letter-spacing: -.03em;
        color: var(--pos-ink);
    }

    .pos-heading p { margin: 0; color: var(--pos-muted); font-size: .875rem; }

    .pos-chip {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .4rem .7rem;
        border: 1px solid var(--pos-border);
        border-radius: 999px;
        background: #fff;
        color: #5c6379;
        font-size: .75rem;
        font-weight: 600;
    }

    .pos-chip i { color: var(--pos-brand); font-size: .95rem; }

    /* ---------- Kartu langkah ---------- */
    .pos-step-card {
        background: #fff;
        border: 1px solid var(--pos-border);
        border-radius: 14px;
        box-shadow: 0 3px 14px rgba(29, 29, 51, .04);
        overflow: hidden;
    }

    .pos-step-card + .pos-step-card { margin-top: 1rem; }

    .pos-step-head {
        display: flex;
        align-items: center;
        gap: .8rem;
        padding: .9rem 1.15rem;
        border-bottom: 1px solid #f1f2f8;
        background: linear-gradient(90deg, #fbfbff, #fff);
    }

    .pos-step-no {
        display: grid;
        place-items: center;
        flex: none;
        width: 32px;
        height: 32px;
        border-radius: 10px;
        background: var(--pos-brand);
        color: #fff;
        font-weight: 800;
        font-size: .9rem;
        box-shadow: 0 5px 12px rgba(102, 108, 255, .25);
    }

    .pos-step-head h5 { margin: 0; font-size: 1rem; font-weight: 700; color: var(--pos-ink); }
    .pos-step-head small { display: block; color: #969bb0; font-size: .75rem; }
    .pos-step-body { padding: 1.15rem; }

    .pos-label {
        display: block;
        margin-bottom: .35rem;
        font-size: .74rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #565d73;
    }

    .form-control, .form-select {
        border-color: #e2e5ef;
        border-radius: 10px;
        font-size: .9rem;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--pos-brand);
        box-shadow: 0 0 0 3px rgba(102, 108, 255, .12);
    }

    .pos-input-icon { position: relative; }
    .pos-input-icon > i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #a3a9bc;
        pointer-events: none;
    }
    .pos-input-icon .form-control { padding-left: 36px; }

    .pos-customer-results {
        top: calc(100% - 8px);
        max-height: 240px;
        overflow-y: auto;
        border: 1px solid var(--pos-border);
        border-radius: 10px;
    }

    .pos-customer-result { border: 0; font-size: .875rem; padding: .55rem .8rem; }
    .pos-customer-result:hover, .pos-customer-result:focus { background: #f3f4ff; }

    .pos-walkin {
        border-radius: 11px;
        border: 1px dashed #c9ccf5;
        background: #f8f8ff;
        padding: .9rem;
    }

    .pos-selected {
        display: flex;
        align-items: center;
        gap: .45rem;
        margin-top: .75rem;
        padding: .55rem .75rem;
        border: 1px solid #c9f0dc;
        border-radius: 10px;
        background: #f1fbf5;
        color: #16794a;
        font-weight: 600;
        font-size: .875rem;
    }

    .pos-day-badge {
        display: inline-block;
        background: rgba(102, 108, 255, .1);
        color: var(--pos-brand);
        border-radius: 8px;
        padding: .3rem .7rem;
        font-weight: 800;
        font-size: .8rem;
    }

    /* ---------- Unit ---------- */
    .pos-vehicle-card {
        cursor: pointer;
        border: 1px solid var(--pos-border);
        border-radius: 12px;
        overflow: hidden;
        height: 100%;
        background: #fff;
        transition: transform .15s, border-color .15s, box-shadow .15s;
    }

    .pos-vehicle-card:hover {
        transform: translateY(-2px);
        border-color: var(--pos-brand);
        box-shadow: 0 8px 20px rgba(102, 108, 255, .12);
    }

    .pos-vehicle-card.selected {
        border-color: var(--pos-brand);
        box-shadow: 0 0 0 2px rgba(102, 108, 255, .22);
    }

    .pos-vehicle-thumb {
        position: relative;
        display: grid;
        place-items: center;
        height: 68px;
        background: linear-gradient(135deg, #eef0ff, #f7f7ff);
        color: var(--pos-brand);
        font-size: 2rem;
        overflow: hidden;
    }

    .pos-vehicle-thumb::after {
        content: "";
        position: absolute;
        width: 120px;
        height: 120px;
        right: -40px;
        top: -60px;
        border-radius: 50%;
        background: rgba(102, 108, 255, .08);
    }

    .pos-vehicle-card.selected .pos-vehicle-thumb {
        background: linear-gradient(135deg, var(--pos-brand), #8a5cf6);
        color: #fff;
    }

    .pos-check {
        position: absolute;
        top: 6px;
        right: 6px;
        z-index: 1;
        display: none;
        place-items: center;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #fff;
        color: var(--pos-brand);
        font-size: .8rem;
    }

    .pos-vehicle-card.selected .pos-check { display: grid; }
    .pos-vehicle-body { padding: .7rem .75rem .8rem; }

    .pos-plate {
        display: inline-block;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-weight: 700;
        letter-spacing: .5px;
        background: #1b1b2f;
        color: #fff;
        border-radius: 5px;
        padding: 2px 7px;
        font-size: .72rem;
    }

    .pos-model { font-size: .85rem; font-weight: 700; color: var(--pos-ink); }
    .pos-meta { font-size: .72rem; color: var(--pos-muted); }
    .pos-rate { color: var(--pos-brand); font-weight: 800; font-size: .8rem; }

    .pos-empty {
        color: #9ca3b5;
        text-align: center;
        padding: 1.6rem 1rem;
        font-size: .85rem;
    }

    .pos-empty i { display: block; font-size: 1.8rem; margin-bottom: .4rem; color: #c2c6d8; }

    /* ---------- Struk ---------- */
    .pos-receipt { position: sticky; top: 76px; }

    .pos-receipt-card {
        border: 0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 12px 34px rgba(29, 29, 51, .10);
    }

    .pos-receipt-head {
        background: linear-gradient(135deg, #575df0, #666cff 55%, #8a5cf6);
        color: #fff;
        padding: 1rem 1.15rem;
    }

    .pos-receipt-head h6 { margin: 0; font-weight: 800; letter-spacing: .06em; font-size: .85rem; }
    .pos-receipt-head small { color: rgba(255, 255, 255, .78); font-size: .72rem; }

    .pos-receipt-head .badge {
        color: #fff;
        background: rgba(255, 255, 255, .18);
        font-weight: 700;
    }

    .pos-receipt-body { padding: 1.15rem; }

    .pos-progress { display: flex; gap: .4rem; margin-bottom: 1rem; }
    .pos-progress-item {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .25rem;
        padding: .35rem .2rem;
        border-radius: 8px;
        background: #f3f4f9;
        color: #9aa0b4;
        font-size: .68rem;
        font-weight: 700;
    }
    .pos-progress-item.done { background: #eaf8f0; color: #16794a; }
    .pos-progress-item i { font-size: .8rem; }

    .pos-line {
        display: flex;
        justify-content: space-between;
        gap: .75rem;
        padding: .28rem 0;
        font-size: .83rem;
    }
    .pos-line .lbl { color: #757b91; }
    .pos-line .val { font-weight: 700; color: var(--pos-ink); text-align: right; }
    .pos-line.discount .val { color: #16a34a; }

    .receipt-divider { border-top: 1px dashed #d9dbee; margin: .7rem 0; }

    .pos-total-box {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
        padding: .85rem 1rem;
        border-radius: 12px;
        background: #f3f4ff;
    }
    .pos-total-box .lbl { font-weight: 700; color: #4d5470; font-size: .8rem; }
    .pos-total { font-size: 1.7rem; font-weight: 800; color: var(--pos-brand); line-height: 1.1; }

    .btn-pos-save {
        border: 0;
        border-radius: 11px;
        padding: .8rem 1rem;
        font-weight: 800;
        letter-spacing: .02em;
        background: var(--pos-brand);
        box-shadow: 0 8px 20px rgba(102, 108, 255, .26);
    }
    .btn-pos-save:hover:not(:disabled) { background: var(--pos-brand-dark); }
    .btn-pos-save:disabled { background: #e1e3f2; color: #9297ad; box-shadow: none; }

    @media (max-width: 1199px) {
        .pos-receipt { position: static; }
    }
</style>
@endsection

@section('content')
<div class="pos-page">
    <div class="pos-heading">
        <div>
            <h1>Buat Booking Baru</h1>
            <p>Pilih pelanggan, tentukan periode, lalu klik unit — struk harga menghitung sendiri.</p>
        </div>
        <div class="d-none d-md-flex gap-2">
            <span class="pos-chip"><i class="ri-flash-line"></i> Satu layar</span>
            <span class="pos-chip"><i class="ri-price-tag-3-line"></i> Harga real-time</span>
        </div>
    </div>

    <div class="row g-3">
        {{-- ============ KOLOM KIRI: INPUT ============ --}}
        <div class="col-xl-8">

            {{-- 1 · Pelanggan --}}
            <section class="pos-step-card">
                <div class="pos-step-head">
                    <span class="pos-step-no">1</span>
                    <div>
                        <h5>Pelanggan</h5>
                        <small>Cari yang sudah terdaftar atau daftarkan walk-in</small>
                    </div>
                </div>
                <div class="pos-step-body">
                    <div class="row g-2">
                        <div class="col-md-8 position-relative" id="pos-customer-search-wrap">
                            <label class="pos-label" for="pos-customer-search">Cari pelanggan</label>
                            <div class="pos-input-icon">
                                <i class="ri-user-search-line"></i>
                                <input type="text" id="pos-customer-search" class="form-control"
                                    placeholder="Nama / No. HP / No. KTP…" autocomplete="off">
                            </div>
                            <div id="pos-customer-results"
                                class="list-group position-absolute w-100 shadow-sm pos-customer-results"
                                style="z-index:1050; display:none;"></div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" class="btn btn-outline-primary w-100" id="pos-btn-walkin">
                                <i class="ri-user-add-line me-1"></i> Walk-in
                            </button>
                        </div>
                    </div>

                    {{-- Form walk-in cepat --}}
                    <div id="pos-walkin-form" class="pos-walkin mt-3 d-none">
                        <div class="row g-2">
                            <div class="col-md-5">
                                <label class="pos-label" for="pos-walkin-name">Nama pelanggan *</label>
                                <input type="text" id="pos-walkin-name" class="form-control" placeholder="Nama lengkap" maxlength="100">
                            </div>
                            <div class="col-md-4">
                                <label class="pos-label" for="pos-walkin-phone">No. HP *</label>
                                <input type="text" id="pos-walkin-phone" class="form-control" placeholder="08xxxxxxxxxx" maxlength="20">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="button" class="btn btn-primary w-100" id="pos-walkin-save">
                                    <i class="ri-check-line me-1"></i> Simpan
                                </button>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">Cukup nama + HP — data lengkap menyusul di menu Master.</small>
                    </div>

                    <div id="pos-customer-selected" class="pos-selected d-none">
                        <i class="ri-checkbox-circle-fill"></i>
                        <span id="pos-customer-name"></span>
                        <button type="button" class="btn btn-sm btn-link text-muted ms-auto p-0" id="pos-customer-clear">
                            ganti
                        </button>
                    </div>
                </div>
            </section>

            {{-- 2 · Periode --}}
            <section class="pos-step-card">
                <div class="pos-step-head">
                    <span class="pos-step-no">2</span>
                    <div>
                        <h5>Periode Sewa</h5>
                        <small>Tanggal menentukan unit yang bebas</small>
                    </div>
                </div>
                <div class="pos-step-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="pos-label" for="pos-start">Mulai</label>
                            <input type="date" id="pos-start" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="pos-label" for="pos-end">Selesai</label>
                            <input type="date" id="pos-end" class="form-control">
                        </div>
                        <div class="col-md-4 d-flex align-items-center gap-3 flex-wrap">
                            <span class="pos-day-badge" id="pos-days">— hari</span>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="pos-driver">
                                <label class="form-check-label" for="pos-driver">Dengan sopir</label>
                            </div>
                        </div>
                    </div>
                    <small id="pos-date-hint" class="text-danger d-none mt-2">Tanggal selesai harus setelah tanggal mulai.</small>
                </div>
            </section>

            {{-- 3 · Unit --}}
            <section class="pos-step-card">
                <div class="pos-step-head">
                    <span class="pos-step-no">3</span>
                    <div>
                        <h5>Unit Tersedia</h5>
                        <small>Klik kartu unit untuk memilih</small>
                    </div>
                </div>
                <div class="pos-step-body">
                    <div class="pos-input-icon mb-3" style="max-width:320px">
                        <i class="ri-search-line"></i>
                        <input type="text" id="pos-vehicle-q" class="form-control form-control-sm"
                            placeholder="Filter plat / model…">
                    </div>
                    <div id="pos-vehicle-grid" class="row g-2">
                        <div class="col-12 pos-empty">
                            <i class="ri-car-line"></i>
                            Pilih tanggal sewa untuk melihat unit bebas.
                        </div>
                        <div class="col-12 pos-empty d-none" id="pos-vehicle-loading">
                            <span class="spinner-border spinner-border-sm me-2"></span>Memuat unit…
                        </div>
                    </div>
                </div>
            </section>

            {{-- 4 · Opsi --}}
            <section class="pos-step-card">
                <div class="pos-step-head">
                    <span class="pos-step-no">4</span>
                    <div>
                        <h5>Opsi Tambahan</h5>
                        <small>Bersifat opsional — struk langsung diperbarui</small>
                    </div>
                </div>
                <div class="pos-step-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="pos-label" for="pos-promo">Promo</label>
                            <select id="pos-promo" class="form-select"><option value="">— Tanpa promo —</option></select>
                            <div class="form-text text-danger d-none" id="pos-promo-note"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="pos-label" for="pos-driver-select">Sopir tetap</label>
                            <select id="pos-driver-select" class="form-select"><option value="">— Tanpa sopir tetap —</option></select>
                        </div>
                        <div class="col-md-4">
                            <label class="pos-label" for="pos-notes">Catatan</label>
                            <input type="text" id="pos-notes" class="form-control" maxlength="500" placeholder="Opsional">
                        </div>
                    </div>
                </div>
            </section>
        </div>

        {{-- ============ KOLOM KANAN: STRUK ============ --}}
        <div class="col-xl-4">
            <div class="pos-receipt">
                <div class="card pos-receipt-card">
                    <div class="pos-receipt-head">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6>STRUK SEWA</h6>
                                <small>Rincian harga langsung dari server</small>
                            </div>
                            <span class="badge bg-label-light" id="pos-receipt-days">—</span>
                        </div>
                    </div>
                    <div class="pos-receipt-body">
                        <div class="pos-progress">
                            <span class="pos-progress-item" id="pos-step-customer"><i class="ri-user-line"></i> Pelanggan</span>
                            <span class="pos-progress-item" id="pos-step-period"><i class="ri-calendar-line"></i> Periode</span>
                            <span class="pos-progress-item" id="pos-step-vehicle"><i class="ri-car-line"></i> Unit</span>
                        </div>

                        <div id="pos-receipt-lines">
                            <div class="pos-empty">
                                <i class="ri-receipt-line"></i>
                                Belum ada unit terpilih.
                            </div>
                        </div>

                        <div class="receipt-divider"></div>

                        <div class="pos-total-box">
                            <span class="lbl">TOTAL</span>
                            <span class="pos-total" id="pos-total">Rp 0</span>
                        </div>
                        <div class="pos-line mt-2">
                            <span class="lbl">Deposit (refundable)</span>
                            <span class="val" id="pos-deposit">Rp 0</span>
                        </div>

                        <button type="button" class="btn btn-primary btn-pos-save w-100 mt-3" id="pos-save" disabled>
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
</div>
@endsection

@section('customjs')
<script>
(function () {
    'use strict';

    const $ = (id) => document.getElementById(id);
    const state = { options: null, customer: null, vehicle: null, vehicles: [], quote: null, debounce: null };
    const rp = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[c]);

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
                `<option value="${esc(p.promo_id)}">${esc(p.promo_code)} — ${esc(p.description || '')}</option>`);
        });
        json.data.drivers.forEach((d) => {
            $('pos-driver-select').insertAdjacentHTML('beforeend',
                `<option value="${esc(d.driver_id)}">${esc(d.name)}${d.phone ? ' · ' + esc(d.phone) : ''}</option>`);
        });

        bindEvents();
        reloadVehicles();
    });

    function bindEvents() {
        $('pos-customer-search').addEventListener('input', (e) => {
            clearTimeout(state.debounce);
            state.debounce = setTimeout(() => searchCustomer(e.target.value), 300);
        });
        $('pos-btn-walkin').addEventListener('click', () => $('pos-walkin-form').classList.toggle('d-none'));
        $('pos-walkin-save').addEventListener('click', saveWalkin);
        $('pos-customer-clear').addEventListener('click', () => {
            state.customer = null;
            $('pos-customer-selected').classList.add('d-none');
            $('pos-customer-search-wrap').classList.remove('d-none');
            $('pos-customer-search').classList.remove('d-none');
            $('pos-customer-search').value = '';
            updateSaveState();
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
        const customers = json.data.customers || [];
        if (!customers.length) {
            const empty = document.createElement('div');
            empty.className = 'list-group-item pos-empty py-3';
            empty.textContent = 'Pelanggan tidak ditemukan.';
            box.appendChild(empty);
        }
        customers.forEach((c) => {
            const a = document.createElement('a');
            a.className = 'list-group-item list-group-item-action pos-customer-result';
            a.href = 'javascript:void(0);';
            a.textContent = c.label;
            a.addEventListener('click', () => pickCustomer(c));
            box.appendChild(a);
        });
        box.style.display = 'block';
    }

    function pickCustomer(c) {
        state.customer = c;
        $('pos-customer-results').style.display = 'none';
        $('pos-customer-search-wrap').classList.add('d-none');
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
        const valid = start && end && end > start;
        $('pos-date-hint').classList.toggle('d-none', !!valid);

        if (!valid) {
            $('pos-days').textContent = '— hari';
            state.vehicles = [];
            state.vehicle = null;
            renderVehicles();
            refreshQuote();
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
            empty.innerHTML = '<i class="ri-car-line"></i>Tidak ada unit bebas untuk rentang ini.';
            empty.classList.remove('d-none');
            return;
        }
        empty.classList.add('d-none');
        state.vehicles.forEach((v) => {
            const meta = [v.color, v.year, v.location, v.category].filter(Boolean).map(esc).join(' · ');
            const col = document.createElement('div');
            col.className = 'col-md-6 col-lg-4 col-xxl-3';
            col.innerHTML = `
                <div class="pos-vehicle-card" data-id="${esc(v.vehicle_id)}">
                    <div class="pos-vehicle-thumb">
                        <i class="ri-car-line"></i>
                        <span class="pos-check"><i class="ri-check-line"></i></span>
                    </div>
                    <div class="pos-vehicle-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="pos-plate">${esc(v.plate)}</span>
                            <span class="pos-rate">${rp(v.base_rate_per_day)}/hari</span>
                        </div>
                        <div class="pos-model text-truncate" title="${esc(v.model)}">${esc(v.model)}</div>
                        ${meta ? `<div class="pos-meta text-truncate mt-1" title="${meta}">${meta}</div>` : ''}
                        ${v.deposit_amount > 0 ? `<div class="pos-meta mt-1">Deposit ${rp(v.deposit_amount)}</div>` : ''}
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
            $('pos-receipt-lines').innerHTML = '<div class="pos-empty"><i class="ri-receipt-line"></i>Belum ada unit terpilih.</div>';
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
            `<div class="pos-line ${cls}"><span class="lbl">${esc(lbl)}</span><span class="val">${val}</span></div>`;
        let html = line(`${esc(state.vehicle.plate)} · ${esc(state.vehicle.model)} × ${q.rental_days} hari`, rp(q.total_base_price));
        html += line('Asuransi', rp(q.insurance_fee));
        if (q.driver_fee > 0) html += line('Sopir', rp(q.driver_fee));
        if (q.young_driver_fee > 0) html += line('Young driver', rp(q.young_driver_fee));
        if (q.discount_amount > 0) html += line('Promo', '−' + rp(q.discount_amount), 'discount');
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
        $('pos-step-customer').classList.toggle('done', !!state.customer);
        $('pos-step-period').classList.toggle('done', !!($('pos-start').value && $('pos-end').value > $('pos-start').value));
        $('pos-step-vehicle').classList.toggle('done', !!state.vehicle);
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

<form id="wizardForm">
    @csrf
    <input type="hidden" name="vehicle_id" id="vehicle_id" value="{{ $data['vehicle_id'] ?? '' }}">
    <input type="hidden" name="customer_id" value="{{ $data['customer_id'] ?? '' }}">

    <div class="row mb-4">
        <div class="col-12">
            <h5 class="mb-1">Detail Sewa</h5>
            <p class="text-muted small">Lengkapi detail periode sewa dan opsi tambahan</p>
        </div>
    </div>

    @if(empty($defaultLocation) && empty($stepPickupLoc))
        <div class="alert alert-warning d-flex align-items-center" role="alert">
            <i class="ri-alert-line me-2"></i>
            <div>Belum ada <strong>lokasi aktif</strong> — lokasi penjemputan/pengembalian wajib diisi dan tidak bisa lanjut. Tambahkan dulu di Master &raquo; Lokasi.</div>
        </div>
    @endif

    @php
        // Normalisasi nilai dari session: tanggal-only dari step 2 → datetime agar
        // terbaca flatpickr (dateFormat 'Y-m-d H:i' gagal parse nilai date-only).
        $startVal = (string) ($data['rental_start_date'] ?? '');
        $endVal = (string) ($data['rental_end_date'] ?? '');
        if ($startVal !== '' && !str_contains($startVal, ' ')) {
            $startVal .= ' 00:00';
        }
        if ($endVal !== '' && !str_contains($endVal, ' ')) {
            $endVal .= ' 23:59';
        }
    @endphp
    <div class="row">
        <div class="col-12 mb-3">
            <label class="form-label" for="flatpickr-range">Periode Sewa <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="flatpickr-range" data-range-start="#rental_start_date" data-range-end="#rental_end_date" data-range-time="true" value="{{ $startVal }} to {{ $endVal }}" placeholder="YYYY-MM-DD HH:MM to YYYY-MM-DD HH:MM" autocomplete="off" required>
            <input type="hidden" name="rental_start_date" id="rental_start_date" value="{{ $startVal }}">
            <input type="hidden" name="rental_end_date" id="rental_end_date" value="{{ $endVal }}">
        </div>
    </div>

    @php
        // Lokasi wajib (kolom NOT NULL) — remote data; preselect lokasi tersimpan
        // atau lokasi aktif pertama & samakan pengembalian dgn penjemputan bila
        // belum diisi agar admin cukup klik lanjut.
        $pickupLoc = $stepPickupLoc ?? $defaultLocation;
        $returnLoc = $stepReturnLoc ?? $pickupLoc;
        $pickupVal = $pickupLoc?->location_id ?? '';
        $returnVal = $returnLoc?->location_id ?? '';
        $pickupText = $pickupLoc ? $pickupLoc->location_name . ' - ' . $pickupLoc->city : '';
        $returnText = $returnLoc ? $returnLoc->location_name . ' - ' . $returnLoc->city : '';
    @endphp
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Lokasi Penjemputan <span class="text-danger">*</span></label>
            <div class="input-group input-group-sm mb-2">
                <span class="input-group-text"><i class="ri-search-line"></i></span>
                <input type="text" class="form-control remote-opt-search" data-target="#pickup_location_id"
                    placeholder="Ketik untuk cari lokasi..." autocomplete="off">
            </div>
            <select class="form-select remote-select" name="pickup_location_id" id="pickup_location_id"
                data-url="{{ route('rental.create.search-location') }}"
                data-empty-label="— Pilih lokasi —" data-current-text="{{ $pickupText }}">
                @if($pickupVal)
                    <option value="{{ $pickupVal }}" selected>{{ $pickupText }}</option>
                @else
                    <option value="" selected disabled>Memuat lokasi...</option>
                @endif
            </select>
            <div class="remote-err text-danger small mt-1" style="display:none">Gagal memuat data lokasi — periksa koneksi lalu ketik ulang di kolom cari.</div>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Lokasi Pengembalian <span class="text-danger">*</span></label>
            <div class="input-group input-group-sm mb-2">
                <span class="input-group-text"><i class="ri-search-line"></i></span>
                <input type="text" class="form-control remote-opt-search" data-target="#return_location_id"
                    placeholder="Ketik untuk cari lokasi..." autocomplete="off">
            </div>
            <select class="form-select remote-select" name="return_location_id" id="return_location_id"
                data-url="{{ route('rental.create.search-location') }}"
                data-empty-label="— Pilih lokasi —" data-current-text="{{ $returnText }}">
                @if($returnVal)
                    <option value="{{ $returnVal }}" selected>{{ $returnText }}</option>
                @else
                    <option value="" selected disabled>Memuat lokasi...</option>
                @endif
            </select>
            <div class="remote-err text-danger small mt-1" style="display:none">Gagal memuat data lokasi — periksa koneksi lalu ketik ulang di kolom cari.</div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="form-check form-switch mt-3">
                <input class="form-check-input" type="checkbox" id="is_with_driver" name="is_with_driver" value="1" {{ isset($data['is_with_driver']) && $data['is_with_driver'] ? 'checked' : '' }}>
                <label class="form-check-label" for="is_with_driver">Dengan Sopir</label>
            </div>
        </div>
    </div>

    {{-- PPN: ON/OFF + persen (default dari Pengaturan Umum) --}}
    @php
        // precedence diperbaiki (awalnya && dan || tercampur tanpa kurung)
        $taxOn = (isset($data['tax_percent']))
            ? (float) $data['tax_percent'] > 0
            : (bool) ($settings['tax_enabled'] ?? true);
    @endphp
    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="form-check form-switch mt-3">
                <input class="form-check-input" type="checkbox" id="tax_enabled" name="tax_enabled" value="1" {{ $taxOn ? 'checked' : '' }}>
                <label class="form-check-label" for="tax_enabled">Kenakan PPN</label>
            </div>
        </div>
        <div class="col-md-6 mb-3" id="taxPercentSection" style="display: {{ $taxOn ? 'block' : 'none' }};">
            <label class="form-label">Persentase PPN (%) <span class="text-danger">*</span></label>
            <div class="input-group">
                <input type="number" step="0.01" min="0" max="100" class="form-control" name="tax_percent_input" id="tax_percent_input"
                    value="{{ $data['tax_percent'] ?? ($settings['tax_enabled'] ?? true ? $settings['tax_percent'] : 0) }}">
                <span class="input-group-text">%</span>
            </div>
            <small class="text-muted">Default: {{ $settings['tax_percent'] }}% (Pengaturan Umum). Isi 0 untuk tanpa PPN.</small>
        </div>
    </div>

    {{-- Deposit: tampil bila deposit_enabled di settings --}}
    @if($settings['deposit_enabled'] ?? true)
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Deposit (Rp) @if($settings['deposit_required'] ?? false)
                        <span class="text-danger">*</span>
                    @endif</label>
                <input type="number" min="0" step="0.01" class="form-control" name="deposit_amount" id="deposit_amount"
                    value="{{ $data['deposit_amount'] ?? '' }}"
                    placeholder="{{ $settings['deposit_default'] > 0 ? 'Default: Rp ' . number_format($settings['deposit_default'], 0, ',', '.') . ' (dari Pengaturan Umum)' : '0 = tanpa deposit' }}"
                    @if($settings['deposit_required'] ?? false) required @endif>
                <small class="text-muted">Isi 0 bila sewa ini tanpa deposit. Deposit di luar total tagihan (jaminan, dikembalikan).</small>
            </div>
            <div class="col-md-6 mb-3 d-flex align-items-end">
                <small class="text-muted">
                    <i class="ri-information-line me-1"></i>
                    Deposit default kendaraan: <strong>Rp {{ number_format(optional(optional($vehicles)->firstWhere('vehicle_id', $data['vehicle_id'] ?? 0))->model->deposit_amount ?? 0, 0, ',', '.') }}</strong>
                </small>
            </div>
        </div>
    @else
        <input type="hidden" name="deposit_amount" value="0">
    @endif

    <div class="driver-section" style="display: {{ isset($data['is_with_driver']) && $data['is_with_driver'] ? 'block' : 'none' }}">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Pilih Sopir</label>
                @php
                    $driverVal = $data['driver_id'] ?? '';
                    $driverText = $stepDriver ? $stepDriver->full_name . ' - ' . $stepDriver->phone : '';
                @endphp
                <div class="input-group input-group-sm mb-2">
                    <span class="input-group-text"><i class="ri-search-line"></i></span>
                    <input type="text" class="form-control remote-opt-search" data-target="#driver_id"
                        placeholder="Ketik untuk cari sopir..." autocomplete="off">
                </div>
                <select class="form-select remote-select" name="driver_id" id="driver_id"
                    data-url="{{ route('rental.create.search-driver') }}"
                    data-allow-clear="1" data-empty-label="— Tanpa Sopir —" data-current-text="{{ $driverText }}">
                    @if($driverVal)
                        <option value="{{ $driverVal }}" selected>{{ $driverText }}</option>
                    @else
                        <option value="" selected>— Tanpa Sopir —</option>
                    @endif
                </select>
                <div class="remote-err text-danger small mt-1" style="display:none">Gagal memuat data sopir — periksa koneksi lalu ketik ulang di kolom cari.</div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Biaya Sopir/Hari</label>
                <input type="number" class="form-control" name="driver_fee" id="driver_fee" value="{{ $data['driver_fee'] ?? $settings['driver_fee_default'] }}">
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Kode Promo</label>
            @php
                $promoVal = $data['promo_id'] ?? '';
                $promoText = $stepPromo
                    ? $stepPromo->promo_code . ' - ' . ($stepPromo->discount_type == 'percentage' ? $stepPromo->discount_value . '%' : 'Rp ' . number_format($stepPromo->discount_value, 0, ',', '.'))
                    : '';
            @endphp
            <div class="input-group input-group-sm mb-2">
                <span class="input-group-text"><i class="ri-search-line"></i></span>
                <input type="text" class="form-control remote-opt-search" data-target="#promo_id"
                    placeholder="Ketik untuk cari promo..." autocomplete="off">
            </div>
            <select class="form-select remote-select" name="promo_id" id="promo_id"
                data-url="{{ route('rental.create.search-promo') }}"
                data-allow-clear="1" data-empty-label="— Tanpa Promo —" data-current-text="{{ $promoText }}">
                @if($promoVal)
                    <option value="{{ $promoVal }}" selected>{{ $promoText }}</option>
                @else
                    <option value="" selected>— Tanpa Promo —</option>
                @endif
            </select>
            <div class="remote-err text-danger small mt-1" style="display:none">Gagal memuat data promo — periksa koneksi lalu ketik ulang di kolom cari.</div>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Catatan</label>
            <textarea class="form-control" name="notes" rows="1">{{ $data['notes'] ?? '' }}</textarea>
        </div>
    </div>

    <div class="price-breakdown mt-4" id="priceSummary" style="display: none;">
        <h6 class="mb-3">Ringkasan Harga</h6>
        <div class="price-row">
            <span>Tarif Dasar/Hari</span>
            <span id="priceRowBaseRate">Rp 0</span>
        </div>
        <div class="price-row">
            <span>Durasi Sewa</span>
            <span id="priceRowDays">- hari</span>
        </div>
        <div class="price-row">
            <span>Total Tarif Dasar</span>
            <span id="priceRowTotalBase">Rp 0</span>
        </div>
        <div class="price-row">
            <span>Asuransi</span>
            <span id="priceRowInsurance">Rp 0</span>
        </div>
        <div class="price-row" id="driverPriceRow">
            <span>Biaya Sopir</span>
            <span id="priceRowDriver">Rp 0</span>
        </div>
        <div class="price-row">
            <span>Diskon</span>
            <span id="priceRowDiscount" class="text-success">Rp -0</span>
        </div>
        <div class="price-row" id="taxPriceRow">
            <span>Pajak (<span id="priceRowTaxPercent">11</span>%)</span>
            <span id="priceRowTax">Rp 0</span>
        </div>
        <div class="price-row" id="depositPriceRow">
            <span>Deposit <small class="text-muted">(jaminan, di luar tagihan)</small></span>
            <span id="priceRowDeposit">Rp 0</span>
        </div>
        <div class="price-row total">
            <span>Total</span>
            <span id="priceRowTotal">Rp 0</span>
        </div>
    </div>
</form>

<script>
    // Remote options TANPA transport AJAX select2 (pola yg sama & terbukti dgn
    // pencarian pelanggan step-1): search box → plain $.ajax → rebuild <option>
    // native. Mandiri (tidak tergantung script step lain), aman di-reload AJAX.
    function escOpt(s) {
        return $('<div>').text(s ?? '').html();
    }

    function renderRemoteOptions($select, items, more) {
        var cur = String($select.val() ?? '');
        var curText = $select.data('current-text') || '';
        var html = '';
        if ($select.data('allow-clear') == 1 || !cur) {
            html += '<option value="">' + escOpt($select.data('empty-label') || '— Pilih —') + '</option>';
        }
        var seen = {};
        (items || []).forEach(function(it) {
            var id = String(it.id);
            if (seen[id]) return;
            seen[id] = 1;
            if (id === cur) curText = it.text;
            html += '<option value="' + escOpt(id) + '">' + escOpt(it.text) + '</option>';
        });
        if (cur && !seen[cur] && curText) {
            html = '<option value="' + escOpt(cur) + '" selected>' + escOpt(curText) + '</option>' + html;
        }
        $select.html(html);
        $select.val(cur);
        if (more) {
            $select.append('<option value="" disabled>… hasil banyak, ketik untuk mempersempit …</option>');
        }
    }

    function fetchRemoteOptions($select, term) {
        if (!$select.length) return;
        var seq = ($select.data('seq') || 0) + 1;
        $select.data('seq', seq);
        var $err = $select.closest('.mb-3').find('.remote-err');
        $.ajax({
            url: $select.data('url'),
            type: 'GET',
            data: { q: term || '' },
            dataType: 'json'
        }).done(function(res) {
            if ($select.data('seq') !== seq) return;
            $err.hide();
            renderRemoteOptions($select, res.results || [], !!(res.pagination && res.pagination.more));
        }).fail(function() {
            if ($select.data('seq') !== seq) return;
            $err.show();
        });
    }

    var remoteOptTimer = null;
    $(document)
        .off('input.remoteOpt', '.remote-opt-search')
        .on('input.remoteOpt', '.remote-opt-search', function() {
            var $input = $(this);
            var $select = $($input.data('target'));
            clearTimeout(remoteOptTimer);
            remoteOptTimer = setTimeout(function() {
                fetchRemoteOptions($select, $input.val().trim());
            }, 300);
        });

    // Muat awal (20 pertama) utk tiap select remote
    $('.remote-select').each(function() { fetchRemoteOptions($(this), ''); });

    // Simpan tax_percent final ke field hidden sebelum submit step
    function syncTaxPercent() {
        const on = $('#tax_enabled').is(':checked');
        const val = on ? ($('#tax_percent_input').val() || 0) : 0;

        if ($('#hiddenTaxPercent').length === 0) {
            $('#wizardForm').append('<input type="hidden" name="tax_percent" id="hiddenTaxPercent">');
        }
        $('#hiddenTaxPercent').val(val);
    }

    $('#tax_enabled').off('change').on('change', function() {
        $('#taxPercentSection').toggle($(this).is(':checked'));
        syncTaxPercent();
        calculateTotal();
    });

    $('#tax_percent_input').off('input').on('input', function() {
        syncTaxPercent();
        calculateTotal();
    });

    $('#is_with_driver').off('change').on('change', function() {
        if ($(this).is(':checked')) {
            $('.driver-section').slideDown();
        } else {
            $('.driver-section').slideUp();
        }
        calculateTotal();
    });

    $('#rental_start_date, #rental_end_date, #vehicle_id, #driver_id, #driver_fee, #promo_id, #deposit_amount').off('change').on('change', function() {
        calculateTotal();
    });

    if ($('#rental_start_date').val() && $('#rental_end_date').val() && $('#vehicle_id').val()) {
        syncTaxPercent();
        if (typeof window.calculateTotal === 'function') {
            calculateTotal();
        }
    }
</script>

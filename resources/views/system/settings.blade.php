@extends('layouts.header')

@section('customcss')
    <style>
        /* ===== Page header ===== */
        .settings-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* ===== Tabs ===== */
        .settings-tabs {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.25rem;
        }

        .settings-tab {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            border: 1px solid #d9dee3;
            background: #fff;
            color: #697a8d;
            font-size: 0.8125rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s;
        }

        .settings-tab:hover {
            border-color: #666cff;
            color: #666cff;
        }

        .settings-tab.active {
            background: #666cff;
            border-color: #666cff;
            color: #fff;
        }

        /* ===== Cards ===== */
        .setting-card {
            height: 100%;
            border: 1px solid #e5e5e8;
        }

        .setting-card .card-header {
            background: #666cff14;
            border-bottom: 1px solid #e5e5e8;
            padding: 0.85rem 1.15rem;
        }

        .setting-card .card-header h6 {
            color: #4c52f0;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 1px;
        }

        .setting-card .card-body {
            padding: 1.15rem;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.8125rem;
            color: #3b4055;
            margin-bottom: 0.35rem;
        }

        .form-control, .form-select {
            font-size: 0.875rem;
        }

        .form-text {
            font-size: 0.72rem;
        }

        /* ===== Logo upload ===== */
        .logo-preview {
            width: 104px;
            height: 104px;
            border: 2px dashed #d9dee3;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #f7f7f9;
            position: relative;
        }

        .logo-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .logo-hint {
            font-size: 0.72rem;
        }

        /* ===== Switch ===== */
        .form-check.form-switch .form-check-input {
            width: 2.6rem;
            height: 1.35rem;
            cursor: pointer;
        }

        .form-check.form-switch.mb-0 {
            padding-left: 0;
        }

        .toggle-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 0.6rem 0.9rem;
            background: #f7f7f9;
            border: 1px solid #e5e5e8;
            border-radius: 8px;
        }

        .toggle-row + .toggle-row {
            margin-top: 0.6rem;
        }

        /* ===== Disabled state ===== */
        .card-body.dimmed {
            opacity: 0.55;
            pointer-events: none;
            user-select: none;
        }

        .badge-soft {
            font-size: 0.68rem;
            font-weight: 700;
            padding: 0.3rem 0.65rem;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-soft.on { background: #72e12829; color: #3ea016; }
        .badge-soft.off { background: #82868b1f; color: #6a6f85; }
    </style>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2 row-gap-3">
            <div class="d-flex flex-column justify-content-center">
                <h4 class="mb-1">{{ $title }}</h4>
                <p class="mb-0">{{ $subtitle }}</p>
            </div>
            <div class="settings-actions">
                <button type="submit" form="settingsForm" class="btn btn-primary">
                    <i class="ri-save-line me-1"></i> Simpan Pengaturan
                </button>
            </div>
        </div>

        @include('layouts.alert')

        {{-- ===== TABS ===== --}}
        <div class="settings-tabs">
            <button type="button" class="settings-tab active" data-tab="profil">
                <i class="ri-building-4-line"></i> Profil
            </button>
            <button type="button" class="settings-tab" data-tab="pajak">
                <i class="ri-calculator-line"></i> PPN &amp; Deposit
            </button>
            <button type="button" class="settings-tab" data-tab="operasional">
                <i class="ri-steering-2-line"></i> Operasional
            </button>
            <button type="button" class="settings-tab" data-tab="dokumen">
                <i class="ri-file-text-line"></i> Dokumen
            </button>
            <button type="button" class="settings-tab" data-tab="aplikasi">
                <i class="ri-global-line"></i> Aplikasi
            </button>
        </div>

        <form method="POST" action="{{ route('system.settings.update') }}" id="settingsForm"
            enctype="multipart/form-data">
            @csrf
            @method('PUT')

            {{-- ===== TAB: PROFIL ===== --}}
            <div class="tab-pane-block" data-pane="profil">
                <div class="row g-4">
                    <div class="col-md-5">
                        <div class="card setting-card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="ri-image-line me-1"></i> Logo Perusahaan</h6>
                            </div>
                            <div class="card-body text-center">
                                <div class="logo-preview mx-auto mb-3">
                                    @if(!empty($settings['company_logo']))
                                        <img src="{{ asset('storage/' . $settings['company_logo']) }}" alt="Logo"
                                            id="logoPreview">
                                    @else
                                        <i class="ri-image-add-line ri-2x text-muted" id="logoPreviewIcon"></i>
                                    @endif
                                </div>
                                <input type="file"
                                    class="form-control @error('company_logo') is-invalid @enderror" name="company_logo"
                                    accept="image/png,image/jpeg,image/svg+xml,image/webp" id="logoInput">
                                <div class="logo-hint text-muted mt-1">PNG/JPG/SVG/WebP &mdash; maks 2MB. Tampil di dokumen PDF.</div>
                                @error('company_logo')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                                @if(!empty($settings['company_logo']))
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="remove_logo" value="1"
                                            id="removeLogo">
                                        <label class="form-check-label small text-danger" for="removeLogo">
                                            Hapus logo saat ini
                                        </label>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="card setting-card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="ri-building-4-line me-1"></i> Identitas Perusahaan</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Nama Perusahaan <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="company_name"
                                        value="{{ old('company_name', $settings['company_name']) }}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Tagline</label>
                                    <input type="text" class="form-control" name="company_tagline"
                                        value="{{ old('company_tagline', $settings['company_tagline']) }}"
                                        placeholder="Muncul di dokumen PDF">
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Telepon</label>
                                        <input type="text" class="form-control" name="company_phone"
                                            value="{{ old('company_phone', $settings['company_phone']) }}">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="company_email"
                                            value="{{ old('company_email', $settings['company_email']) }}">
                                    </div>
                                    <div class="col-12 mb-0">
                                        <label class="form-label">Alamat</label>
                                        <textarea class="form-control" name="company_address" rows="2">{{ old('company_address', $settings['company_address']) }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== TAB: PPN & DEPOSIT ===== --}}
            <div class="tab-pane-block d-none" data-pane="pajak">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card setting-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="ri-calculator-line me-1"></i> PPN / Pajak</h6>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="tax_enabled" name="tax_enabled"
                                        value="1" {{ old('tax_enabled', $settings['tax_enabled'] ? '1' : '') ? 'checked' : '' }}>
                                </div>
                            </div>
                            <div class="card-body" id="taxSettingsBody">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Persentase (%) <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0" max="100" class="form-control"
                                                name="tax_percent" value="{{ old('tax_percent', $settings['tax_percent']) }}" required>
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Label Pajak <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="tax_label"
                                            value="{{ old('tax_label', $settings['tax_label']) }}" placeholder="PPN / GST / Tax" required>
                                    </div>
                                </div>
                                <div class="toggle-row">
                                    <div>
                                        <strong class="small">Default sewa baru</strong>
                                        <p class="mb-0 text-muted small">Sewa baru otomatis dikenakan pajak ini. Bisa dimatikan per sewa lewat wizard.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card setting-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="ri-secure-payment-line me-1"></i> Deposit</h6>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="deposit_enabled" name="deposit_enabled"
                                        value="1" {{ old('deposit_enabled', $settings['deposit_enabled'] ? '1' : '') ? 'checked' : '' }}>
                                </div>
                            </div>
                            <div class="card-body" id="depositSettingsBody">
                                <div class="mb-3">
                                    <label class="form-label">Deposit Default (Rp) <span class="text-danger">*</span></label>
                                    <input type="number" min="0" class="form-control" name="deposit_default"
                                        value="{{ old('deposit_default', $settings['deposit_default']) }}" required>
                                    <div class="form-text">Isi 0 bila perusahaan tidak memakai deposit.</div>
                                </div>
                                <div class="toggle-row">
                                    <div>
                                        <strong class="small">Deposit wajib</strong>
                                        <p class="mb-0 text-muted small">Sewa tidak bisa disimpan tanpa deposit &gt; 0.</p>
                                    </div>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" name="deposit_required" value="1"
                                            {{ old('deposit_required', $settings['deposit_required'] ? '1' : '') ? 'checked' : '' }}>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== TAB: OPERASIONAL ===== --}}
            <div class="tab-pane-block d-none" data-pane="operasional">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card setting-card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="ri-time-line me-1"></i> Keterlambatan</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-0">
                                        <label class="form-label">Biaya Telat / Jam (Rp) <span class="text-danger">*</span></label>
                                        <input type="number" min="0" class="form-control" name="late_hour_charge"
                                            value="{{ old('late_hour_charge', $settings['late_hour_charge']) }}" required>
                                        <div class="form-text">Dikenakan per jam keterlambatan pengembalian.</div>
                                    </div>
                                    <div class="col-md-6 mb-0">
                                        <label class="form-label">Toleransi Telat (menit) <span class="text-danger">*</span></label>
                                        <input type="number" min="0" class="form-control" name="overdue_grace_minutes"
                                            value="{{ old('overdue_grace_minutes', $settings['overdue_grace_minutes']) }}" required>
                                        <div class="form-text">Toleransi sebelum denda dihitung.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card setting-card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="ri-user-settings-line me-1"></i> Sopir</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-0">
                                        <label class="form-label">Biaya Sopir Default / Hari (Rp) <span class="text-danger">*</span></label>
                                        <input type="number" min="0" class="form-control" name="driver_fee_default"
                                            value="{{ old('driver_fee_default', $settings['driver_fee_default']) }}" required>
                                    </div>
                                    <div class="col-md-6 mb-0">
                                        <label class="form-label">Batas Usia Young Driver <span class="text-danger">*</span></label>
                                        <input type="number" min="17" max="30" class="form-control" name="young_driver_age"
                                            value="{{ old('young_driver_age', $settings['young_driver_age']) }}" required>
                                    </div>
                                    <div class="col-12 mt-3 mb-0">
                                        <label class="form-label">Biaya Young Driver Default (Rp) <span class="text-danger">*</span></label>
                                        <input type="number" min="0" class="form-control" name="young_driver_fee_default"
                                            value="{{ old('young_driver_fee_default', $settings['young_driver_fee_default']) }}" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== TAB: DOKUMEN ===== --}}
            <div class="tab-pane-block d-none" data-pane="dokumen">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card setting-card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="ri-calendar-deadline-line me-1"></i> Invoice</h6>
                            </div>
                            <div class="card-body">
                                <label class="form-label">Jatuh Tempo (hari) <span class="text-danger">*</span></label>
                                <input type="number" min="1" class="form-control" name="invoice_due_days"
                                    value="{{ old('invoice_due_days', $settings['invoice_due_days']) }}" required>
                                <div class="form-text">Dihitung dari tanggal terbit.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="card setting-card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="ri-file-text-line me-1"></i> Isi Dokumen PDF</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Catatan Footer Invoice</label>
                                    <textarea class="form-control" name="invoice_footer_note" rows="2">{{ old('invoice_footer_note', $settings['invoice_footer_note']) }}</textarea>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label">Syarat &amp; Ketentuan Kontrak</label>
                                    <textarea class="form-control" name="contract_terms" rows="4">{{ old('contract_terms', $settings['contract_terms']) }}</textarea>
                                    <div class="form-text">Ditampilkan di PDF kontrak sewa. Satu poin per baris.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== TAB: APLIKASI ===== --}}
            <div class="tab-pane-block d-none" data-pane="aplikasi">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card setting-card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="ri-money-dollar-circle-line me-1"></i> Mata Uang</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-0">
                                        <label class="form-label">Kode Mata Uang <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="currency"
                                            value="{{ old('currency', $settings['currency']) }}" required>
                                        <div class="form-text">Contoh: IDR, USD</div>
                                    </div>
                                    <div class="col-md-6 mb-0">
                                        <label class="form-label">Simbol <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="currency_symbol"
                                            value="{{ old('currency_symbol', $settings['currency_symbol']) }}" required>
                                        <div class="form-text">Contoh: Rp, $</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card setting-card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="ri-global-line me-1"></i> Zona Waktu</h6>
                            </div>
                            <div class="card-body">
                                <label class="form-label">Timezone <span class="text-danger">*</span></label>
                                <select class="form-select" name="timezone">
                                    @foreach(['Asia/Jakarta' => 'WIB — Asia/Jakarta', 'Asia/Makassar' => 'WITA — Asia/Makassar', 'Asia/Jayapura' => 'WIT — Asia/Jayapura'] as $val => $lbl)
                                        <option value="{{ $val }}" {{ old('timezone', $settings['timezone']) == $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Digunakan untuk timestamp dokumen &amp; log.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card setting-card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="ri-lock-line me-1"></i> Periode Akuntansi (Tutup Buku)</h6>
                            </div>
                            <div class="card-body">
                                <label class="form-label">Tanggal Tutup Buku</label>
                                <input type="text" class="form-control flatpickr-date" name="accounting_closing_date" autocomplete="off"
                                    value="{{ old('accounting_closing_date', $settings['accounting_closing_date']) }}">
                                <div class="form-text">Transaksi (sewa, pembayaran, jurnal) bertanggal sebelum tutup buku akan dikunci. Kosongkan bila belum ada.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card setting-card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="ri-database-2-line me-1"></i> Backup Database</h6>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small">Unduh cadangan seluruh tabel database (format SQL dump) tanpa membuka terminal server.</p>
                                @can('settings_edit')
                                    <a href="{{ route('system.backup') }}" class="btn btn-outline-primary">
                                        <i class="ri-download-cloud-line me-1"></i> Backup Database Sekarang
                                    </a>
                                @else
                                    <span class="text-muted small fst-italic">Butuh izin pengaturan untuk mencadangkan database.</span>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('customjs')
    <script>
        // ===== Tab switching =====
        $('.settings-tab').on('click', function() {
            $('.settings-tab').removeClass('active');
            $(this).addClass('active');
            const tab = $(this).data('tab');
            $('.tab-pane-block').addClass('d-none');
            $('.tab-pane-block[data-pane="' + tab + '"]').removeClass('d-none');
        });

        // ===== Toggle dim: PPN / Deposit =====
        function bindToggle(switchId, bodyId) {
            const apply = () => $('#' + bodyId).toggleClass('dimmed', !$('#' + switchId).is(':checked'));
            $('#' + switchId).on('change', apply);
            apply();
        }
        bindToggle('tax_enabled', 'taxSettingsBody');
        bindToggle('deposit_enabled', 'depositSettingsBody');

        // ===== Logo preview =====
        $('#logoInput').on('change', function() {
            const file = this.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = '<img src="' + e.target.result + '" alt="Logo">';
                const icon = $('#logoPreviewIcon');
                if (icon.length) icon.replaceWith(img);
                else $('#logoPreview').html(img);
            };
            reader.readAsDataURL(file);
        });
    </script>
@endsection

@section('appmodal')
    <div id="mymodal" class="modal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-indigo text-white">
                    <h5 class="modal-title"></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body"></div>
            </div>
        </div>
    </div>
@endsection

@section('notification')
    @include('layouts.notification')
@endsection
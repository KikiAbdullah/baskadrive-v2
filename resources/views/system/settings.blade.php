@extends('layouts.header')

@section('customcss')
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex flex-column justify-content-center mb-2">
            <h4 class="mb-1">{{ $title }}</h4>
            <p class="mb-6">{{ $subtitle }}</p>
        </div>

        @include('layouts.alert')

        <div class="row">
            <div class="col-md-9">
                <form method="POST" action="{{ route('system.settings.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="ri-building-line me-1"></i> Profil Perusahaan</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nama Perusahaan <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="company_name"
                                        value="{{ old('company_name', $settings['company_name'] ?? '') }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Telepon</label>
                                    <input type="text" class="form-control" name="company_phone"
                                        value="{{ old('company_phone', $settings['company_phone'] ?? '') }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="company_email"
                                        value="{{ old('company_email', $settings['company_email'] ?? '') }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Alamat</label>
                                    <textarea class="form-control" name="company_address"
                                        rows="2">{{ old('company_address', $settings['company_address'] ?? '') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="ri-money-dollar-circle-line me-1"></i> Keuangan &amp; Sewa</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Pajak (%) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control"
                                        name="tax_percent" value="{{ old('tax_percent', $settings['tax_percent'] ?? 11) }}"
                                        required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Deposit Default (Rp) <span class="text-danger">*</span></label>
                                    <input type="number" min="0" class="form-control" name="deposit_default"
                                        value="{{ old('deposit_default', $settings['deposit_default'] ?? 500000) }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Biaya Terlambat / Jam (Rp) <span class="text-danger">*</span></label>
                                    <input type="number" min="0" class="form-control" name="late_hour_charge"
                                        value="{{ old('late_hour_charge', $settings['late_hour_charge'] ?? 50000) }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Toleransi Terlambat (menit) <span class="text-danger">*</span></label>
                                    <input type="number" min="0" class="form-control" name="overdue_grace_minutes"
                                        value="{{ old('overdue_grace_minutes', $settings['overdue_grace_minutes'] ?? 60) }}"
                                        required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Jatuh Tempo Invoice (hari) <span class="text-danger">*</span></label>
                                    <input type="number" min="1" class="form-control" name="invoice_due_days"
                                        value="{{ old('invoice_due_days', $settings['invoice_due_days'] ?? 7) }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Mata Uang <span class="text-danger">*</span></label>
                                    <select class="form-select" name="currency">
                                        @foreach(['IDR' => 'Rupiah (IDR)', 'USD' => 'US Dollar (USD)'] as $val => $lbl)
                                            <option value="{{ $val }}"
                                                {{ ($settings['currency'] ?? 'IDR') == $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="ri-global-line me-1"></i> Aplikasi</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Zona Waktu <span class="text-danger">*</span></label>
                                    <select class="form-select" name="timezone">
                                        @foreach(['Asia/Jakarta' => 'WIB - Asia/Jakarta', 'Asia/Makassar' => 'WITA - Asia/Makassar', 'Asia/Jayapura' => 'WIT - Asia/Jayapura'] as $val => $lbl)
                                            <option value="{{ $val }}"
                                                {{ ($settings['timezone'] ?? 'Asia/Jakarta') == $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-end mb-4">
                        <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> Simpan
                            Pengaturan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('customjs')
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
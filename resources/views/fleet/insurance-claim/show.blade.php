@extends('layouts.header')

@section('customcss')
    <style>
        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.5rem 0;
            border-bottom: 1px dashed #e5e7eb;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #6b7280;
            font-weight: 500;
            white-space: nowrap;
        }

        .info-value {
            text-align: right;
            font-weight: 600;
        }
    </style>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2 row-gap-4">
            <div class="d-flex flex-column justify-content-center">
                <div class="d-flex align-items-center gap-2">
                    <h4 class="mb-1">{{ $item->claim_number ?? 'Klaim' }}</h4>
                    @include('components.claim-status-badge', ['status' => $item->status])
                </div>
                <p class="mb-6">{{ $subtitle }}</p>
            </div>
            <div class="d-flex align-content-center flex-wrap gap-4">
                <a href="{{ route('fleet.damage.index') }}" class="action-link-icon-text">
                    <i class="ri-arrow-left-line"></i>
                    <span class="fw-semibold text-uppercase">Kembali</span>
                </a>
                @if($item->damageReport)
                    <a href="{{ route('fleet.damage.show', $item->damageReport->damage_id) }}" class="action-link-icon-text">
                        <i class="ri-error-warning-line"></i>
                        <span class="fw-semibold text-uppercase">Lihat Kerusakan</span>
                    </a>
                @endif
            </div>
        </div>

        @include('layouts.alert')

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="ri-shield-check-line me-1"></i> Detail Klaim</h6>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <span class="info-label">Kendaraan</span>
                            <span class="info-value">{{ $item->damageReport?->vehicle?->license_plate ?? '-' }} - {{ $item->damageReport?->vehicle?->model?->brand?->brand_name ?? '' }} {{ $item->damageReport?->vehicle?->model?->model_name ?? '' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Penyewa</span>
                            <span class="info-value">{{ $item->rental?->customer?->full_name ?? '-' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Asuransi</span>
                            <span class="info-value">{{ $item->insurance_provider ?? '-' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Nomor Polis</span>
                            <span class="info-value">{{ $item->policy_number ?? '-' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Tanggal Klaim</span>
                            <span class="info-value">{{ $item->claim_date?->locale('id')->translatedFormat('d F Y') ?? '-' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Nilai Klaim</span>
                            <span class="info-value">{{ \App\Support\AppSettings::money($item->claim_amount ?? 0) }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Nilai Disetujui</span>
                            <span class="info-value">{{ \App\Support\AppSettings::money($item->approved_amount ?? 0) }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Tanggal Disetujui</span>
                            <span class="info-value">{{ $item->approved_date?->locale('id')->translatedFormat('d F Y') ?? '-' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Tanggal Dibayar</span>
                            <span class="info-value">{{ $item->paid_date?->locale('id')->translatedFormat('d F Y') ?? '-' }}</span>
                        </div>
                        @if($item->notes)
                            <div class="info-row">
                                <span class="info-label">Catatan</span>
                                <span class="info-value text-wrap">{{ $item->notes }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                @if($item->damageReport && $item->damageReport->photos->isNotEmpty())
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="ri-image-line me-1"></i> Foto Kerusakan</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                @foreach($item->damageReport->photos as $photo)
                                    <div class="col-md-4 mb-2">
                                        <img src="{{ asset('storage/' . $photo->photo_url) }}" class="img-fluid rounded"
                                            alt="{{ $photo->caption }}">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-md-4">
                @can('fleet_claim_manage')
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="ri-edit-line me-1"></i> Ubah Status Klaim</h6>
                        </div>
                        <div class="card-body">
                            {{-- FLE-12: closed kini status valid (enum DB diperluas) --}}
                            <select class="form-select" id="claimStatusSelect">
                                @foreach(['draft' => 'Draft', 'submitted' => 'Diajukan', 'under_review' => 'Review', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'paid' => 'Dibayar', 'closed' => 'Tutup'] as $val => $lbl)
                                    <option value="{{ $val }}" {{ $item->status == $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                @endforeach
                            </select>
                            <div class="mt-3" id="approvedAmountWrap" style="display: none;">
                                <label class="form-label" for="approvedAmountInput">Nilai Disetujui (Rp)</label>
                                <input type="number" class="form-control" id="approvedAmountInput" min="0" step="0.01"
                                    max="9999999999.99" value="{{ $item->approved_amount ?? $item->claim_amount }}">
                            </div>
                            <button type="button" class="btn btn-primary w-100 mt-3" id="btnUpdateClaimStatus">Simpan Status</button>
                            <div class="form-text mt-2">
                                Status <strong>Dibayar</strong> mencatat jurnal pencairan (Dr Bank / Cr Pendapatan Klaim)
                                secara atomik dengan perubahan status (FLE-09).
                            </div>
                        </div>
                    </div>
                @endcan
            </div>
        </div>
    </div>
@endsection

@section('customjs')
    <script>
        function handleAjaxError(xhr) {
            let msg = 'Terjadi kesalahan. Coba lagi.';
            if (xhr.responseJSON && xhr.responseJSON.msg) {
                msg = xhr.responseJSON.msg;
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            } else if (xhr.status === 0) {
                msg = 'Koneksi gagal. Periksa jaringan Anda.';
            }
            Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
        }

        const statusSelect = $('#claimStatusSelect');
        function toggleApprovedAmount() {
            $('#approvedAmountWrap').toggle(['approved', 'paid'].includes(statusSelect.val()));
        }
        statusSelect.on('change', toggleApprovedAmount);
        toggleApprovedAmount();

        $('#btnUpdateClaimStatus').on('click', function() {
            const btn = this;
            const payload = {
                _token: '{{ csrf_token() }}',
                status: statusSelect.val()
            };
            if (['approved', 'paid'].includes(statusSelect.val())) {
                payload.approved_amount = $('#approvedAmountInput').val();
            }
            Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            $.ajax({
                url: '{{ route('fleet.insurance-claim.update-status', $item->claim_id) }}',
                type: 'PUT',
                data: payload,
                dataType: 'JSON',
                complete: () => Swal.close(),
                success: function(res) {
                    Swal.fire({
                        icon: res.status ? 'success' : 'error',
                        title: res.status ? 'Berhasil' : 'Gagal',
                        text: res.msg,
                        didClose: () => { if (res.status) location.reload(); }
                    });
                },
                error: handleAjaxError
            });
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

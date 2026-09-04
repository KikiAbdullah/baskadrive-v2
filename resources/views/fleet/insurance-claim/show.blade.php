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
            <div class="d-flex align-content-center flex-wrap gap-2">
                <a href="{{ route('fleet.insurance-claim.index') }}" class="btn btn-outline-secondary">
                    <i class="ri-arrow-left-line me-1"></i> Kembali
                </a>
            </div>
        </div>

        @include('layouts.alert')

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="ri-file-text-line me-1"></i> Detail Klaim</h6>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <span class="info-label">No. Klaim</span>
                            <span class="info-value">{{ $item->claim_number ?? '-' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Provider</span>
                            <span class="info-value">{{ $item->insurance_provider ?? '-' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">No. Polis</span>
                            <span class="info-value">{{ $item->policy_number ?? '-' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Tanggal Klaim</span>
                            <span class="info-value">{{ $item->claim_date?->format('d F Y') ?? '-' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Nilai Klaim</span>
                            <span class="info-value">Rp {{ number_format($item->claim_amount ?? 0, 0, ',', '.') }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Nilai Disetujui</span>
                            <span class="info-value">Rp {{ number_format($item->approved_amount ?? 0, 0, ',', '.') }}</span>
                        </div>
                        @if($item->approved_date)
                            <div class="info-row">
                                <span class="info-label">Tanggal Disetujui</span>
                                <span class="info-value">{{ $item->approved_date?->format('d F Y') }}</span>
                            </div>
                        @endif
                        @if($item->notes)
                            <div class="info-row">
                                <span class="info-label">Catatan</span>
                                <span class="info-value text-wrap">{{ $item->notes }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                @if($item->damageReport)
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="ri-error-warning-line me-1"></i> Kerusakan Terkait</h6>
                            <a href="{{ route('fleet.damage.show', $item->damageReport->damage_id) }}"
                                class="btn btn-sm btn-outline-primary">Detail</a>
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <span class="info-label">Kendaraan</span>
                                <span class="info-value">{{ $item->damageReport->vehicle?->license_plate ?? '-' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Jenis</span>
                                <span class="info-value">{{ ucfirst(str_replace('_', ' ', $item->damageReport->damage_type ?? '-')) }}</span>
                            </div>
                            @include('components.damage-status-badge', ['status' => $item->damageReport->status])
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="ri-edit-line me-1"></i> Ubah Status</h6>
                    </div>
                    <div class="card-body">
                        <select class="form-select" id="statusSelect">
                            @foreach(['draft' => 'Draft', 'submitted' => 'Diajukan', 'under_review' => 'Review', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'paid' => 'Dibayar', 'closed' => 'Tutup'] as $val => $lbl)
                                <option value="{{ $val }}" {{ $item->status == $val ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>

                        <div class="mt-2" id="approvedAmountWrap" style="display:{{ $item->status == 'approved' ? 'block' : 'none' }}">
                            <label class="form-label">Nilai Disetujui (Rp)</label>
                            <input type="number" class="form-control" id="approvedAmountInput"
                                value="{{ $item->approved_amount ?? $item->claim_amount }}">
                        </div>

                        <button type="button" class="btn btn-primary w-100 mt-3" id="btnUpdateStatus">Simpan Status</button>
                    </div>
                </div>

                @if($item->rental)
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="ri-shopping-cart-line me-1"></i> Sewa Terkait</h6>
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <span class="info-label">Kode Sewa</span>
                                <span class="info-value">{{ $item->rental->rental_code ?? '-' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Pelanggan</span>
                                <span class="info-value">{{ $item->rental->customer?->full_name ?? '-' }}</span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('customjs')
    <script>
        $('#statusSelect').on('change', function() {
            $('#approvedAmountWrap').toggle($(this).val() === 'approved');
        });

        $('#btnUpdateStatus').on('click', function() {
            const data = {
                _token: '{{ csrf_token() }}',
                status: $('#statusSelect').val()
            };
            if ($('#statusSelect').val() === 'approved') {
                data.approved_amount = $('#approvedAmountInput').val();
            }
            $.ajax({
                url: '{{ url('fleet/insurance-claim') }}/{{ $item->claim_id }}/status',
                type: 'PUT',
                data: data,
                dataType: 'JSON',
                success: function(res) {
                    if (res.status) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: res.msg,
                            didClose: () => location.reload()
                        });
                    }
                }
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
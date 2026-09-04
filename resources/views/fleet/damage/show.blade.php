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
                    <h4 class="mb-1">{{ $item->vehicle?->license_plate ?? 'Kerusakan' }}</h4>
                    @include('components.damage-status-badge', ['status' => $item->status])
                </div>
                <p class="mb-6">{{ $subtitle }}</p>
            </div>
            <div class="d-flex align-content-center flex-wrap gap-2">
                <a href="{{ route('fleet.damage.index') }}" class="btn btn-outline-secondary">
                    <i class="ri-arrow-left-line me-1"></i> Kembali
                </a>
                @if(!$item->insuranceClaim)
                    <a href="{{ route('fleet.insurance-claim.create') }}?damage_id={{ $item->damage_id }}"
                        class="btn btn-outline-primary">
                        <i class="ri-shield-check-line me-1"></i> Ajukan Klaim
                    </a>
                @endif
            </div>
        </div>

        @include('layouts.alert')

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="ri-error-warning-line me-1"></i> Detail Kerusakan</h6>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <span class="info-label">Kendaraan</span>
                            <span class="info-value">{{ $item->vehicle?->license_plate }} - {{ $item->vehicle?->model?->brand?->brand_name }} {{ $item->vehicle?->model?->model_name }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Jenis</span>
                            <span class="info-value">{{ ucfirst(str_replace('_', ' ', $item->damage_type ?? '-')) }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Severity</span>
                            <span class="info-value">{{ ucfirst($item->severity ?? '-') }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Lokasi</span>
                            <span class="info-value">{{ $item->location ?? '-' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Tanggal Lapor</span>
                            <span class="info-value">{{ $item->reported_date?->format('d F Y') }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Deskripsi</span>
                            <span class="info-value text-wrap">{{ $item->description ?? '-' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Estimasi Biaya</span>
                            <span class="info-value">Rp {{ number_format($item->repair_cost_estimate ?? 0, 0, ',', '.') }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Biaya Aktual</span>
                            <span class="info-value">Rp {{ number_format($item->actual_repair_cost ?? 0, 0, ',', '.') }}</span>
                        </div>
                        @if($item->notes)
                            <div class="info-row">
                                <span class="info-label">Catatan</span>
                                <span class="info-value text-wrap">{{ $item->notes }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="ri-image-line me-1"></i> Foto Kerusakan</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnUploadPhoto">
                            <i class="ri-upload-line"></i> Unggah
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row" id="photoList">
                            @forelse($item->photos as $photo)
                                <div class="col-md-4 mb-2" id="photo-{{ $photo->photo_id }}">
                                    <img src="{{ asset('storage/' . $photo->photo_url) }}" class="img-fluid rounded"
                                        alt="{{ $photo->caption }}">
                                    <button type="button" class="btn btn-sm btn-outline-danger mt-1 btn-del-photo"
                                        data-id="{{ $photo->photo_id }}">Hapus</button>
                                </div>
                            @empty
                                <div class="col-12 text-muted">Belum ada foto.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="ri-edit-line me-1"></i> Ubah Status</h6>
                    </div>
                    <div class="card-body">
                        <select class="form-select" id="statusSelect">
                            @foreach(['reported' => 'Laporan', 'inspected' => 'Diinspeksi', 'approved' => 'Disetujui', 'in_repair' => 'Diperbaiki', 'repaired' => 'Selesai', 'rejected' => 'Ditolak', 'closed' => 'Tutup'] as $val => $lbl)
                                <option value="{{ $val }}" {{ $item->status == $val ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-primary w-100 mt-3" id="btnUpdateStatus">Simpan Status</button>
                    </div>
                </div>

                @if($item->insuranceClaim)
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="ri-shield-check-line me-1"></i> Klaim Asuransi</h6>
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <span class="info-label">No. Klaim</span>
                                <span class="info-value">{{ $item->insuranceClaim->claim_number ?? '-' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Provider</span>
                                <span class="info-value">{{ $item->insuranceClaim->insurance_provider ?? '-' }}</span>
                            </div>
                            <a href="{{ route('fleet.insurance-claim.show', $item->insuranceClaim->claim_id) }}"
                                class="btn btn-sm btn-outline-primary w-100 mt-2">Lihat Klaim</a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('customjs')
    <script>
        $('#btnUpdateStatus').on('click', function() {
            $.ajax({
                url: '{{ url('fleet/damage') }}/{{ $item->damage_id }}/status',
                type: 'PUT',
                data: {
                    _token: '{{ csrf_token() }}',
                    status: $('#statusSelect').val()
                },
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

        $('#btnUploadPhoto').on('click', function() {
            Swal.fire({
                title: 'Unggah Foto',
                html: '<input type="file" id="photoInput" class="form-control" accept="image/*">',
                showCancelButton: true,
                confirmButtonText: 'Unggah',
                preConfirm: () => {
                    const fd = new FormData();
                    fd.append('_token', '{{ csrf_token() }}');
                    fd.append('photo', $('#photoInput')[0].files[0]);
                    return $.ajax({
                        url: '{{ url('fleet/damage') }}/{{ $item->damage_id }}/photo',
                        type: 'POST',
                        data: fd,
                        processData: false,
                        contentType: false,
                        dataType: 'JSON'
                    }).fail(function() {
                        Swal.showValidationMessage('Gagal mengunggah');
                    });
                }
            }).then((result) => {
                if (result.value && result.value.status) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: result.value.msg,
                        didClose: () => location.reload()
                    });
                }
            });
        });

        $('body').on('click', '.btn-del-photo', function() {
            const id = $(this).data('id');
            Swal.fire({
                icon: 'warning',
                title: 'Hapus foto?',
                showCancelButton: true,
                confirmButtonText: 'Ya',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '{{ url('fleet/damage') }}/photo/' + id,
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        dataType: 'JSON',
                        success: function(res) {
                            if (res.status) {
                                $('#photo-' + id).remove();
                                Swal.fire('Berhasil', res.msg, 'success');
                            }
                        }
                    });
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
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
            <div class="d-flex align-content-center flex-wrap gap-4">
                <a href="{{ route('fleet.damage.index') }}" class="action-link-icon-text">
                    <i class="ri-arrow-left-line"></i>
                    <span class="fw-semibold text-uppercase">Kembali</span>
                </a>
                @if(!$item->insuranceClaim)
                    <a href="{{ route('fleet.insurance-claim.create') }}?damage_id={{ $item->damage_id }}"
                        class="action-link-icon-text">
                        <i class="ri-shield-check-line"></i>
                        <span class="fw-semibold text-uppercase">Ajukan Klaim</span>
                    </a>
                @endif
                @if($item->rental_id && ((float) ($item->actual_repair_cost ?? 0) > 0 || (float) ($item->repair_cost_estimate ?? 0) > 0))
                    <button type="button" class="action-link-icon-text border-0 bg-transparent" id="btnBillRenter">
                        <i class="ri-money-dollar-circle-line"></i>
                        <span class="fw-semibold text-uppercase">Tagihkan ke Penyewa</span>
                    </button>
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
                        <span class="text-muted small">Multi-file didukung</span>
                    </div>
                    <div class="card-body">
                        <div id="photoDropzone"
                            class="border rounded p-4 text-center mb-3 bg-label-secondary"
                            style="border-style: dashed; cursor: pointer;">
                            <i class="ri-upload-cloud-2-line ri-2x d-block mb-1"></i>
                            <span class="fw-semibold">Tarik foto ke sini atau klik untuk memilih banyak file sekaligus</span>
                            <div class="text-muted small">PNG/JPG/MAX 5MB per file</div>
                            <input type="file" id="photoFileInput" multiple accept="image/*" class="d-none">
                        </div>
                        <div class="row" id="photoList">
                            @forelse($item->photos as $photo)
                                <div class="col-md-4 mb-2 position-relative" id="photo-{{ $photo->photo_id }}">
                                    <img src="{{ asset('storage/' . $photo->photo_url) }}" class="img-fluid rounded"
                                        alt="{{ $photo->caption }}">
                                    <button type="button"
                                        class="btn btn-sm btn-outline-danger btn-del-photo position-absolute"
                                        style="top: 4px; right: 16px; border: none; background: rgba(255,255,255,0.9); border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; padding: 0;"
                                        data-id="{{ $photo->photo_id }}" title="Hapus foto">
                                        <i class="ri-close-circle-line"></i>
                                    </button>
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

        $('#btnBillRenter').on('click', function() {
            const btn = this;
            Swal.fire({
                icon: 'question',
                title: 'Tagihkan biaya kerusakan?',
                text: 'Otomatis membuat catatan denda pada sewa terkait.',
                showCancelButton: true,
                confirmButtonText: 'Ya, Tagihkan',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    $(btn).prop('disabled', true);
                    $.ajax({
                        url: '{{ url('fleet/damage') }}/{{ $item->damage_id }}/bill-renter',
                        type: 'PUT',
                        data: { _token: '{{ csrf_token() }}' },
                        dataType: 'JSON',
                        complete: () => $(btn).prop('disabled', false),
                        success: function(res) {
                            Swal.fire({
                                icon: res.status ? 'success' : 'error',
                                title: res.status ? 'Berhasil' : 'Gagal',
                                text: res.msg,
                                didClose: () => { if (res.status) location.reload(); }
                            });
                        }
                    });
                }
            });
        });

        function uploadPhotos(files) {
            if (!files || !files.length) return;
            const fd = new FormData();
            fd.append('_token', '{{ csrf_token() }}');
            for (let i = 0; i < files.length; i++) {
                fd.append('photos[]', files[i]);
            }
            Swal.fire({
                title: 'Mengunggah ' + files.length + ' foto...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            $.ajax({
                url: '{{ url('fleet/damage') }}/{{ $item->damage_id }}/photo',
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'JSON',
                success: function(res) {
                    if (res.status) {
                        Swal.fire({ icon: 'success', title: 'Berhasil', text: res.msg, timer: 1200, showConfirmButton: false, didClose: () => location.reload() });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: res.msg });
                    }
                },
                error: function() {
                    Swal.fire({ icon: 'error', title: 'Gagal', text: 'Salah satu atau seluruh file gagal diunggah.' });
                }
            });
        }

        const dz = $('#photoDropzone'), fileInput = $('#photoFileInput');
        dz.on('click', () => fileInput.trigger('click'));
        fileInput.on('change', function() { uploadPhotos(this.files); });
        dz.on('dragover dragenter', function(e) {
            e.preventDefault(); e.stopPropagation();
            $(this).addClass('border-primary bg-label-primary');
        });
        dz.on('dragleave drop', function(e) {
            e.preventDefault(); e.stopPropagation();
            $(this).removeClass('border-primary bg-label-primary');
            if (e.type === 'drop') uploadPhotos(e.originalEvent.dataTransfer.files);
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
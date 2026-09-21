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
                @can('fleet_claim_manage')
                    @if(!$item->insuranceClaim)
                        <a href="{{ route('fleet.insurance-claim.create') }}?damage_id={{ $item->damage_id }}"
                            class="action-link-icon-text">
                            <i class="ri-shield-check-line"></i>
                            <span class="fw-semibold text-uppercase">Ajukan Klaim</span>
                        </a>
                    @endif
                @endcan
                @can('fleet_bill_renter')
                    @if($item->rental_id && ((float) ($item->actual_repair_cost ?? 0) > 0 || (float) ($item->repair_cost_estimate ?? 0) > 0))
                        <button type="button" class="action-link-icon-text border-0 bg-transparent" id="btnBillRenter">
                            <i class="ri-money-dollar-circle-line"></i>
                            <span class="fw-semibold text-uppercase">Tagihkan ke Penyewa</span>
                        </button>
                    @endif
                @endcan
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
                            {{-- FLE-14: tanggal berbahasa Indonesia --}}
                            <span class="info-label">Tanggal Lapor</span>
                            <span class="info-value">{{ $item->reported_date?->locale('id')->translatedFormat('d F Y') ?? '-' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Deskripsi</span>
                            <span class="info-value text-wrap">{{ $item->description ?? '-' }}</span>
                        </div>
                        <div class="info-row">
                            {{-- FLE-14: dua desimal selaras modul Keuangan (FIN-14) --}}
                            <span class="info-label">Estimasi Biaya</span>
                            <span class="info-value">{{ \App\Support\AppSettings::money($item->repair_cost_estimate ?? 0) }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Biaya Aktual</span>
                            <span class="info-value">{{ \App\Support\AppSettings::money($item->actual_repair_cost ?? 0) }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Penanggung Tagihan</span>
                            <span class="info-value">
                                @if($item->fines->isNotEmpty())
                                    @foreach($item->fines as $fine)
                                        <span class="badge bg-label-{{ $fine->status === 'paid' ? 'success' : ($fine->status === 'waived' ? 'secondary' : 'warning') }}">
                                            {{ \App\Support\AppSettings::money($fine->amount) }} ({{ ucfirst($fine->status) }})
                                        </span>
                                    @endforeach
                                @else
                                    <span class="text-muted">Belum ditagihkan</span>
                                @endif
                            </span>
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
                        <span class="text-muted small">Maksimal 10 foto — multi-file didukung</span>
                    </div>
                    <div class="card-body">
                        @can('fleet_damage_add')
                            @if(in_array($item->status, ['reported', 'inspected', 'assessment', 'approved', 'in_repair', 'repair_in_progress']))
                                <div id="photoDropzone"
                                    class="border rounded p-4 text-center mb-3 bg-label-secondary"
                                    style="border-style: dashed; cursor: pointer;">
                                    <i class="ri-upload-cloud-2-line ri-2x d-block mb-1"></i>
                                    <span class="fw-semibold">Tarik foto ke sini atau klik untuk memilih banyak file sekaligus</span>
                                    <div class="text-muted small">PNG/JPG/MAX 5MB per file</div>
                                    <input type="file" id="photoFileInput" multiple accept="image/*" class="d-none">
                                </div>
                            @else
                                <div class="alert alert-warning py-2">
                                    <i class="ri-lock-line me-1"></i> Laporan berstatus
                                    <strong>{{ ucfirst($item->status) }}</strong> — bukti foto dibekukan (FLE-13).
                                </div>
                            @endif
                        @endcan
                        <div class="row" id="photoList">
                            @forelse($item->photos as $photo)
                                <div class="col-md-4 mb-2 position-relative" id="photo-{{ $photo->photo_id }}">
                                    <img src="{{ asset('storage/' . $photo->photo_url) }}" class="img-fluid rounded"
                                        alt="{{ $photo->caption }}">
                                    @can('fleet_damage_manage')
                                        <button type="button"
                                            class="btn btn-sm btn-outline-danger btn-del-photo position-absolute"
                                            style="top: 4px; right: 16px; border: none; background: rgba(255,255,255,0.9); border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; padding: 0;"
                                            data-id="{{ $photo->photo_id }}" title="Hapus foto">
                                            <i class="ri-close-circle-line"></i>
                                        </button>
                                    @endcan
                                </div>
                            @empty
                                <div class="col-12 text-muted">Belum ada foto.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                @can('fleet_damage_manage')
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="ri-edit-line me-1"></i> Ubah Status</h6>
                        </div>
                        <div class="card-body">
                            {{-- FLE-02: daftar status identik dengan enum database & badge --}}
                            <select class="form-select" id="statusSelect">
                                @foreach(['reported' => 'Laporan', 'inspected' => 'Diinspeksi', 'assessment' => 'Asesmen', 'approved' => 'Disetujui', 'in_repair' => 'Dalam Perbaikan', 'repair_in_progress' => 'Dalam Perbaikan (Lama)', 'repaired' => 'Selesai Diperbaiki', 'rejected' => 'Ditolak', 'claimed_insurance' => 'Klaim Asuransi', 'written_off' => 'Hilang (Total Loss)', 'closed' => 'Tutup'] as $val => $lbl)
                                    <option value="{{ $val }}" {{ $item->status == $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-primary w-100 mt-3" id="btnUpdateStatus">Simpan Status</button>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="ri-money-dollar-euro-circle-line me-1"></i> Biaya Perbaikan Aktual</h6>
                        </div>
                        <div class="card-body">
                            {{-- FLE-12: jalur koreksi biaya aktual — dasar penagihan penyewa --}}
                            <label class="form-label" for="actualCostInput">Nilai (Rp)</label>
                            <input type="number" class="form-control" id="actualCostInput" min="0" step="0.01"
                                max="9999999999.99" value="{{ $item->actual_repair_cost ?? '' }}">
                            <button type="button" class="btn btn-outline-primary w-100 mt-3" id="btnSaveActualCost">Simpan Biaya Aktual</button>
                        </div>
                    </div>
                @endcan

                @if($item->insuranceClaim)
                    <div class="card {{ @can('fleet_damage_manage') ? 'mt-3' : '' }}">
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

        function ajaxWithSpinner(options) {
            if (options.button) $(options.button).prop('disabled', true);
            $.ajax(Object.assign({}, options, { complete: function() { if (options.button) $(options.button).prop('disabled', false); } }));
        }

        $('#btnUpdateStatus').on('click', function() {
            const btn = this;
            Swal.fire({
                title: 'Memproses...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            $.ajax({
                url: '{{ route('fleet.damage.update-status', $item->damage_id) }}',
                type: 'PUT',
                data: {
                    _token: '{{ csrf_token() }}',
                    status: $('#statusSelect').val()
                },
                dataType: 'JSON',
                complete: () => Swal.close(),
                success: function(res) {
                    if (res.status) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: res.msg,
                            didClose: () => location.reload()
                        });
                    }
                },
                error: handleAjaxError
            });
        });

        $('#btnSaveActualCost').on('click', function() {
            const btn = this;
            const val = $('#actualCostInput').val();
            if (val === '' || Number(val) < 0) {
                Swal.fire({ icon: 'warning', title: 'Nilai tidak valid', text: 'Isi biaya aktual dengan angka ≥ 0.' });
                return;
            }
            Swal.fire({ title: 'Menyimpan...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            $.ajax({
                url: '{{ route('fleet.damage.update-cost', $item->damage_id) }}',
                type: 'PUT',
                data: { _token: '{{ csrf_token() }}', actual_repair_cost: val },
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

        $('#btnBillRenter').on('click', function() {
            const btn = this;
            Swal.fire({
                icon: 'question',
                title: 'Tagihkan biaya kerusakan?',
                html: 'Membuat tagihan denda pada sewa terkait dan <strong>jurnal piutang</strong> (akrual).<br>Penyewa tidak dapat ditagih dua kali untuk kerusakan yang sama.',
                showCancelButton: true,
                confirmButtonText: 'Ya, Tagihkan',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    $.ajax({
                        url: '{{ route('fleet.damage.bill', $item->damage_id) }}',
                        type: 'PUT',
                        data: { _token: '{{ csrf_token() }}' },
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
                }
            });
        });

        function uploadPhotos(files) {
            if (!files || !files.length) return;
            if (files.length > 10) {
                Swal.fire({ icon: 'warning', title: 'Terlalu banyak file', text: 'Maksimal 10 foto per laporan.' });
                return;
            }
            const fd = new FormData();
            fd.append('_token', '{{ csrf_token() }}');
            for (let i = 0; i < files.length; i++) {
                fd.append('photos[]', files[i]);
            }
            Swal.fire({ title: 'Mengunggah ' + files.length + ' foto...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            $.ajax({
                url: '{{ route('fleet.damage.photo.upload', $item->damage_id) }}',
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'JSON',
                complete: () => Swal.close(),
                success: function(res) {
                    if (res.status) {
                        Swal.fire({ icon: 'success', title: 'Berhasil', text: res.msg, timer: 1200, showConfirmButton: false, didClose: () => location.reload() });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: res.msg });
                    }
                },
                error: handleAjaxError
            });
        }

        const dz = $('#photoDropzone'), fileInput = $('#photoFileInput');
        if (dz.length) {
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
        }

        $('body').on('click', '.btn-del-photo', function() {
            const id = $(this).data('id');
            Swal.fire({
                icon: 'warning',
                title: 'Hapus foto?',
                text: 'File foto juga dihapus dari penyimpanan.',
                showCancelButton: true,
                confirmButtonText: 'Ya',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '{{ url('fleet/damage') }}/photo/' + id,
                        type: 'DELETE',
                        data: { _token: '{{ csrf_token() }}' },
                        dataType: 'JSON',
                        success: function(res) {
                            if (res.status) {
                                $('#photo-' + id).remove();
                                Swal.fire('Berhasil', res.msg, 'success');
                            } else {
                                Swal.fire({ icon: 'error', title: 'Gagal', text: res.msg });
                            }
                        },
                        error: handleAjaxError
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

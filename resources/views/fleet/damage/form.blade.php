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
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="{{ route('fleet.damage.store') }}">
                            @csrf
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Kendaraan <span class="text-danger">*</span></label>
                                    <select class="form-select select2" name="vehicle_id" required>
                                        <option value="">Pilih Kendaraan</option>
                                        @foreach($vehicles as $v)
                                            <option value="{{ $v->vehicle_id }}">
                                                {{ $v->license_plate }} -
                                                {{ $v->model->brand->brand_name ?? '' }}
                                                {{ $v->model->model_name ?? '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Sewa Terkait</label>
                                    <select class="form-select select2" name="rental_id">
                                        <option value="">Tidak ada</option>
                                        @foreach($rentals as $r)
                                            <option value="{{ $r->rental_id }}">
                                                {{ $r->rental_code }} -
                                                {{ $r->customer->full_name ?? '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tanggal Lapor <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control flatpickr-date" name="reported_date" autocomplete="off"
                                        value="{{ date('Y-m-d') }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Jenis Kerusakan <span class="text-danger">*</span></label>
                                    <select class="form-select" name="damage_type" required>
                                        <option value="">Pilih</option>
                                        {{-- FLE-11: enum sesuai skema database --}}
                                        @foreach(['exterior' => 'Eksterior/Body', 'interior' => 'Interior', 'mechanical' => 'Mekanikal/Mesin', 'electrical' => 'Kelistrikan', 'glass' => 'Kaca', 'tire' => 'Ban', 'other' => 'Lainnya'] as $val => $lbl)
                                            <option value="{{ $val }}">{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Severity <span class="text-danger">*</span></label>
                                    <select class="form-select" name="severity" required>
                                        {{-- FLE-11: total_loss ikut tersedia (ada di enum DB) --}}
                                        @foreach(['minor' => 'Minor', 'moderate' => 'Moderate', 'severe' => 'Severe', 'total_loss' => 'Total Loss (Hilang)'] as $val => $lbl)
                                            <option value="{{ $val }}">{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Lokasi Kerusakan</label>
                                    <input type="text" class="form-control" name="location">
                                </div>                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Estimasi Biaya (Rp)</label>
                                    <input type="number" class="form-control" name="repair_cost_estimate" min="0" step="0.01" max="9999999999.99">
                                </div>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Deskripsi</label>
                                    <textarea class="form-control" name="description" rows="3"></textarea>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Catatan</label>
                                    <textarea class="form-control" name="notes" rows="2"></textarea>
                                </div>
                            </div>

                            <div class="text-end">
                                <a href="{{ route('fleet.damage.index') }}" class="btn btn-outline-secondary">Batal</a>
                                <button type="submit" class="btn btn-primary">Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('customjs')
    <script>
        $('.select2').select2();
        if (window.initFlatpickr) { window.initFlatpickr(); }
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
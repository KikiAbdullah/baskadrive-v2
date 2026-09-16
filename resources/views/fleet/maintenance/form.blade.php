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
                        <form method="POST"
                            action="{{ $item ? route('fleet.maintenance.update', $item->maintenance_id) : route('fleet.maintenance.store') }}">
                            @csrf
                            @if($item)
                                @method('PUT')
                            @endif

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Kendaraan <span class="text-danger">*</span></label>
                                    <select class="form-select select2" name="vehicle_id" required>
                                        <option value="">Pilih Kendaraan</option>
                                        @foreach($vehicles as $v)
                                            <option value="{{ $v->vehicle_id }}"
                                                {{ $item && $item->vehicle_id == $v->vehicle_id ? 'selected' : '' }}>
                                                {{ $v->license_plate }} -
                                                {{ $v->model->brand->brand_name ?? '' }}
                                                {{ $v->model->model_name ?? '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Jenis Maintenance <span class="text-danger">*</span></label>
                                    <select class="form-select select2" name="maintenance_type_id" required>
                                        <option value="">Pilih Jenis</option>
                                        @foreach($types as $t)
                                            <option value="{{ $t->type_id }}"
                                                {{ $item && $item->maintenance_type_id == $t->type_id ? 'selected' : '' }}>
                                                {{ $t->type_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Bengkel</label>
                                    <select class="form-select select2" name="workshop_id">
                                        <option value="">Pilih Bengkel</option>
                                        @foreach($workshops as $w)
                                            <option value="{{ $w->workshop_id }}"
                                                {{ $item && $item->workshop_id == $w->workshop_id ? 'selected' : '' }}>
                                                {{ $w->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tanggal Dijadwalkan <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control flatpickr-date" name="scheduled_date" autocomplete="off"
                                        value="{{ $item ? $item->scheduled_date?->format('Y-m-d') : '' }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tanggal Aktual</label>
                                    <input type="text" class="form-control flatpickr-date" name="actual_date" autocomplete="off"
                                        value="{{ $item ? $item->actual_date?->format('Y-m-d') : '' }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Odometer (KM)</label>
                                    <input type="number" class="form-control" name="current_mileage"
                                        value="{{ $item->current_mileage ?? '' }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Biaya (Rp)</label>
                                    <input type="number" class="form-control" name="cost" value="{{ $item->cost ?? '' }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Next Maintenance (KM)</label>
                                    <input type="number" class="form-control" name="next_maintenance_km"
                                        value="{{ $item->next_maintenance_km ?? '' }}">
                                </div>
                                @if($item)
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            @foreach(['scheduled' => 'Terjadwal', 'overdue' => 'Terlambat', 'in_progress' => 'Proses', 'completed' => 'Selesai', 'cancelled' => 'Batal'] as $val => $lbl)
                                                <option value="{{ $val }}"
                                                    {{ $item->status == $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div class="col-12 mb-3">
                                    <label class="form-label">Deskripsi</label>
                                    <textarea class="form-control" name="description"
                                        rows="2">{{ $item->description ?? '' }}</textarea>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Catatan</label>
                                    <textarea class="form-control" name="notes" rows="2">{{ $item->notes ?? '' }}</textarea>
                                </div>
                            </div>

                            <div class="text-end">
                                <a href="{{ route('fleet.maintenance.index') }}" class="btn btn-outline-secondary">Batal</a>
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
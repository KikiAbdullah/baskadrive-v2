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
                <div class="card">
                    <div class="card-body">
                        <form method="POST"
                            action="{{ route('rental.update', $rental->rental_id) }}">
                            @csrf
                            @method('PUT')

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>{{ $rental->rental_code }}</strong></p>
                                    <p class="mb-0 text-muted small">
                                        {{ $rental->customer?->full_name ?? '-' }} &mdash;
                                        {{ $rental->vehicle?->license_plate ?? '-' }}
                                        ({{ $rental->vehicle?->model?->brand?->brand_name ?? '' }}
                                        {{ $rental->vehicle?->model?->model_name ?? '' }})
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="form-select" name="status" required>
                                        @foreach(['reserved' => 'Reservasi', 'ongoing' => 'Berjalan', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'] as $val => $lbl)
                                            <option value="{{ $val }}" {{ $rental->status == $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tanggal Mulai Sewa <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control datepicker" name="rental_start_date"
                                        value="{{ $rental->rental_start_date?->format('Y-m-d') }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tanggal Selesai Sewa <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control datepicker" name="rental_end_date"
                                        value="{{ $rental->rental_end_date?->format('Y-m-d') }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Lokasi Penjemputan</label>
                                    <select class="form-select select2" name="pickup_location_id">
                                        <option value="">Pilih Lokasi</option>
                                        @foreach($locations as $loc)
                                            <option value="{{ $loc->location_id }}"
                                                {{ $rental->pickup_location_id == $loc->location_id ? 'selected' : '' }}>
                                                {{ $loc->location_name }} - {{ $loc->city }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Lokasi Pengembalian</label>
                                    <select class="form-select select2" name="return_location_id">
                                        <option value="">Pilih Lokasi</option>
                                        @foreach($locations as $loc)
                                            <option value="{{ $loc->location_id }}"
                                                {{ $rental->return_location_id == $loc->location_id ? 'selected' : '' }}>
                                                {{ $loc->location_name }} - {{ $loc->city }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch mt-3">
                                        <input class="form-check-input" type="checkbox" id="is_with_driver"
                                            name="is_with_driver" value="1"
                                            {{ $rental->is_with_driver ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_with_driver">Dengan Sopir</label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Sopir</label>
                                    <select class="form-select select2" name="driver_id">
                                        <option value="">Pilih Sopir</option>
                                        @foreach($drivers as $driver)
                                            <option value="{{ $driver->driver_id }}"
                                                {{ $rental->driver_id == $driver->driver_id ? 'selected' : '' }}>
                                                {{ $driver->full_name }} - {{ $driver->phone }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Kode Promo</label>
                                    <select class="form-select select2" name="promo_id">
                                        <option value="">Tidak ada promo</option>
                                        @foreach($promos as $promo)
                                            <option value="{{ $promo->promo_id }}"
                                                {{ $rental->promo_id == $promo->promo_id ? 'selected' : '' }}>
                                                {{ $promo->promo_code }} -
                                                {{ $promo->discount_type == 'percentage' ? $promo->discount_value . '%' : 'Rp ' . number_format($promo->discount_value, 0, ',', '.') }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Catatan</label>
                                    <textarea class="form-control" name="notes" rows="1">{{ $rental->notes ?? '' }}</textarea>
                                </div>
                            </div>

                            <div class="alert alert-info small">
                                <i class="ri-information-line me-1"></i>
                                Total biaya akan dihitung ulang otomatis saat perubahan disimpan.
                            </div>

                            <div class="text-end">
                                <a href="{{ route('rental.show', $rental->rental_id) }}"
                                    class="btn btn-outline-secondary">Batal</a>
                                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
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
        if ($.fn.datepicker) {
            $('.datepicker').each(function() {
                if (!$(this).data('datepicker')) {
                    $(this).datepicker({
                        format: 'yyyy-mm-dd',
                        autoclose: true,
                        todayHighlight: true,
                        orientation: 'bottom auto'
                    });
                }
            });
        }
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
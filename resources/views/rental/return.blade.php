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
                        <form method="POST" action="{{ route('rental.detail.return.store', $rental->rental_id) }}">
                            @csrf
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tanggal & Waktu Pengembalian <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" name="return_date"
                                        value="{{ now()->format('Y-m-d\TH:i') }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Odometer Akhir (KM)</label>
                                    <input type="number" class="form-control" name="return_mileage">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Level Bahan Bakar</label>
                                    <select class="form-select" name="fuel_level">
                                        <option value="full">Full</option>
                                        <option value="three_quarter">3/4</option>
                                        <option value="half">1/2</option>
                                        <option value="quarter">1/4</option>
                                        <option value="empty">Kosong</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Kondisi Kendaraan <span class="text-danger">*</span></label>
                                    <select class="form-select" name="vehicle_condition" required>
                                        <option value="good">Baik</option>
                                        <option value="minor_damage">Kerusakan Ringan</option>
                                        <option value="damage">Kerusakan Berat</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Biaya Tambahan (Rp)</label>
                                    <input type="number" step="0.01" min="0" class="form-control" name="extra_charge" value="0">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Refund Deposit (Rp)</label>
                                    <input type="number" step="0.01" min="0" class="form-control" name="deposit_refund" value="0">
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Deskripsi Kerusakan</label>
                                    <textarea class="form-control" name="damage_description" rows="3"></textarea>
                                </div>
                            </div>

                            <div class="text-end">
                                <a href="{{ route('rental.show', $rental->rental_id) }}"
                                    class="btn btn-outline-secondary">Batal</a>
                                <button type="submit" class="btn btn-primary">Proses Pengembalian</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="ri-information-line me-1"></i> Informasi Sewa</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-1"><strong>{{ $rental->rental_code }}</strong></p>
                        <p class="mb-1">{{ $rental->customer?->full_name ?? '-' }}</p>
                        <p class="mb-1">{{ $rental->vehicle?->license_plate ?? '-' }} -
                            {{ $rental->vehicle?->model?->brand?->brand_name ?? '' }}
                            {{ $rental->vehicle?->model?->model_name ?? '' }}</p>
                        <hr>
                        <p class="mb-1">Mulai: {{ $rental->rental_start_date?->format('d/m/Y H:i') }}</p>
                        <p class="mb-0">Rencana Selesai: {{ $rental->rental_end_date?->format('d/m/Y H:i') }}</p>
                    </div>
                </div>
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
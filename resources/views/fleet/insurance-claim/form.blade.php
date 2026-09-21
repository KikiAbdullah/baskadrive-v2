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
                        <form method="POST" action="{{ route('fleet.insurance-claim.store') }}">
                            @csrf
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Laporan Kerusakan <span class="text-danger">*</span></label>
                                    <select class="form-select select2" name="damage_id" required>
                                        <option value="">Pilih Kerusakan</option>
                                        @foreach($damages as $d)
                                            <option value="{{ $d->damage_id }}"
                                                {{ request('damage_id') == $d->damage_id ? 'selected' : '' }}>
                                                #{{ $d->damage_id }} -
                                                {{ $d->vehicle?->license_plate ?? 'Tanpa kendaraan' }} -
                                                {{ ucfirst(str_replace('_', ' ', $d->damage_type ?? '')) }}
                                                ({{ ucfirst($d->severity ?? '') }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Asuransi <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="insurance_provider" maxlength="100"
                                        placeholder="Nama perusahaan asuransi" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nomor Polis</label>
                                    <input type="text" class="form-control" name="policy_number" maxlength="50">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tanggal Klaim <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control flatpickr-date" name="claim_date" autocomplete="off"
                                        value="{{ date('Y-m-d') }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nilai Klaim (Rp) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="claim_amount" min="0.01" step="0.01"
                                        max="9999999999.99" required>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Catatan</label>
                                    <textarea class="form-control" name="notes" rows="2" maxlength="2000"></textarea>
                                </div>
                            </div>

                            <div class="text-end">
                                <a href="{{ route('fleet.damage.index') }}" class="btn btn-outline-secondary">Batal</a>
                                <button type="submit" class="btn btn-primary">Ajukan Klaim</button>
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

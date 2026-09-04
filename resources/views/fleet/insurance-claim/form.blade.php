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
                                    <select class="form-select select2" name="damage_id" id="damageSelect" required>
                                        <option value="">Pilih Kerusakan</option>
                                        @foreach($damages as $d)
                                            <option value="{{ $d->damage_id }}"
                                                {{ request('damage_id') == $d->damage_id ? 'selected' : '' }}>
                                                {{ $d->vehicle?->license_plate ?? '-' }} -
                                                {{ ucfirst(str_replace('_', ' ', $d->damage_type ?? '')) }} -
                                                Rp {{ number_format($d->repair_cost_estimate ?? 0, 0, ',', '.') }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Provider Asuransi <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="insurance_provider" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">No. Polis</label>
                                    <input type="text" class="form-control" name="policy_number">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tanggal Klaim <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control datepicker" name="claim_date"
                                        value="{{ date('Y-m-d') }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nilai Klaim (Rp) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="claim_amount" required>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Catatan</label>
                                    <textarea class="form-control" name="notes" rows="2"></textarea>
                                </div>
                            </div>

                            <div class="text-end">
                                <a href="{{ route('fleet.insurance-claim.index') }}" class="btn btn-outline-secondary">Batal</a>
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

        @if(request('damage_id'))
            $('#damageSelect').trigger('change');
        @endif
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
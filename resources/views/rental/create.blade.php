@extends('layouts.header')

@section('customcss')
<style>
    .wizard-step { display: none; }
    .wizard-step.active { display: block; }
    .step-indicator { display: flex; justify-content: center; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; }
    .step-block { display: flex; align-items: center; }
    .step-item { display: flex; align-items: center; }
    .step-number { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.875rem; border: 2px solid #d9dee3; color: #697a8d; background: #fff; transition: all 0.2s; flex-shrink: 0; }
    .step-item.active .step-number { border-color: #666cff; background: #666cff; color: #fff; }
    .step-item.completed .step-number { border-color: #28c76f; background: #28c76f; color: #fff; }
    .step-label { margin: 0 0.5rem; font-size: 0.75rem; color: #697a8d; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
    .step-connector { width: 50px; height: 2px; background: #d9dee3; margin: 0 0.75rem; flex-shrink: 0; transition: background 0.2s; }
    .step-connector.done { background: #28c76f; }
    .customer-card { cursor: pointer; transition: all 0.2s; border: 2px solid transparent; }
    .customer-card:hover { border-color: #666cff; }
    .customer-card.selected { border-color: #666cff; background: #f0f1ff; }
    .vehicle-card { cursor: pointer; transition: all 0.2s; border: 2px solid transparent; }
    .vehicle-card:hover { border-color: #666cff; }
    .vehicle-card.selected { border-color: #666cff; background: #f0f1ff; }
    .price-breakdown { background: #f8f9fa; border-radius: 0.5rem; padding: 1.5rem; }
    .price-breakdown .price-row { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #e9ecef; }
    .price-breakdown .price-row.total { border-bottom: none; font-size: 1.25rem; font-weight: 700; color: #666cff; }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1">{{ $title }}</h4>
            <p class="mb-6">{{ $subtitle }}</p>
        </div>
    </div>

    @include('layouts.alert')

    <div class="card">
        <div class="card-body">
            <div class="step-indicator" id="stepIndicator">
                <div class="step-block">
                    <div class="step-item active" data-step="1">
                        <div class="step-number">1</div>
                        <div class="step-label">Pelanggan</div>
                    </div>
                </div>
                <div class="step-connector" data-connector="1"></div>
                <div class="step-block">
                    <div class="step-item" data-step="2">
                        <div class="step-number">2</div>
                        <div class="step-label">Kendaraan</div>
                    </div>
                </div>
                <div class="step-connector" data-connector="2"></div>
                <div class="step-block">
                    <div class="step-item" data-step="3">
                        <div class="step-number">3</div>
                        <div class="step-label">Detail Sewa</div>
                    </div>
                </div>
                <div class="step-connector" data-connector="3"></div>
                <div class="step-block">
                    <div class="step-item" data-step="4">
                        <div class="step-number">4</div>
                        <div class="step-label">Konfirmasi</div>
                    </div>
                </div>
            </div>

            <div id="wizardContent">
                @include('rental._step-1')
            </div>
        </div>
    </div>
</div>
@endsection

@section('customjs')
<script>
    let currentStep = {{ $step }};

    function updateStepIndicator(step) {
        $('.step-indicator .step-item').each(function() {
            const s = parseInt($(this).data('step'));
            $(this).removeClass('active completed');
            if (s === step) {
                $(this).addClass('active');
            } else if (s < step) {
                $(this).addClass('completed');
            }
        });
        $('.step-indicator .step-connector').each(function() {
            const c = parseInt($(this).data('connector'));
            $(this).toggleClass('done', c < step);
        });
    }

    function loadStep(step) {
        Swal.fire({ text: 'Loading...', showConfirmButton: false, allowOutsideClick: false });
        $.ajax({
            url: @json(route('rental.create.step', ['step' => '__STEP__'])).replace('__STEP__', step),
            type: 'GET',
            dataType: 'JSON',
            success: function(response) {
                Swal.close();
                if (response.status) {
                    $('#wizardContent').html(response.view);
                    currentStep = response.step;
                    updateStepIndicator(currentStep);
                    initStepHandlers();
                    initSelect2();
                    initDatepicker();
                }
            },
            error: function() {
                Swal.close();
                Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal memuat langkah.' });
            }
        });
    }

    function saveStep(step, formData) {
        Swal.fire({ text: 'Menyimpan...', showConfirmButton: false, allowOutsideClick: false });
        formData.append('step', step);
        formData.append('_token', '{{ csrf_token() }}');
        $.ajax({
            url: '{{ route("rental.create") }}',
            type: 'POST',
            data: formData,
            dataType: 'JSON',
            processData: false,
            contentType: false,
            success: function(response) {
                Swal.close();
                if (response.status) {
                    loadStep(response.step);
                }
            },
            error: function(jqXHR) {
                Swal.close();
                if (jqXHR.status === 422) {
                    const errors = jqXHR.responseJSON.errors;
                    let msg = '';
                    $.each(errors, function(key, val) { msg += val[0] + '<br>'; });
                    Swal.fire({ icon: 'error', title: 'Validasi Gagal', html: msg });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan.' });
                }
            }
        });
    }

    function initStepHandlers() {
        $('.btn-next-step').off('click').on('click', function() {
            const step = parseInt($(this).data('step'));
            const form = $('#wizardForm')[0];
            const formData = new FormData(form);
            saveStep(step, formData);
        });

        $('.btn-prev-step').off('click').on('click', function() {
            const step = parseInt($(this).data('step'));
            loadStep(step);
        });
    }

    function calculateTotal() {
        const vehicleId = $('#vehicle_id').val();
        const startDate = $('#rental_start_date').val();
        const endDate = $('#rental_end_date').val();
        const withDriver = $('#is_with_driver').is(':checked');
        const driverId = $('#driver_id').val();
        const promoId = $('#promo_id').val();
        const depositAmount = $('#deposit_amount').val();
        const taxEnabled = $('#tax_enabled').length ? $('#tax_enabled').is(':checked') : true;
        const taxPercent = taxEnabled ? ($('#tax_percent_input').val() ?? null) : 0;

        if (!vehicleId || !startDate || !endDate) return;

        $.ajax({
            url: '{{ route("rental.create.calculate-total") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                vehicle_id: vehicleId,
                rental_start_date: startDate,
                rental_end_date: endDate,
                is_with_driver: withDriver,
                driver_id: driverId,
                promo_id: promoId,
                deposit_amount: depositAmount,
                tax_percent: taxPercent,
            },
            dataType: 'JSON',
            success: function(response) {
                if (response.status) {
                    const d = response.data;
                    $('#priceRowBaseRate').text('Rp ' + formatNumber(d.base_rate_per_day));
                    $('#priceRowDays').text(d.rental_days + ' hari');
                    $('#priceRowTotalBase').text('Rp ' + formatNumber(d.total_base_price));
                    $('#priceRowInsurance').text('Rp ' + formatNumber(d.insurance_fee));
                    $('#priceRowDriver').text('Rp ' + formatNumber(d.driver_fee));
                    $('#priceRowDiscount').text('Rp -' + formatNumber(d.discount_amount));
                    $('#priceRowTaxPercent').text(d.tax_percent);
                    $('#priceRowTax').text('Rp ' + formatNumber(d.tax_amount));
                    $('#priceRowDeposit').text('Rp ' + formatNumber(d.deposit_amount));
                    $('#priceRowTotal').text('Rp ' + formatNumber(d.total_amount));
                    $('#taxPriceRow').toggle(parseFloat(d.tax_percent) > 0);
                    $('#depositPriceRow').toggle(parseFloat(d.deposit_amount) > 0);
                    $('#priceSummary').slideDown();
                }
            }
        });
    }

    function formatNumber(num) {
        if (num === null || num === undefined || isNaN(num)) return '0';
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function initSelect2() {
        if ($.fn.select2) {
            $('.select2').each(function() {
                if (!$(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2();
                }
            });
        }
    }

    function initDatepicker() {
        if (window.initFlatpickr) {
            window.initFlatpickr(document.getElementById('wizardContent'));
        }
    }

    $(document).ready(function() {
        initStepHandlers();
        initSelect2();
        initDatepicker();
    });
</script>
@endsection

@section('appmodal')
<div id="mymodal" class="modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
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
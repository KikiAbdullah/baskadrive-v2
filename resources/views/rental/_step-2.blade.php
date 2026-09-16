<form id="wizardForm">
    @csrf
    <input type="hidden" name="customer_id" value="{{ $data['customer_id'] ?? '' }}">
    <input type="hidden" name="vehicle_id" id="vehicle_id" value="{{ $data['vehicle_id'] ?? '' }}">

    <div class="row mb-4">
        <div class="col-12">
            <h5 class="mb-1">Pilih Kendaraan</h5>
            <p class="text-muted small">Tentukan periode sewa lalu pilih kendaraan yang tersedia</p>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <label class="form-label">Tanggal Mulai <span class="text-danger">*</span></label>
            <input type="text" class="form-control flatpickr-date" name="rental_start_date" id="filterStartDate" value="{{ \Illuminate\Support\Str::before($data['rental_start_date'] ?? '', ' ') }}" placeholder="YYYY-MM-DD" autocomplete="off" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Tanggal Selesai <span class="text-danger">*</span></label>
            <input type="text" class="form-control flatpickr-date" name="rental_end_date" id="filterEndDate" value="{{ \Illuminate\Support\Str::before($data['rental_end_date'] ?? '', ' ') }}" placeholder="YYYY-MM-DD" autocomplete="off" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">&nbsp;</label>
            <button type="button" class="btn btn-primary d-block" id="checkAvailability">
                <i class="ri-search-line"></i> Cek Ketersediaan
            </button>
        </div>
    </div>

    <div class="wizard-scroll" id="vehicleList">
        <div class="text-center py-4 text-muted">
            <i class="ri-car-line ri-3x mb-2 d-block"></i>
            Pilih tanggal untuk melihat kendaraan tersedia
        </div>
    </div>
</form>

<script>
    function renderVehicles(response, selectedVehicle) {
        let html = '';

        // FASE 3 audit sewa: escape semua nilai dinamis (anti XSS dari data master)
        const esc = (s) => $('<div>').text(s ?? '').html();

        // Pilihan lama tidak lagi tersedia untuk window tanggal baru → kosongkan
        if (selectedVehicle && !(response || []).some(function(v) { return String(v.id) === String(selectedVehicle); })) {
            selectedVehicle = '';
            $('#vehicle_id').val('');
            $('#footerSelection').text('');
        }

        if (!response.length) {
            html = '<div class="text-center py-4 text-muted"><i class="ri-close-circle-line ri-3x mb-2 d-block"></i>Tidak ada kendaraan tersedia untuk tanggal tersebut.</div>';
        } else {
            html = '<div class="row">';
            $.each(response, function(i, v) {
                const isSelected = String(selectedVehicle) === String(v.id);
                html += '<div class="col-md-6 mb-3">';
                html += '<div class="card vehicle-card h-100 ' + (isSelected ? 'selected' : '') + '" data-id="' + esc(v.id) + '" data-price="' + esc(v.base_price_per_day || 0) + '">';
                html += '<div class="card-body">';
                html += '<div class="d-flex justify-content-between">';
                html += '<div class="flex-grow-1 me-2">';
                html += '<div class="d-flex justify-content-between align-items-start mb-1">';
                html += '<h6 class="mb-0">' + esc(v.license_plate || '-') + '</h6>';
                html += '<div class="form-check ms-2">';
                html += '<input class="form-check-input vehicle-radio" type="radio" name="_vehicle_radio" value="' + esc(v.id) + '" ' + (isSelected ? 'checked' : '') + '>';
                html += '</div>';
                html += '</div>';
                html += '<p class="text-muted small mb-2">' + esc((v.brand_name || '') + ' ' + (v.model_name || '') + ' (' + (v.year || '-') + ')') + '</p>';
                html += '<div class="d-flex flex-wrap gap-1 mb-2">';
                html += '<span class="badge bg-label-info"><i class="ri-palette-line"></i> ' + esc(v.color || '-') + '</span>';
                html += '<span class="badge bg-label-secondary"><i class="ri-steering-2-line"></i> ' + esc(v.transmission || '-') + '</span>';
                html += '<span class="badge bg-label-warning"><i class="ri-group-line"></i> ' + esc((v.seat_capacity || '-') + ' kursi') + '</span>';
                html += '</div>';
                html += '<table class="table table-sm table-borderless mb-0">';
                html += '<tr><td class="ps-0 text-muted small">Harga/hari</td><td class="pe-0 text-end"><strong>Rp ' + (v.base_price_per_day ? formatNumber(v.base_price_per_day) : '0') + '</strong></td></tr>';
                html += '<tr><td class="ps-0 text-muted small">Deposit</td><td class="pe-0 text-end"><strong>Rp ' + (v.deposit_amount ? formatNumber(v.deposit_amount) : '0') + '</strong></td></tr>';
                html += '</table>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
            });
            html += '</div>';
        }

        $('#vehicleList').html(html);

        $('.vehicle-card').off('click').on('click', function() {
            $('.vehicle-card').removeClass('selected');
            $('.vehicle-radio').prop('checked', false);
            $(this).addClass('selected');
            $(this).find('.vehicle-radio').prop('checked', true);
            $('#vehicle_id').val($(this).data('id'));
            const plate = $(this).find('h6').first().text().trim();
            const price = $(this).data('price') || 0;
            $('#footerSelection').text('· ' + plate + ' (Rp ' + formatNumber(price) + '/hari)');
        });

        const $init = $('.vehicle-card.selected').first();
        if ($init.length) {
            const p = $init.find('h6').first().text().trim();
            $('#footerSelection').text('· ' + p + ' (Rp ' + formatNumber($init.data('price') || 0) + '/hari)');
        }
    }

    $('#checkAvailability').on('click', function() {
        const startDate = $('#filterStartDate').val();
        const endDate = $('#filterEndDate').val();
        const selectedVehicle = $('#vehicle_id').val();

        if (!startDate || !endDate) {
            Swal.fire({ icon: 'warning', title: 'Peringatan', text: 'Pilih tanggal mulai dan selesai.' });
            return;
        }

        if (startDate >= endDate) {
            Swal.fire({ icon: 'warning', title: 'Peringatan', text: 'Tanggal selesai harus setelah tanggal mulai.' });
            return;
        }

        Swal.fire({ text: 'Memeriksa ketersediaan...', showConfirmButton: false, allowOutsideClick: false });

        $.ajax({
            url: '{{ route("rental.create.available-vehicles") }}',
            type: 'GET',
            data: { start_date: startDate, end_date: endDate },
            dataType: 'JSON',
            success: function(response) {
                Swal.close();
                renderVehicles(response, selectedVehicle);
            },
            error: function() {
                Swal.close();
                Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal memeriksa ketersediaan.' });
            }
        });
    });

    if ($('#filterStartDate').val() && $('#filterEndDate').val()) {
        $('#checkAvailability').trigger('click');
    }
</script>

<form id="wizardForm">
    @csrf

    <div class="row mb-4">
        <div class="col-12">
            <h5 class="mb-1">Konfirmasi Sewa</h5>
            <p class="text-muted small">Periksa kembali data sewa sebelum disimpan</p>
        </div>
    </div>

    @php
        $customer = isset($data['customer_id']) ? \App\Models\Customer::find($data['customer_id']) : null;
        $vehicle = isset($data['vehicle_id']) ? \App\Models\Vehicle::with('model.brand')->find($data['vehicle_id']) : null;
        $pickupLoc = isset($data['pickup_location_id']) ? \App\Models\Location::find($data['pickup_location_id']) : null;
        $returnLoc = isset($data['return_location_id']) ? \App\Models\Location::find($data['return_location_id']) : null;
        $driver = (isset($data['is_with_driver']) && $data['is_with_driver'] && isset($data['driver_id'])) ? \App\Models\Driver::find($data['driver_id']) : null;
        $promo = isset($data['promo_id']) ? \App\Models\Promo::find($data['promo_id']) : null;
        $start = isset($data['rental_start_date']) ? \Carbon\Carbon::parse($data['rental_start_date']) : null;
        $end = isset($data['rental_end_date']) ? \Carbon\Carbon::parse($data['rental_end_date']) : null;
        $days = $start && $end ? ($start->diffInDays($end) ?: 1) : 0;
    @endphp

    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="ri-user-star-line me-1"></i> Data Pelanggan</h6>
                </div>
                <div class="card-body">
                    @if($customer)
                        <p class="mb-1"><strong>{{ $customer->full_name }}</strong></p>
                        <p class="mb-1 text-muted small"><i class="ri-phone-line"></i> {{ $customer->phone }}</p>
                        <p class="mb-1 text-muted small"><i class="ri-mail-line"></i> {{ $customer->email }}</p>
                        <span class="badge bg-label-{{ $customer->customer_type == 'corporate' ? 'primary' : 'info' }}">
                            {{ $customer->customer_type == 'corporate' ? 'Perusahaan' : 'Individu' }}
                        </span>
                    @else
                        <p class="text-muted">Belum dipilih</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="ri-car-line me-1"></i> Data Kendaraan</h6>
                </div>
                <div class="card-body">
                    @if($vehicle)
                        <p class="mb-1"><strong>{{ $vehicle->license_plate }}</strong></p>
                        <p class="mb-1 text-muted small">{{ $vehicle->model->brand->brand_name ?? '' }} {{ $vehicle->model->model_name ?? '' }} ({{ $vehicle->year }})</p>
                        <p class="mb-1 text-muted small">{{ $vehicle->color }} | {{ $vehicle->model->transmission ?? '' }} | {{ $vehicle->model->seat_capacity ?? '' }} kursi</p>
                    @else
                        <p class="text-muted">Belum dipilih</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="ri-calendar-line me-1"></i> Periode Sewa</h6>
                </div>
                <div class="card-body">
                    @if($start && $end)
                        <p class="mb-1"><strong>Mulai:</strong> {{ $start->format('d F Y') }}</p>
                        <p class="mb-1"><strong>Selesai:</strong> {{ $end->format('d F Y') }}</p>
                        <p class="mb-0"><strong>Durasi:</strong> {{ $days }} hari</p>
                    @endif
                    @if($pickupLoc)
                        <p class="mb-0 mt-2"><strong>Penjemputan:</strong> {{ $pickupLoc->location_name }}</p>
                    @endif
                    @if($returnLoc)
                        <p class="mb-0"><strong>Pengembalian:</strong> {{ $returnLoc->location_name }}</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="ri-settings-3-line me-1"></i> Opsi Tambahan</h6>
                </div>
                <div class="card-body">
                    <p class="mb-1">
                        <strong>Sopir:</strong>
                        @if($driver)
                            {{ $driver->full_name }}
                        @else
                            <span class="text-muted">Tidak ada</span>
                        @endif
                    </p>
                    <p class="mb-1">
                        <strong>Promo:</strong>
                        @if($promo)
                            {{ $promo->promo_code }} ({{ $promo->discount_type == 'percentage' ? $promo->discount_value . '%' : 'Rp ' . number_format($promo->discount_value, 0, ',', '.') }})
                        @else
                            <span class="text-muted">Tidak ada</span>
                        @endif
                    </p>
                    @if(!empty($data['notes']))
                        <p class="mb-0"><strong>Catatan:</strong> {{ $data['notes'] }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">
            <h6 class="mb-0"><i class="ri-money-dollar-circle-line me-1"></i> Rincian Harga</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <tbody>
                        <tr>
                            <td>Tarif Dasar/Hari</td>
                            <td class="text-end" id="reviewBaseRate">Rp 0</td>
                        </tr>
                        <tr>
                            <td>Durasi Sewa</td>
                            <td class="text-end">{{ $days }} hari</td>
                        </tr>
                        <tr>
                            <td>Total Tarif Dasar</td>
                            <td class="text-end" id="reviewTotalBase">Rp 0</td>
                        </tr>
                        <tr>
                            <td>Asuransi</td>
                            <td class="text-end" id="reviewInsurance">Rp 0</td>
                        </tr>
                        @if($driver)
                        <tr>
                            <td>Biaya Sopir</td>
                            <td class="text-end" id="reviewDriverFee">Rp 0</td>
                        </tr>
                        @endif
                        <tr>
                            <td>Diskon</td>
                            <td class="text-end text-success" id="reviewDiscount">Rp 0</td>
                        </tr>
                        <tr id="reviewTaxRow">
                            <td>{{ $settings['tax_label'] ?? 'PPN' }} (<span id="reviewTaxPercent">11</span>%)</td>
                            <td class="text-end" id="reviewTax">Rp 0</td>
                        </tr>
                        <tr id="reviewDepositRow">
                            <td>Deposit <small class="text-muted">(jaminan, di luar tagihan)</small></td>
                            <td class="text-end" id="reviewDeposit">Rp 0</td>
                        </tr>
                        <tr class="table-active">
                            <td><strong>Total</strong></td>
                            <td class="text-end"><strong id="reviewTotal">Rp 0</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="text-end mt-4">
        <button type="button" class="btn btn-outline-secondary btn-prev-step" data-step="3">
            <i class="ri-arrow-left-s-line"></i> Sebelumnya
        </button>
        <button type="button" class="btn btn-success" id="btnSubmitRental">
            <i class="ri-check-line"></i> Konfirmasi & Simpan
        </button>
    </div>
</form>

<script>
    $('#btnSubmitRental').off('click').on('click', function(e) {
        e.preventDefault();
        Swal.fire({
            icon: 'question',
            title: 'Konfirmasi',
            text: 'Simpan data sewa?',
            showCancelButton: true,
            confirmButtonText: 'Ya, Simpan',
            cancelButtonText: 'Batal',
            reverseButtons: true,
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return $.ajax({
                    type: 'POST',
                    url: '{{ route("rental.create.store") }}',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    dataType: 'JSON',
                }).done(function(data) {
                    return data;
                }).fail(function() {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal menyimpan sewa.' });
                });
            },
            allowOutsideClick: false
        }).then((result) => {
            if (result.value && result.value.status) {
                Swal.fire({ icon: 'success', title: 'Berhasil', text: result.value.msg, didClose: () => {
                    window.location.href = result.value.redirect;
                }});
            }
        });
    });

    function loadReviewPrices() {
        const vehicleId = '{{ $data["vehicle_id"] ?? "" }}';
        const startDate = '{{ $data["rental_start_date"] ?? "" }}';
        const endDate = '{{ $data["rental_end_date"] ?? "" }}';
        const withDriver = {{ isset($data['is_with_driver']) && $data['is_with_driver'] ? 'true' : 'false' }};
        const driverId = '{{ $data["driver_id"] ?? "" }}';
        const promoId = '{{ $data["promo_id"] ?? "" }}';
        const depositAmount = '{{ $data["deposit_amount"] ?? "" }}';
        const taxPercent = '{{ $data["tax_percent"] ?? "" }}';

        if (vehicleId && startDate && endDate) {
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
                        $('#reviewBaseRate').text('Rp ' + formatNumber(d.base_rate_per_day));
                        $('#reviewTotalBase').text('Rp ' + formatNumber(d.total_base_price));
                        $('#reviewInsurance').text('Rp ' + formatNumber(d.insurance_fee));
                        $('#reviewDriverFee').text('Rp ' + formatNumber(d.driver_fee));
                        $('#reviewDiscount').text('Rp -' + formatNumber(d.discount_amount));
                        $('#reviewTaxPercent').text(d.tax_percent);
                        $('#reviewTax').text('Rp ' + formatNumber(d.tax_amount));
                        $('#reviewDeposit').text('Rp ' + formatNumber(d.deposit_amount));
                        $('#reviewTotal').text('Rp ' + formatNumber(d.total_amount));
                        $('#reviewTaxRow').toggle(parseFloat(d.tax_percent) > 0);
                        $('#reviewDepositRow').toggle(parseFloat(d.deposit_amount) > 0);
                    }
                }
            });
        }
    }

    loadReviewPrices();
</script>

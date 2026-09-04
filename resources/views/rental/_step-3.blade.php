<form id="wizardForm">
    @csrf
    <input type="hidden" name="vehicle_id" id="vehicle_id" value="{{ $data['vehicle_id'] ?? '' }}">
    <input type="hidden" name="customer_id" value="{{ $data['customer_id'] ?? '' }}">

    <div class="row mb-4">
        <div class="col-12">
            <h5 class="mb-1">Detail Sewa</h5>
            <p class="text-muted small">Lengkapi detail periode sewa dan opsi tambahan</p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Tanggal Mulai Sewa <span class="text-danger">*</span></label>
            <input type="text" class="form-control datepicker" name="rental_start_date" id="rental_start_date" value="{{ $data['rental_start_date'] ?? '' }}" placeholder="YYYY-MM-DD" autocomplete="off" required>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Tanggal Selesai Sewa <span class="text-danger">*</span></label>
            <input type="text" class="form-control datepicker" name="rental_end_date" id="rental_end_date" value="{{ $data['rental_end_date'] ?? '' }}" placeholder="YYYY-MM-DD" autocomplete="off" required>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Lokasi Penjemputan</label>
            <select class="form-select select2" name="pickup_location_id">
                <option value="">Pilih Lokasi</option>
                @foreach($locations as $loc)
                    <option value="{{ $loc->location_id }}" {{ isset($data['pickup_location_id']) && $data['pickup_location_id'] == $loc->location_id ? 'selected' : '' }}>
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
                    <option value="{{ $loc->location_id }}" {{ isset($data['return_location_id']) && $data['return_location_id'] == $loc->location_id ? 'selected' : '' }}>
                        {{ $loc->location_name }} - {{ $loc->city }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="form-check form-switch mt-3">
                <input class="form-check-input" type="checkbox" id="is_with_driver" name="is_with_driver" value="1" {{ isset($data['is_with_driver']) && $data['is_with_driver'] ? 'checked' : '' }}>
                <label class="form-check-label" for="is_with_driver">Dengan Sopir</label>
            </div>
        </div>
    </div>

    <div class="driver-section" style="display: {{ isset($data['is_with_driver']) && $data['is_with_driver'] ? 'block' : 'none' }}">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Pilih Sopir</label>
                <select class="form-select select2" name="driver_id" id="driver_id">
                    <option value="">Pilih Sopir</option>
                    @foreach($drivers as $driver)
                        <option value="{{ $driver->driver_id }}" {{ isset($data['driver_id']) && $data['driver_id'] == $driver->driver_id ? 'selected' : '' }}>
                            {{ $driver->full_name }} - {{ $driver->phone }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Biaya Sopir/Hari</label>
                <input type="number" class="form-control" name="driver_fee" id="driver_fee" value="{{ $data['driver_fee'] ?? 150000 }}">
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Kode Promo</label>
            <select class="form-select select2" name="promo_id" id="promo_id">
                <option value="">Tidak ada promo</option>
                @foreach($promos as $promo)
                    <option value="{{ $promo->promo_id }}" {{ isset($data['promo_id']) && $data['promo_id'] == $promo->promo_id ? 'selected' : '' }}>
                        {{ $promo->promo_code }} - {{ $promo->discount_type == 'percentage' ? $promo->discount_value . '%' : 'Rp ' . number_format($promo->discount_value, 0, ',', '.') }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Catatan</label>
            <textarea class="form-control" name="notes" rows="1">{{ $data['notes'] ?? '' }}</textarea>
        </div>
    </div>

    <div class="price-breakdown mt-4" id="priceSummary" style="display: none;">
        <h6 class="mb-3">Ringkasan Harga</h6>
        <div class="price-row">
            <span>Tarif Dasar/Hari</span>
            <span id="priceRowBaseRate">Rp 0</span>
        </div>
        <div class="price-row">
            <span>Durasi Sewa</span>
            <span id="priceRowDays">- hari</span>
        </div>
        <div class="price-row">
            <span>Total Tarif Dasar</span>
            <span id="priceRowTotalBase">Rp 0</span>
        </div>
        <div class="price-row">
            <span>Asuransi</span>
            <span id="priceRowInsurance">Rp 0</span>
        </div>
        <div class="price-row" id="driverPriceRow">
            <span>Biaya Sopir</span>
            <span id="priceRowDriver">Rp 0</span>
        </div>
        <div class="price-row">
            <span>Diskon</span>
            <span id="priceRowDiscount" class="text-success">Rp -0</span>
        </div>
        <div class="price-row">
            <span>Pajak (11%)</span>
            <span id="priceRowTax">Rp 0</span>
        </div>
        <div class="price-row">
            <span>Deposit</span>
            <span id="priceRowDeposit">Rp 0</span>
        </div>
        <div class="price-row total">
            <span>Total</span>
            <span id="priceRowTotal">Rp 0</span>
        </div>
    </div>

    <div class="text-end mt-4">
        <button type="button" class="btn btn-outline-secondary btn-prev-step" data-step="2">
            <i class="ri-arrow-left-s-line"></i> Sebelumnya
        </button>
        <button type="button" class="btn btn-primary btn-next-step" data-step="3">
            Selanjutnya <i class="ri-arrow-right-s-line"></i>
        </button>
    </div>
</form>

<script>
    initSelect2();

    $('#is_with_driver').off('change').on('change', function() {
        if ($(this).is(':checked')) {
            $('.driver-section').slideDown();
        } else {
            $('.driver-section').slideUp();
        }
        calculateTotal();
    });

    $('#rental_start_date, #rental_end_date, #vehicle_id, #driver_id, #driver_fee, #promo_id').off('change').on('change', function() {
        calculateTotal();
    });

    if ($('#rental_start_date').val() && $('#rental_end_date').val() && $('#vehicle_id').val()) {
        calculateTotal();
    }
</script>

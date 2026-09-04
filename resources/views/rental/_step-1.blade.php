<form id="wizardForm">
    @csrf
    <input type="hidden" name="customer_id" id="customer_id" value="{{ $data['customer_id'] ?? '' }}">

    <div class="row mb-4">
        <div class="col-12">
            <h5 class="mb-1">Pilih Pelanggan</h5>
            <p class="text-muted small">Cari dan pilih pelanggan yang akan menyewa</p>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text"><i class="ri-search-line"></i></span>
                <input type="text" class="form-control" id="searchCustomer" placeholder="Cari nama, telepon, atau email...">
            </div>
        </div>
        <div class="col-md-6 text-end">
            <a href="{{ route('master.customer.create') }}" class="btn btn-outline-primary" target="_blank">
                <i class="ri-add-line"></i> Pelanggan Baru
            </a>
        </div>
    </div>

    <div class="row" id="customerList">
        @forelse($customers as $customer)
            <div class="col-md-6 mb-3 customer-item" data-search="{{ strtolower($customer->full_name . ' ' . $customer->phone . ' ' . $customer->email) }}">
                <div class="card customer-card {{ isset($data['customer_id']) && $data['customer_id'] == $customer->customer_id ? 'selected' : '' }}" data-id="{{ $customer->customer_id }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">{{ $customer->full_name }}</h6>
                                <p class="mb-1 text-muted small">
                                    <i class="ri-phone-line"></i> {{ $customer->phone }}
                                </p>
                                <p class="mb-1 text-muted small">
                                    <i class="ri-mail-line"></i> {{ $customer->email }}
                                </p>
                                <span class="badge bg-label-{{ $customer->customer_type == 'corporate' ? 'primary' : 'info' }}">
                                    {{ $customer->customer_type == 'corporate' ? 'Perusahaan' : 'Individu' }}
                                </span>
                                @if($customer->is_verified)
                                    <span class="badge bg-label-success ms-1">Terverifikasi</span>
                                @endif
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="_customer_radio" value="{{ $customer->customer_id }}" {{ isset($data['customer_id']) && $data['customer_id'] == $customer->customer_id ? 'checked' : '' }}>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-center py-4">
                <p class="text-muted">Belum ada pelanggan. Silakan tambah pelanggan baru.</p>
            </div>
        @endforelse
    </div>

    <div class="text-end mt-2">
        <button type="button" class="btn btn-primary btn-next-step" data-step="1">
            Selanjutnya <i class="ri-arrow-right-s-line"></i>
        </button>
    </div>
</form>

<script>
    $('#searchCustomer').on('keyup', function() {
        const q = $(this).val().toLowerCase();
        $('.customer-item').each(function() {
            const search = $(this).data('search');
            $(this).toggle(search.includes(q));
        });
    });

    $('.customer-card').off('click').on('click', function() {
        $('.customer-card').removeClass('selected');
        $('input[name="_customer_radio"]').prop('checked', false);
        $(this).addClass('selected');
        $(this).find('input[name="_customer_radio"]').prop('checked', true);
        $('#customer_id').val($(this).data('id'));
    });
</script>

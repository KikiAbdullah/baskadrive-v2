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
                <div class="card customer-card {{ isset($data['customer_id']) && $data['customer_id'] == $customer->customer_id ? 'selected' : '' }}" data-id="{{ $customer->customer_id }}" role="button" tabindex="0" aria-pressed="{{ isset($data['customer_id']) && $data['customer_id'] == $customer->customer_id ? 'true' : 'false' }}">
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
    let searchTimer = null;
    let initialCustomerHtml = $('#customerList').html();

    function bindCustomerCards() {
        function pickCard(card) {
            $('.customer-card').removeClass('selected').attr('aria-pressed', 'false');
            $('input[name="_customer_radio"]').prop('checked', false);
            card.addClass('selected').attr('aria-pressed', 'true');
            card.find('input[name="_customer_radio"]').prop('checked', true);
            $('#customer_id').val(card.data('id'));
        }
        $('.customer-card').off('click').on('click', function() {
            pickCard($(this));
        });
        $('.customer-card').off('keydown').on('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                pickCard($(this));
            }
        });
    }
    bindCustomerCards();

    function escapeHtml(s) {
        return $('<div>').text(s ?? '').html();
    }

    function renderCustomerResults(items) {
        if (!items || !items.length) {
            $('#customerList').html('<div class="col-12 text-center py-4"><p class="text-muted">Tidak ada pelanggan ditemukan.</p></div>');
            return;
        }
        let html = '';
        items.forEach(function(c) {
            const label = escapeHtml(c.full_name || c.text || '');
            const phone = escapeHtml(c.phone || '');
            const email = escapeHtml(c.email || '');
            const badgeType = c.customer_type === 'corporate' ? 'Perusahaan' : 'Individu';
            const badgeClass = c.customer_type === 'corporate' ? 'primary' : 'info';
            const verified = c.is_verified ? '<span class="badge bg-label-success ms-1">Terverifikasi</span>' : '';
            const selectedId = $('#customer_id').val();
            const isSelected = String(selectedId) === String(c.customer_id) ? ' selected' : '';
            const checked = isSelected ? ' checked' : '';
            html += '<div class="col-md-6 mb-3 customer-item">'
                + '<div class="card customer-card' + isSelected + '" data-id="' + c.customer_id + '" role="button" tabindex="0" aria-pressed="' + (isSelected ? 'true' : 'false') + '">'
                + '<div class="card-body"><div class="d-flex justify-content-between align-items-start"><div>'
                + '<h6 class="mb-1">' + label + '</h6>'
                + '<p class="mb-1 text-muted small"><i class="ri-phone-line"></i> ' + phone + '</p>'
                + '<p class="mb-1 text-muted small"><i class="ri-mail-line"></i> ' + email + '</p>'
                + '<span class="badge bg-label-' + badgeClass + '">' + badgeType + '</span>' + verified
                + '</div><div class="form-check"><input class="form-check-input" type="radio" name="_customer_radio" value="' + c.customer_id + '"' + checked + '></div>'
                + '</div></div></div></div>';
        });
        $('#customerList').html(html);
        bindCustomerCards();
    }

    $('#searchCustomer').on('keyup', function() {
        const q = $(this).val().trim();
        if (q.length === 0) {
            $('#customerList').html(initialCustomerHtml);
            bindCustomerCards();
            return;
        }
        if (q.length < 2) return;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() {
            $.ajax({
                url: '{{ route("rental.create.search-customer") }}',
                type: 'GET',
                data: { q: q },
                dataType: 'JSON',
                success: function(res) {
                    // endpoint mengembalikan {results:[...]} — fallback ke array langsung
                    const items = res.results || res.data || res || [];
                    renderCustomerResults(items);
                },
                error: function() {
                    // fallback ke filter client-side bila endpoint gagal
                    const ql = q.toLowerCase();
                    $('.customer-item').each(function() {
                        const search = ($(this).data('search') || '').toString().toLowerCase();
                        $(this).toggle(search.includes(ql));
                    });
                }
            });
        }, 300);
    });
</script>

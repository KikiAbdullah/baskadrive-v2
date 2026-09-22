@extends('layouts.header')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2 row-gap-4">
            <div class="d-flex flex-column justify-content-center">
                <h4 class="mb-1">{{ $title }}</h4>
                <p class="mb-6">{{ $subtitle }}</p>
            </div>
        </div>

        @include('layouts.alert')

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h6 class="mb-0"><i class="ri-stack-line me-1"></i> Invoice dengan Sisa Tagihan ({{ $pendingInvoices->count() }})</h6>
                        <span class="badge bg-label-info">Pilih beberapa invoice milik satu pelanggan untuk satu kwitansi</span>
                    </div>
                    <div class="card-body">
                        @if($pendingInvoices->isEmpty())
                            <p class="text-muted mb-0">Tidak ada invoice dengan sisa tagihan. Semua sudah lunas.</p>
                        @else
                            <form id="batchForm">
                                @csrf
                                <div class="table-responsive text-nowrap">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th style="width: 36px;"><input type="checkbox" id="checkAll" /></th>
                                                <th>Invoice</th>
                                                <th>Pelanggan</th>
                                                <th>Sewa</th>
                                                <th>Jatuh Tempo</th>
                                                <th class="text-end">Total</th>
                                                <th class="text-end">Dibayar</th>
                                                <th class="text-end">Sisa</th>
                                                <th class="text-end" style="width: 170px;">Bayar Sekarang</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($pendingInvoices as $inv)
                                                @php $remaining = (float) $inv->total_amount - (float) $inv->paid_amount; @endphp
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" class="form-check-input inv-check"
                                                            data-remaining="{{ $remaining }}"
                                                            data-invoice-id="{{ $inv->invoice_id }}"
                                                            data-number="{{ $inv->invoice_number }}"
                                                            data-customer="{{ $inv->rental?->customer?->customer_id }}" />
                                                    </td>
                                                    <td><strong>{{ $inv->invoice_number }}</strong></td>
                                                    <td>{{ $inv->rental?->customer?->full_name ?? '-' }}</td>
                                                    <td>{{ $inv->rental?->rental_code ?? '-' }}</td>
                                                    <td>{{ $inv->due_date?->format('d/m/Y') ?? '-' }}</td>
                                                    <td class="text-end">{{ \App\Support\AppSettings::money($inv->total_amount) }}</td>
                                                    <td class="text-end">{{ \App\Support\AppSettings::money($inv->paid_amount) }}</td>
                                                    <td class="text-end text-danger">{{ \App\Support\AppSettings::money($remaining) }}</td>
                                                    <td>
                                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm inv-amount text-end"
                                                            data-remaining="{{ $remaining }}" disabled />
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="row g-3 mt-2 align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label">Metode Pembayaran</label>
                                        <select name="payment_method" id="paymentMethod" class="form-select">
                                            <option value="bank_transfer">Transfer Bank</option>
                                            <option value="cash">Tunai</option>
                                            <option value="credit_card">Kartu Kredit</option>
                                            <option value="debit_card">Kartu Debit</option>
                                            <option value="e_wallet">E-Wallet</option>
                                            <option value="other">Lainnya</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">No. Referensi (opsional)</label>
                                        <input type="text" name="reference_number" id="referenceNumber" class="form-control" maxlength="50" placeholder="No. transfer/kliring" />
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Catatan (opsional)</label>
                                        <input type="text" name="notes" id="batchNotes" class="form-control" maxlength="500" />
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <div class="mb-2">Total: <strong id="batchTotal">Rp 0</strong> <span id="batchCount" class="badge bg-label-primary">0 invoice</span></div>
                                        <button type="submit" id="btnSubmitBatch" class="btn btn-primary">
                                            <i class="ri-bank-card-line me-1"></i> Proses Pembayaran Borongan
                                        </button>
                                    </div>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('customjs')
    <script>
        $(function() {
            function fmt(n) {
                return 'Rp ' + Number(n || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            // Pemilihan satu pelanggan per batch: centang invoice lain dari pelanggan
            // berbeda otomatis melepas centang pelanggan sebelumnya.
            var activeCustomer = null;

            $('#checkAll').on('change', function() {
                var checked = $(this).prop('checked');
                if (checked) {
                    // "Semua" berlaku dalam batas satu pelanggan pertama yang tersedia
                    var first = $('.inv-check').first();
                    activeCustomer = first.data('customer');
                    $('.inv-check').each(function() {
                        var on = $(this).data('customer') === activeCustomer;
                        $(this).prop('checked', on).trigger('change');
                    });
                } else {
                    activeCustomer = null;
                    $('.inv-check').prop('checked', false).trigger('change');
                }
            });

            $(document).on('change', '.inv-check', function() {
                var row = $(this).closest('tr');
                var amountInput = row.find('.inv-amount');
                if ($(this).prop('checked')) {
                    var cust = String($(this).data('customer'));
                    if (activeCustomer === null) {
                        activeCustomer = cust;
                    } else if (String(activeCustomer) !== cust) {
                        // Tolak campur pelanggan — batalkan centang ini
                        $(this).prop('checked', false);
                        Swal.fire({ icon: 'warning', title: 'Pelanggan berbeda', text: 'Satu pembayaran borongan hanya untuk invoice milik satu pelanggan.' });
                        return;
                    }
                    amountInput.prop('disabled', false);
                    if (!amountInput.val()) {
                        amountInput.val(parseFloat(amountInput.data('remaining')).toFixed(2));
                    }
                } else {
                    amountInput.prop('disabled', true).val('');
                    if ($('.inv-check:checked').length === 0) {
                        activeCustomer = null;
                    }
                }
                recalc();
            });

            $(document).on('input', '.inv-amount', recalc);

            function recalc() {
                var total = 0, count = 0;
                $('.inv-check:checked').each(function() {
                    total += parseFloat($(this).closest('tr').find('.inv-amount').val()) || 0;
                    count++;
                });
                $('#batchTotal').text(fmt(total));
                $('#batchCount').text(count + ' invoice');
            }

            $('#batchForm').on('submit', function(e) {
                e.preventDefault();
                var rows = [];
                var invalid = null;

                $('.inv-check:checked').each(function() {
                    var row = $(this).closest('tr');
                    var remaining = parseFloat($(this).data('remaining'));
                    var amount = parseFloat(row.find('.inv-amount').val());

                    if (!amount || amount <= 0) {
                        invalid = 'Nominal pembayaran untuk ' + $(this).data('number') + ' harus positif.';
                        return false;
                    }
                    if (amount > remaining + 0.01) {
                        invalid = 'Nominal ' + $(this).data('number') + ' melebihi sisa tagihan (' + fmt(remaining) + ').';
                        return false;
                    }
                    rows.push({ invoice_id: $(this).data('invoice-id'), amount: amount });
                });

                if (!rows.length) {
                    Swal.fire({ icon: 'warning', title: 'Belum ada invoice', text: 'Centang minimal satu invoice beserta nominalnya.' });
                    return;
                }
                if (invalid) {
                    Swal.fire({ icon: 'error', title: 'Nominal tidak valid', text: invalid });
                    return;
                }

                var $btn = $('#btnSubmitBatch').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Memproses…');

                $.ajax({
                    url: '{{ route('finance.payment.batch.store') }}',
                    method: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        invoices: rows,
                        payment_method: $('#paymentMethod').val(),
                        reference_number: $('#referenceNumber').val(),
                        notes: $('#batchNotes').val()
                    },
                    success: function(res) {
                        if (res.status) {
                            Swal.fire({ icon: 'success', title: 'Berhasil', text: res.msg + ' Cetak kwitansi batch?', showCancelButton: true, confirmButtonText: 'Cetak Kwitansi' })
                                .then(function(r) {
                                    if (r.isConfirmed) {
                                        window.open('{{ route('finance.payment.batch.receipt', ':groupId:') }}'.replace(':groupId:', res.data.group_id), '_blank');
                                    }
                                    window.location.reload();
                                });
                        } else {
                            $btn.prop('disabled', false).html('<i class="ri-bank-card-line me-1"></i> Proses Pembayaran Borongan');
                            Swal.fire({ icon: 'error', title: 'Gagal', text: res.msg });
                        }
                    },
                    error: function(xhr) {
                        $btn.prop('disabled', false).html('<i class="ri-bank-card-line me-1"></i> Proses Pembayaran Borongan');
                        var msg = (xhr.responseJSON && xhr.responseJSON.msg) ? xhr.responseJSON.msg : 'Terjadi kesalahan server.';
                        Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
                    }
                });
            });
        });
    </script>
@endsection

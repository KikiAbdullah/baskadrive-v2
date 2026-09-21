@extends('layouts.header')

@section('customcss')
    <style>
        .fin-tabs {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .fin-tab {
            padding: 0.375rem 0.875rem;
            border-radius: 2rem;
            border: 1px solid #d9dee3;
            background: #fff;
            color: #697a8d;
            font-size: 0.8125rem;
            text-decoration: none;
            transition: all 0.15s;
        }

        .fin-tab:hover {
            border-color: #666cff;
            color: #666cff;
        }

        .fin-tab.active {
            background: #666cff;
            border-color: #666cff;
            color: #fff;
        }
    </style>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2 row-gap-4">
            <div class="d-flex flex-column justify-content-center">
                <h4 class="mb-1">{{ $title }}</h4>
                <p class="mb-6">{{ $subtitle }}</p>
            </div>
            <div class="d-flex align-content-center flex-wrap gap-4">
                <span class="menuoption"></span>
            </div>
        </div>

        @include('layouts.alert')

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="fin-tabs">
                    @foreach($tabs as $key => $label)
                        <a href="{{ route('finance.index', ['tab' => $key]) }}"
                            class="fin-tab {{ $tab == $key ? 'active' : '' }}">{{ $label }}</a>
                    @endforeach
                </div>

                @if($tab === 'invoice')
                    <select class="form-select form-select-sm w-auto" id="statusFilter">
                        <option value="">Semua Status</option>
                        <option value="draft">Draft</option>
                        <option value="sent">Terkirim</option>
                        <option value="partially_paid">Sebagian</option>
                        <option value="paid">Lunas</option>
                        <option value="overdue">Jatuh Tempo</option>
                        <option value="cancelled">Batal</option>
                    </select>
                @elseif($tab === 'fine')
                    <select class="form-select form-select-sm w-auto" id="statusFilter">
                        <option value="">Semua Status</option>
                        <option value="unpaid">Belum Bayar</option>
                        <option value="paid">Lunas</option>
                        <option value="waived">Dibebaskan</option>
                    </select>
                @elseif($tab === 'payment')
                    <select class="form-select form-select-sm w-auto" id="statusFilter">
                        <option value="">Semua Status</option>
                        <option value="pending">Pending</option>
                        <option value="completed">Selesai</option>
                        <option value="failed">Gagal</option>
                        <option value="refunded">Refund</option>
                    </select>
                @endif
            </div>

            {{-- ============ TAB: INVOICE ============ --}}
            @if($tab === 'invoice')
                <div class="card-datatable table-responsive">
                    <table class="table table-xxs" id="dtable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>No. Invoice</th>
                                <th>Kode Sewa</th>
                                <th>Pelanggan</th>
                                <th>Tgl Jatuh Tempo</th>
                                <th>Total</th>
                                <th>Terbayar</th>
                                <th>Sisa</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <script type="text/javascript">
                    var dtable;
                    const urlAjax = '{{ route('finance.invoice.data') }}';
                    const getButtonOption = '{{ route('finance.invoice.button-option') }}';
                    const dtableColumns = [{
                            data: 'invoice_id',
                            className: 'd-none'
                        },
                        {
                            data: 'invoice_number'
                        },
                        {
                            data: 'rental_code'
                        },
                        {
                            data: 'customer'
                        },
                        {
                            data: 'due_date'
                        },
                        {
                            data: 'total'
                        },
                        {
                            data: 'paid'
                        },
                        {
                            data: 'remaining'
                        },
                        {
                            data: 'status_badge'
                        },
                    ];
                    const dtableRowId = 'invoice_id';
                </script>

                {{-- ============ TAB: FINE ============ --}}
            @elseif($tab === 'fine')
                <div class="card-datatable table-responsive">
                    <table class="table table-xxs" id="dtable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Kode Sewa</th>
                                <th>Pelanggan</th>
                                <th>Kendaraan</th>
                                <th>Jenis</th>
                                <th>Nominal</th>
                                <th>Tgl Dikeluarkan</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <script type="text/javascript">
                    var dtable;
                    const urlAjax = '{{ route('finance.fine.data') }}';
                    const getButtonOption = '{{ route('finance.fine.button-option') }}';
                    const dtableColumns = [{
                            data: 'fine_id',
                            className: 'd-none'
                        },
                        {
                            data: 'rental_code'
                        },
                        {
                            data: 'customer'
                        },
                        {
                            data: 'vehicle'
                        },
                        {
                            data: 'fine_type'
                        },
                        {
                            data: 'amount'
                        },
                        {
                            data: 'issued_date'
                        },
                        {
                            data: 'status_badge'
                        },
                    ];
                    const dtableRowId = 'fine_id';
                </script>

                {{-- ============ TAB: PAYMENT ============ --}}
            @else
                <div class="card-datatable table-responsive">
                    <table class="table table-xxs" id="dtable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>No. Kwitansi</th>
                                <th>No. Invoice</th>
                                <th>Kode Sewa</th>
                                <th>Pelanggan</th>
                                <th>Tgl Bayar</th>
                                <th>Nominal</th>
                                <th>Metode</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <script type="text/javascript">
                    var dtable;
                    const urlAjax = '{{ route('finance.payment.data') }}';
                    const getButtonOption = '{{ route('finance.payment.button-option') }}';
                    const dtableColumns = [{
                            data: 'payment_id',
                            className: 'd-none'
                        },
                        {
                            data: 'receipt_number'
                        },
                        {
                            data: 'invoice_number'
                        },
                        {
                            data: 'rental_code'
                        },
                        {
                            data: 'customer'
                        },
                        {
                            data: 'payment_date'
                        },
                        {
                            data: 'amount'
                        },
                        {
                            data: 'method'
                        },
                        {
                            data: 'status_badge'
                        },
                    ];
                    const dtableRowId = 'payment_id';
                </script>
            @endif
        </div>
    </div>
@endsection

@section('customjs')
    <script type="text/javascript">
        $(document).ready(function() {
            dtable = $('#dtable').DataTable({
                "select": {
                    style: "single",
                    info: false
                },
                "serverSide": true,
                "stateSave": true,
                "sServerMethod": "GET",
                "deferRender": true,
                "rowId": dtableRowId,
                "ajax": {
                    url: urlAjax,
                    data: function(d) {
                        d.status = $('#statusFilter').val();
                    }
                },
                "columns": dtableColumns,
                "order": [
                    [1, "desc"]
                ],
                "columnDefs": [{
                    "targets": [0],
                    "visible": false,
                    "searchable": false
                }],
                "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 d-flex justify-content-center justify-content-md-end"f>><"table-responsive"t><"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            });

            $("#dtable_length").addClass('d-none d-lg-block');

            $('#statusFilter').on('change', function() {
                dtable.ajax.reload();
            });

            dtable.on('select', function(e, dt, type, indexes) {
                var rowData = dtable.rows(indexes).data().toArray();
                var id = rowData[0][dtableRowId];

                $.ajax({
                    type: 'GET',
                    url: getButtonOption,
                    data: {
                        id: id
                    },
                    success: function(response) {
                        if (response.status) {
                            $(".menuoption").html(response.view);
                        }
                    }
                });
            });

            dtable.on('deselect', function(e, dt, type, indexes) {
                if (type === 'row') {
                    $(".menuoption").html('');
                }
            });

            {{-- FIN-12: helper respons gagal / error jaringan + status HTTP konsisten --}}
            function finActionError(qXHR, fallbackMsg) {
                let msg = fallbackMsg || 'Terjadi kesalahan. Silakan coba lagi.';
                if (qXHR && qXHR.responseJSON && qXHR.responseJSON.msg) {
                    msg = qXHR.responseJSON.msg;
                }
                Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
            }

            {{-- Aksi AJAX per tab --}}
            $('body').on('click', '.btn-send', function(e) {
                e.preventDefault();
                const $btn = $(this);
                const id = $btn.data('id');
                Swal.fire({
                    title: 'Kirim Invoice?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Kirim',
                    cancelButtonText: 'Batal',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        $btn.addClass('disabled pe-none');
                        $.ajax({
                            url: '{{ route('finance.invoice.send-email', ':id:') }}'.replace(':id:', id),
                            type: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}'
                            },
                            dataType: 'JSON',
                            success: function(res) {
                                if (res.status) {
                                    Swal.fire('Berhasil', res.msg, 'success');
                                    dtable.ajax.reload(null, false);
                                } else {
                                    finActionError(null, res.msg);
                                }
                            },
                            error: function(qXHR) {
                                finActionError(qXHR);
                            },
                            complete: function() {
                                $btn.removeClass('disabled pe-none');
                            }
                        });
                    }
                });
            });

            $('body').on('click', '.btn-pay', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                Swal.fire({
                    title: 'Bayar Denda?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Bayar',
                    cancelButtonText: 'Batal',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        const $btn = $(this);
                        $btn.addClass('disabled pe-none');
                        $.ajax({
                            url: '{{ route('finance.fine.pay', ':id:') }}'.replace(':id:', id),
                            type: 'PUT',
                            data: {
                                _token: '{{ csrf_token() }}',
                                payment_method: 'cash'
                            },
                            dataType: 'JSON',
                            success: function(res) {
                                if (res.status) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Berhasil',
                                        text: res.msg,
                                        didClose: () => dtable.ajax.reload(null, false)
                                    });
                                } else {
                                    finActionError(null, res.msg);
                                }
                            },
                            error: function(qXHR) {
                                finActionError(qXHR);
                            },
                            complete: function() {
                                $btn.removeClass('disabled pe-none');
                            }
                        });
                    }
                });
            });

            $('body').on('click', '.btn-waive', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                Swal.fire({
                    title: 'Bebaskan Denda?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Bebaskan',
                    cancelButtonText: 'Batal',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        const $btn = $(this);
                        $btn.addClass('disabled pe-none');
                        $.ajax({
                            url: '{{ route('finance.fine.waive', ':id:') }}'.replace(':id:', id),
                            type: 'PUT',
                            data: {
                                _token: '{{ csrf_token() }}'
                            },
                            dataType: 'JSON',
                            success: function(res) {
                                if (res.status) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Berhasil',
                                        text: res.msg,
                                        didClose: () => dtable.ajax.reload(null, false)
                                    });
                                } else {
                                    finActionError(null, res.msg);
                                }
                            },
                            error: function(qXHR) {
                                finActionError(qXHR);
                            },
                            complete: function() {
                                $btn.removeClass('disabled pe-none');
                            }
                        });
                    }
                });
            });
        });
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
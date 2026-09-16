@extends('layouts.header')

@section('customcss')
    <style>
        .status-tabs {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .status-tab {
            padding: 0.375rem 0.875rem;
            border-radius: 2rem;
            border: 1px solid #d9dee3;
            background: #fff;
            color: #697a8d;
            font-size: 0.8125rem;
            text-decoration: none;
            transition: all 0.15s;
        }

        .status-tab:hover {
            border-color: #666cff;
            color: #666cff;
        }

        .status-tab.active {
            background: #666cff;
            border-color: #666cff;
            color: #fff;
        }

        .status-tab .badge {
            margin-left: 0.25rem;
        }
    </style>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2 row-gap-4">
            <div class="d-flex flex-column justify-content-center">
                <h4 class="mb-1">{{ $title }}</h4>
                <p class="mb-6">Daftar {{ $subtitle }}</p>
            </div>
            <div class="d-flex align-content-center flex-wrap gap-4">
                <span class="menuoption"></span>
                @can('rental_add')
                    <a href="{{ route('rental.create') }}" class="action-link-icon-text">
                        <i class="ri-add-line"></i>
                        <span class="fw-semibold text-uppercase">Buat Sewa Baru</span>
                    </a>
                @endcan
                @can('rental_export')
                    <a href="{{ route('rental.export', array_filter(['status' => $status ?? 'all'])) }}" class="action-link-icon-text" id="btnExportRental">
                        <i class="ri-download-2-line"></i>
                        <span class="fw-semibold text-uppercase">Export CSV</span>
                    </a>
                @endcan
            </div>
        </div>

        @include('layouts.alert')

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="status-tabs">
                    @foreach($tabs as $key => $label)
                        <a href="{{ route('rental.index', ['status' => $key]) }}"
                            class="status-tab {{ $status == $key ? 'active' : '' }}">
                            {{ $label }}<span class="badge bg-label-{{ $status == $key ? 'light' : 'secondary' }}">{{ $counts[$key] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
            <div class="card-datatable table-responsive">
                <table class="table table-xxs" id="dtable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kode</th>
                            <th>Pelanggan</th>
                            <th>Kendaraan</th>
                            <th>Penjemputan</th>
                            <th>Periode</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('customjs')
    <script type="text/javascript">
        var dtable;
        var currentStatus = '{{ $status }}';
        const urlAjax = '{{ route('rental.data', ['status' => $status]) }}';
        const getButtonOption = '{{ route('rental.button-option') }}';

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
                "rowId": 'rental_id',
                "ajax": urlAjax,
                "columns": [{
                        data: 'rental_id',
                        className: 'd-none'
                    },
                    {
                        data: 'rental_code'
                    },
                    {
                        data: 'customer_name'
                    },
                    {
                        data: 'vehicle_info'
                    },
                    {
                        data: 'pickup'
                    },
                    {
                        data: 'date_range'
                    },
                    {
                        data: 'total_amount'
                    },
                    {
                        data: 'status_badge'
                    },
                ],
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

            dtable.on('select', function(e, dt, type, indexes) {
                var rowData = dtable.rows(indexes).data().toArray();
                var id = rowData[0].rental_id;

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

            $('body').on('click', '.btn-confirm', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                Swal.fire({
                    icon: 'question',
                    title: 'Konfirmasi Penjemputan?',
                    text: 'Status sewa akan berubah menjadi berjalan.',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Konfirmasi',
                    cancelButtonText: 'Batal',
                    reverseButtons: true,
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '{{ route('rental.confirm', ['id' => '__ID__']) }}'.replace('__ID__', id),
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
                                    Swal.fire({ icon: 'error', title: 'Gagal', text: res.msg || 'Aksi ditolak.' });
                                }
                            },
                            error: function(xhr) {
                                const msg = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.msg)) || 'Terjadi kesalahan pada server.';
                                Swal.fire({ icon: 'error', title: 'Error', text: msg });
                            }
                        });
                    }
                });
            });

            $('body').on('click', '.btn-cancel', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                Swal.fire({
                    icon: 'warning',
                    title: 'Batalkan Sewa?',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Batalkan',
                    cancelButtonText: 'Batal',
                    reverseButtons: true,
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '{{ route('rental.cancel', ['id' => '__ID__']) }}'.replace('__ID__', id),
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
                                    Swal.fire({ icon: 'error', title: 'Gagal', text: res.msg || 'Aksi ditolak.' });
                                }
                            },
                            error: function(xhr) {
                                const msg = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.msg)) || 'Terjadi kesalahan pada server.';
                                Swal.fire({ icon: 'error', title: 'Error', text: msg });
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
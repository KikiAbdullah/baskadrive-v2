@extends('layouts.header')

@section('customcss')
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2 row-gap-4">
            <div class="d-flex flex-column justify-content-center">
                <h4 class="mb-1">{{ $title }}</h4>
                <p class="mb-6">Daftar {{ $subtitle }}</p>
            </div>
            <div class="d-flex align-content-center flex-wrap gap-2"></div>
        </div>

        @include('layouts.alert')

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Filter</h6>
                <select class="form-select form-select-sm w-auto" id="statusFilter">
                    <option value="">Semua Status</option>
                    <option value="draft">Draft</option>
                    <option value="sent">Terkirim</option>
                    <option value="partially_paid">Sebagian</option>
                    <option value="paid">Lunas</option>
                    <option value="overdue">Jatuh Tempo</option>
                    <option value="cancelled">Batal</option>
                </select>
            </div>
            <div class="card-datatable table-responsive">
                <table class="table table-xxs" id="dtable">
                    <thead>
                        <tr>
                            <th>No. Invoice</th>
                            <th>Kode Sewa</th>
                            <th>Pelanggan</th>
                            <th>Tgl Jatuh Tempo</th>
                            <th>Total</th>
                            <th>Terbayar</th>
                            <th>Sisa</th>
                            <th>Status</th>
                            <th>Aksi</th>
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
        const urlAjax = '{{ route('finance.invoice.data') }}';

        $(document).ready(function() {
            dtable = $('#dtable').DataTable({
                "serverSide": true,
                "stateSave": true,
                "sServerMethod": "GET",
                "deferRender": true,
                "ajax": {
                    url: urlAjax,
                    data: function(d) {
                        d.status = $('#statusFilter').val();
                    }
                },
                "columns": [{
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
                    {
                        data: 'action'
                    },
                ],
                "order": [
                    [0, "desc"]
                ],
                "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 d-flex justify-content-center justify-content-md-end"f>><"table-responsive"t><"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            });

            $('#statusFilter').on('change', function() {
                dtable.ajax.reload();
            });

            $('body').on('click', '.btn-send', function() {
                const id = $(this).data('id');
                Swal.fire({
                    title: 'Kirim Invoice?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Kirim',
                    cancelButtonText: 'Batal',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '{{ url('finance/invoice') }}/' + id + '/send-email',
                            type: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}'
                            },
                            dataType: 'JSON',
                            success: function(res) {
                                if (res.status) {
                                    Swal.fire('Berhasil', res.msg, 'success');
                                }
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
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
            <div class="d-flex align-content-center flex-wrap gap-4">
                <span class="menuoption"></span>
                @can('fleet_maintain')
                    <a href="{{ route('fleet.maintenance.create') }}" class="action-link-icon-text">
                        <i class="ri-add-line"></i>
                        <span class="fw-semibold text-uppercase">Tambah Jadwal</span>
                    </a>
                @endcan
            </div>
        </div>

        @include('layouts.alert')

        <div class="card">
            <div class="card-datatable table-responsive">
                <table class="table table-xxs" id="dtable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kendaraan</th>
                            <th>Jenis</th>
                            <th>Bengkel</th>
                            <th>Tanggal</th>
                            <th>Biaya</th>
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
        const urlAjax = '{{ route('fleet.maintenance.data') }}';
        const getButtonOption = '{{ route('fleet.maintenance.button-option') }}';

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
                "rowId": 'maintenance_id',
                "ajax": urlAjax,
                "columns": [{
                        data: 'maintenance_id',
                        className: 'd-none'
                    },
                    {
                        data: 'vehicle_info'
                    },
                    {
                        data: 'type'
                    },
                    {
                        data: 'workshop_name'
                    },
                    {
                        data: 'scheduled_date'
                    },
                    {
                        data: 'cost'
                    },
                    {
                        data: 'status_badge'
                    },
                ],
                "order": [
                    [4, "asc"]
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
                var id = rowData[0].maintenance_id;

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

            $('body').on('click', '.btn-complete', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                Swal.fire({
                    icon: 'question',
                    title: 'Tandai Selesai?',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Selesai',
                    cancelButtonText: 'Batal',
                    reverseButtons: true,
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                        $.ajax({
                            url: '{{ url('fleet/maintenance') }}/' + id + '/complete',
                            type: 'PUT',
                            data: {
                                _token: '{{ csrf_token() }}'
                            },
                            dataType: 'JSON',
                            complete: () => Swal.close(),
                            success: function(res) {
                                if (res.status) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Berhasil',
                                        text: res.msg,
                                        didClose: () => dtable.ajax.reload(null, false)
                                    });
                                } else {
                                    Swal.fire({ icon: 'error', title: 'Gagal', text: res.msg || 'Terjadi kesalahan.' });
                                }
                            },
                            error: function(xhr) {
                                const msg = (xhr.responseJSON && (xhr.responseJSON.msg || xhr.responseJSON.message))
                                    || (xhr.status === 0 ? 'Koneksi gagal. Periksa jaringan Anda.' : 'Terjadi kesalahan. Coba lagi.');
                                Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
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
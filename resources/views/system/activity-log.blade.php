@extends('layouts.header')

@section('customcss')
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex flex-column justify-content-center mb-2">
            <h4 class="mb-1">{{ $title }}</h4>
            <p class="mb-6">{{ $subtitle }}</p>
        </div>

        @include('layouts.alert')

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-2">
                <h6 class="mb-0">Filter</h6>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <input type="text" class="form-control form-control-sm flatpickr-date w-auto" id="dateFromFilter"
                        placeholder="Dari tanggal" autocomplete="off" style="max-width: 150px;">
                    <input type="text" class="form-control form-control-sm flatpickr-date w-auto" id="dateToFilter"
                        placeholder="Sampai tanggal" autocomplete="off" style="max-width: 150px;">
                    <select class="form-select form-select-sm w-auto" id="userFilter">
                        <option value="">Semua Pengguna</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select form-select-sm w-auto" id="actionFilter">
                        <option value="">Semua Aksi</option>
                        <option value="create">Create</option>
                        <option value="update">Update</option>
                        <option value="delete">Delete</option>
                        <option value="login">Login</option>
                        <option value="logout">Logout</option>
                        <option value="export">Export</option>
                    </select>
                </div>
            </div>
            <div class="card-datatable table-responsive">
                <table class="table table-xxs" id="dtable">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Pengguna</th>
                            <th>Aksi</th>
                            <th>Menu</th>
                            <th>Pesan</th>
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
        const urlAjax = '{{ route('system.activity-log.data') }}';

        $(document).ready(function() {
            dtable = $('#dtable').DataTable({
                "serverSide": true,
                "stateSave": true,
                "sServerMethod": "GET",
                "deferRender": true,
                "ajax": {
                    url: urlAjax,
                    data: function(d) {
                        d.action = $('#actionFilter').val();
                        d.user_id = $('#userFilter').val();
                        d.date_from = $('#dateFromFilter').val();
                        d.date_to = $('#dateToFilter').val();
                    }
                },
                "columns": [{
                        data: 'created_at'
                    },
                    {
                        data: 'user'
                    },
                    {
                        data: 'action'
                    },
                    {
                        data: 'menu'
                    },
                    {
                        data: 'message'
                    },
                ],
                "order": [
                    [0, "desc"]
                ],
                "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 d-flex justify-content-center justify-content-md-end"f>><"table-responsive"t><"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            });

            $('#actionFilter, #userFilter, #dateFromFilter, #dateToFilter').on('change', function() {
                dtable.ajax.reload();
            });
        });

        if (window.initFlatpickr) {
            window.initFlatpickr(document.getElementById('dtable')?.closest('.card') ?? document);
        }
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
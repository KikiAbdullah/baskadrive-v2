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
            </div>
        </div>

        @include('layouts.alert')

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Filter</h6>
                {{-- AKN-06: 'revenue' diganti 'income' sesuai enum kolom account_type --}}
                <select class="form-select form-select-sm w-auto" id="typeFilter">
                    <option value="">Semua Tipe</option>
                    <option value="asset">Aset</option>
                    <option value="liability">Kewajiban</option>
                    <option value="equity">Ekuitas</option>
                    <option value="income">Pendapatan</option>
                    <option value="expense">Beban</option>
                </select>
            </div>
            <div class="card-datatable table-responsive">
                <table class="table table-xxs" id="dtable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kode Akun</th>
                            <th>Nama Akun</th>
                            <th>Tipe</th>
                            <th>Saldo</th>
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
        const urlAjax = '{{ route('accounting.ledger.data') }}';
        const getButtonOption = '{{ route('accounting.ledger.button-option') }}';

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
                "rowId": 'account_id',
                "ajax": {
                    url: urlAjax,
                    data: function(d) {
                        d.type = $('#typeFilter').val();
                    }
                },
                "columns": [{
                        data: 'account_id',
                        className: 'd-none'
                    },
                    {
                        data: 'account_code'
                    },
                    {
                        data: 'account_name'
                    },
                    {
                        data: 'account_type'
                    },
                    {
                        data: 'balance'
                    },
                ],
                "order": [
                    [1, "asc"]
                ],
                "columnDefs": [{
                    "targets": [0],
                    "visible": false,
                    "searchable": false
                }],
                "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 d-flex justify-content-center justify-content-md-end"f>><"table-responsive"t><"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            });

            $("#dtable_length").addClass('d-none d-lg-block');

            $('#typeFilter').on('change', function() {
                dtable.ajax.reload();
            });

            dtable.on('select', function(e, dt, type, indexes) {
                var rowData = dtable.rows(indexes).data().toArray();
                var id = rowData[0].account_id;

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
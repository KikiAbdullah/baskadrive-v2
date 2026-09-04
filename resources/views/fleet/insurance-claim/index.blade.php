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
            <div class="d-flex align-content-center flex-wrap gap-2">
                <a href="{{ route('fleet.insurance-claim.create') }}" class="btn btn-primary">
                    <i class="ri-add-line me-1"></i> Ajukan Klaim
                </a>
            </div>
        </div>

        @include('layouts.alert')

        <div class="card">
            <div class="card-datatable table-responsive">
                <table class="table table-xxs" id="dtable">
                    <thead>
                        <tr>
                            <th>No. Klaim</th>
                            <th>Kendaraan</th>
                            <th>Provider</th>
                            <th>Nilai Klaim</th>
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
        const urlAjax = '{{ route('fleet.insurance-claim.data') }}';

        $(document).ready(function() {
            dtable = $('#dtable').DataTable({
                "serverSide": true,
                "stateSave": true,
                "sServerMethod": "GET",
                "deferRender": true,
                "ajax": urlAjax,
                "columns": [{
                        data: 'claim_number'
                    },
                    {
                        data: 'vehicle_info'
                    },
                    {
                        data: 'provider'
                    },
                    {
                        data: 'claim_amount'
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
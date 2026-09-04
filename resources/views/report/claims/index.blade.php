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
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Filter</h6>
                <div class="d-flex gap-2 align-items-center">
                    <select class="form-select form-select-sm w-auto" id="statusFilter">
                        <option value="">Semua Status</option>
                        <option value="reported">Laporan</option>
                        <option value="inspected">Diinspeksi</option>
                        <option value="approved">Disetujui</option>
                        <option value="in_repair">Diperbaiki</option>
                        <option value="repaired">Selesai</option>
                        <option value="closed">Tutup</option>
                    </select>
                    <a href="{{ route('report.export', 'claims') }}" class="btn btn-sm btn-outline-primary">
                        <i class="ri-download-line"></i> Export
                    </a>
                </div>
            </div>
            <div class="card-datatable table-responsive">
                <table class="table table-xxs" id="dtable">
                    <thead>
                        <tr>
                            <th>Kendaraan</th>
                            <th>Jenis</th>
                            <th>Severity</th>
                            <th>Tgl Lapor</th>
                            <th class="text-end">Biaya Perbaikan</th>
                            <th>Status Kerusakan</th>
                            <th>Status Klaim</th>
                            <th class="text-end">Nilai Klaim</th>
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
        const urlAjax = '{{ route('report.claims.data') }}';

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
                        data: 'vehicle'
                    },
                    {
                        data: 'damage_type'
                    },
                    {
                        data: 'severity'
                    },
                    {
                        data: 'reported_date'
                    },
                    {
                        data: 'repair_cost'
                    },
                    {
                        data: 'status_badge'
                    },
                    {
                        data: 'claim_status'
                    },
                    {
                        data: 'claim_amount'
                    },
                ],
                "order": [
                    [3, "desc"]
                ],
                "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 d-flex justify-content-center justify-content-md-end"f>><"table-responsive"t><"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            });

            $('#statusFilter').on('change', function() {
                dtable.ajax.reload();
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
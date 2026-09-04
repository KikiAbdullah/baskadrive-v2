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

        <div class="row mb-3">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted">Total Pendapatan</small>
                        <h4 class="mb-0 text-success">Rp {{ number_format($totalRevenue ?? 0, 0, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted">Jumlah Invoice</small>
                        <h4 class="mb-0">{{ $totalInvoices ?? 0 }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted">Piutang (Jatuh Tempo)</small>
                        <h4 class="mb-0 text-danger">Rp {{ number_format($outstanding ?? 0, 0, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0">Filter Periode</h6>
                <div class="d-flex gap-2 align-items-center">
                    <input type="date" class="form-control form-control-sm" id="startDate" value="{{ $start }}">
                    <span>s/d</span>
                    <input type="date" class="form-control form-control-sm" id="endDate" value="{{ $end }}">
                    <a href="{{ route('report.export', 'revenue') }}" class="btn btn-sm btn-outline-primary">
                        <i class="ri-download-line"></i> Export
                    </a>
                </div>
            </div>
            <div class="card-datatable table-responsive">
                <table class="table table-xxs" id="dtable">
                    <thead>
                        <tr>
                            <th>Periode</th>
                            <th class="text-end">Pendapatan</th>
                            <th class="text-end">Jml Pembayaran</th>
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
        const urlAjax = '{{ route('report.revenue.data') }}';

        $(document).ready(function() {
            dtable = $('#dtable').DataTable({
                "serverSide": true,
                "stateSave": true,
                "sServerMethod": "GET",
                "deferRender": true,
                "ajax": {
                    url: urlAjax,
                    data: function(d) {
                        d.start_date = $('#startDate').val();
                        d.end_date = $('#endDate').val();
                    }
                },
                "columns": [{
                        data: 'period_label'
                    },
                    {
                        data: 'revenue'
                    },
                    {
                        data: 'payments_count'
                    },
                ],
                "order": [
                    [0, "desc"]
                ],
                "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 d-flex justify-content-center justify-content-md-end"f>><"table-responsive"t><"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            });

            $('#startDate, #endDate').on('change', function() {
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
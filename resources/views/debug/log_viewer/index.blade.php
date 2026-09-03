@extends('layouts.header')
@section('customcss')
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

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-datatable table-responsive">
                        <table class="table table-sm" id="dtable">
                            <thead>
                                <tr>
                                    <th class="text-center" width="5%"></th>
                                    <th class="text-center" width="12%">Date</th>
                                    <th class="text-center">File</th>
                                    <th class="text-center">Line</th>
                                    <th class="text-center">Text</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($logs as $log)
                                    <tr>
                                        <td class="text-center">{!! $icons[$log['urgent']] !!}</td>
                                        <td class="text-center">{{ $log['date'] }}</td>
                                        <td class="text-center">{{ $log['file'] }}</td>
                                        <td class="text-center">{{ $log['line'] }}</td>
                                        <td>{{ $log['text'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('customjs')
    <script type="text/javascript">
        var dtable;

        $(document).ready(function($) {
            dtable = $('#dtable').DataTable({
                "pageLength": 10,
                "stateSave": false,
                "ordering": false,
                "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 d-flex justify-content-center justify-content-md-end"f>><"table-responsive"t><"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            });
        });
    </script>
@endsection

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

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="{{ route('rental.detail.invoice.store', $rental->rental_id) }}">
                            @csrf
                            <div class="table-responsive mb-3">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th class="text-end">Qty</th>
                                            <th class="text-end">Harga</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($rental->details as $d)
                                            <tr>
                                                <td>{{ $d->item_name }}</td>
                                                <td class="text-end">{{ $d->quantity }}</td>
                                                <td class="text-end">Rp {{ number_format($d->unit_price ?? 0, 0, ',', '.') }}</td>
                                                <td class="text-end">Rp {{ number_format($d->total_price ?? 0, 0, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-active">
                                            <th colspan="3" class="text-end">Total</th>
                                            <th class="text-end">Rp {{ number_format($rental->total_amount ?? 0, 0, ',', '.') }}</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tanggal Jatuh Tempo <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control datepicker" name="due_date"
                                        value="{{ now()->addDays(7)->format('Y-m-d') }}" required>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Catatan</label>
                                    <textarea class="form-control" name="notes" rows="2"></textarea>
                                </div>
                            </div>

                            <div class="text-end">
                                <a href="{{ route('rental.show', $rental->rental_id) }}"
                                    class="btn btn-outline-secondary">Batal</a>
                                <button type="submit" class="btn btn-primary">Buat Invoice</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('customjs')
    <script>
        if ($.fn.datepicker) {
            $('.datepicker').datepicker({
                format: 'yyyy-mm-dd',
                autoclose: true,
                todayHighlight: true,
                orientation: 'bottom auto'
            });
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
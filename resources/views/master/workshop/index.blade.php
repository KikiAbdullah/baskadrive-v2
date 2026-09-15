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

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-datatable table-responsive">
                        <table class="table table-xxs" id="dtable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama</th>
                                    <th>Rating</th>
                                    <th>Spesialisasi</th>
                                    <th>Telepon</th>
                                    <th>Kontak Person</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6" id="dynamic-form">
                @can('master_add')

                    @include('master.workshop.create')

                @endcan
            </div>

        </div>
    </div>
@endsection

@section('customjs')
    <script type="text/javascript">
        var dtable;
        const urlAjax = '{{ route('master.workshop.data') }}';
        const getButtonOption = '{{ route('get.button-option') }}';
        @php
                $btnList = [];
                if (auth()->user()->can('master_edit')) { $btnList['vedit'] = $url['edit']; }
                if (auth()->user()->can('master_delete')) { $btnList['destroy'] = $url['destroy']; }
            @endphp
            const buttons = @json($btnList);
        var html_temp = $("#dynamic-form").html();
        var button_temp =
            '<a href="#!" class="action-link-icon-text btnBack"><i class="ri-arrow-left-s-line"></i><span class="fw-semibold text-uppercase">CANCEL</span></a>';

        $(document).ready(function($) {
            const swalInit = Swal;
            dtable = $('#dtable').DataTable({
                "select": {
                    style: "single",
                    info: false
                },
                "serverSide": true,
                "stateSave": true,
                "sServerMethod": "GET",
                "deferRender": true,
                "rowId": 'workshop_id',
                "ajax": urlAjax,
                                "columns": [
                    {
                        data: 'workshop_id'
                    }
,
                    {
                        data: 'name'
                    }
,
                    {
                        data: 'rating_stars'
                    }
,
                    {
                        data: 'specialization'
                    }
,
                    {
                        data: 'phone'
                    }
,
                    {
                        data: 'contact_person'
                    }
,
                    {
                        data: 'is_active'
                    }
                ],
                "order": [
                    [0, "desc"]
                ],
                "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 d-flex justify-content-center justify-content-md-end"f>><"table-responsive"t><"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
                "columnDefs": [{
                    "targets": [0],
                    "visible": false,
                    "searchable": false
                }, ],
            });
            //set class for page length
            $("#dtable_length").addClass('d-none d-lg-block');

            dtable.on('select', function(e, dt, type, indexes) {
                var rowData = dtable.rows(indexes).data().toArray();
                var id = rowData[0].workshop_id;
                $.ajax({
                    type: 'GET',
                    url: getButtonOption,
                    data: {
                        id: id,
                        buttons: buttons,
                    },
                    success: function(response) {
                        if (response.status) {
                            backtoCreate();
                            $(".menuoption").html(response.view);
                        }
                    }
                });
            });
            dtable.on('deselect', function(e, dt, type, indexes) {
                if (type === 'row') {
                    backtoCreate();
                }
            });

            //submit form create
            $("body").on("submit", ".js-crud-create", function(e) {
                $(this).find('.submit_loader').removeAttr('class').addClass(
                    'ri-loader-4-line spinner submit_loader');
            });

            $("body").on("click", ".editBtn", function(e) {
                $.ajax({
                    url: $(this).attr('href'),
                    type: 'GET',
                    dataType: 'JSON',
                    data: {},
                    success: function(response) {
                        if (response.status) {
                            $("#dynamic-form").html(response.view);
                            $('.select').select2();
                            $('.menuoption').prepend(button_temp);
                            button_temp = $('.editBtn').clone();
                            $('.menuoption').find('.editBtn').remove();
                        }
                    }
                });
                e.preventDefault();
            });
            $('body').on('click', '.btnBack', function(e) {
                backtoCreate();
                e.preventDefault();
            });

            $('body').on('click', '.deleteBtn', function(e) {
                var form = $(this).parents('form.delete');
                Swal.fire({
                    icon: 'warning',
                    title: 'Konfirmasi Hapus Data: {{ $title }}?',
                    showCancelButton: true,
                    confirmButtonText: 'Confirm',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-primary',
                        cancelButton: 'btn btn-light',
                        input: 'form-control'
                    },
                    reverseButtons: true,
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            text: 'Loading..',
                            showConfirmButton: false,
                            allowOutsideClick: false
                        });
                        form.submit();
                    }
                })
                e.preventDefault();
            });

            //update form submit
            $('body').on('submit', '.js-crud-edit', function(e) {
                swalInit.fire({
                    icon: 'question',
                    title: 'Simpan Perubahan?',
                    showCancelButton: true,
                    confirmButtonText: 'Confirm',
                    reverseButtons: true,
                    showLoaderOnConfirm: true,
                    preConfirm: () => {
                        return $.ajax({
                            type: 'PUT',
                            url: $(".js-crud-edit").attr('action'),
                            data: $(".js-crud-edit").serialize(),
                            dataType: "json",
                        }).done(function(data) {
                            return data;
                        }).fail(function(jqXHR, textStatus, errorThrown) {
                            if (jqXHR.status == 422) {
                                var xhr = JSON.stringify(JSON.parse(jqXHR.responseText)
                                    .errors);
                            } else {
                                var xhr = JSON.stringify(JSON.parse(jqXHR
                                    .responseText));
                            }
                            swalInit.fire({
                                title: 'Request Error',
                                text: xhr.substring(0, 160),
                                icon: 'error',
                            })
                        })
                    },
                    allowOutsideClick: false
                }).then((result) => {
                    if (result.value != null)
                        if (result.value.status) {
                            swalInit.fire({
                                title: 'Success',
                                text: result.value.msg,
                                icon: 'success',
                                didClose: () => {
                                    dtable.ajax.reload(null, false);
                                }
                            })
                        } else {
                            swalInit.fire({
                                title: 'Error',
                                text: result.value.msg.substring(0, 160),
                                icon: 'error',
                            })
                        }
                })
                e.preventDefault();
            });
        });

        function backtoCreate() {
            $("#dynamic-form").html(html_temp);
            $('.menuoption').html('');
            button_temp = '<a href="#!" class="action-link-icon-text btnBack"><i class="ri-arrow-left-s-line"></i><span class="fw-semibold text-uppercase">CANCEL</span></a>';
            $('.select').select2();
        }
    </script>
@endsection

@section('appmodal')
    <!-- Basic modal -->
    <div id="mymodal" class="modal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-indigo text-white">
                    <h5 class="modal-title"></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                </div>
            </div>
        </div>
    </div>
    <!-- /basic modal -->
@endsection

@section('notification')
    @include('layouts.notification')
@endsection

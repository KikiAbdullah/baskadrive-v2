@extends('layouts.header')

@section('customcss')
    <link rel="stylesheet" href="{{ asset('assets/libs/leaflet/leaflet.css') }}" />
    <style>
        .location-map { height: 260px; width: 100%; z-index: 0; }
        .leaflet-container { font-family: inherit; }
    </style>
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
                                    <th>Nama Lokasi</th>
                                    <th>Kota</th>
                                    <th>Jam</th>
                                    <th>Koordinat</th>
                                    <th>Telepon</th>
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

                    @include('master.location.create')

                @endcan
            </div>

        </div>
    </div>
@endsection

@section('customjs')
    <script src="{{ asset('assets/libs/leaflet/leaflet.js') }}"></script>
    <script type="text/javascript">
        var dtable;
        const urlAjax = '{{ route('master.location.data') }}';
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

        var locationMap = null;
        var locationMarker = null;
        var geoController = null;
        var geoTimer = null;

        function scheduleReverseGeocode(lat, lng) {
            clearTimeout(geoTimer);
            geoTimer = setTimeout(function () { reverseGeocode(lat, lng); }, 900);
        }

        function reverseGeocode(lat, lng) {
            var status = document.getElementById('geoStatus');
            if (typeof fetch === 'undefined') return;

            if (geoController) geoController.abort();
            geoController = new AbortController();
            if (status) status.textContent = 'Mencari alamat…';

            fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + lat +
                    '&lon=' + lng + '&zoom=18&addressdetails=1',
                { signal: geoController.signal, headers: { 'Accept': 'application/json' } })
                .then(function (r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(function (data) {
                    var a = data.address || {};
                    var address = document.getElementById('locationAddress');
                    var city = document.getElementById('locationCity');
                    var province = document.getElementById('locationProvince');

                    if (address) {
                        var parts = [];
                        if (a.house_number) parts.push(a.house_number);
                        if (a.road) parts.push(a.road);
                        if (a.neighbourhood) parts.push(a.neighbourhood);
                        address.value = parts.length ? parts.join(' ') : (data.display_name || '');
                        city.value = a.city_district || a.town || a.municipality || a.village || a.city || a.county || '';
                        province.value = a.state || a.province || a.city || '';
                    }
                    if (status) status.textContent = 'Alamat terisi dari peta.';
                })
                .catch(function (err) {
                    if (err.name !== 'AbortError' && status) {
                        status.textContent = 'Gagal mengambil alamat. Isi manual atau coba lagi.';
                    }
                });
        }

        function initLocationMap() {
            var el = document.getElementById('locationMap');
            var latInput = document.getElementById('latitude');
            var lngInput = document.getElementById('longitude');
            if (!el || !latInput || !lngInput || typeof L === 'undefined') return;

            if (locationMap) {
                locationMap.remove();
                locationMap = null;
                locationMarker = null;
            }

            var lat = parseFloat(latInput.value);
            var lng = parseFloat(lngInput.value);
            var valid = !isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
            var center = valid ? [lat, lng] : [-6.2088, 106.8456];

            locationMap = L.map(el).setView(center, valid ? 15 : 11);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(locationMap);

            locationMarker = L.marker(center, { draggable: true }).addTo(locationMap);

            function syncInputs() {
                var p = locationMarker.getLatLng();
                latInput.value = p.lat.toFixed(7);
                lngInput.value = p.lng.toFixed(7);
                scheduleReverseGeocode(p.lat, p.lng);
            }

            function fromInputs() {
                var a = parseFloat(latInput.value);
                var b = parseFloat(lngInput.value);
                if (!isNaN(a) && !isNaN(b) && a >= -90 && a <= 90 && b >= -180 && b <= 180) {
                    locationMarker.setLatLng([a, b]);
                    locationMap.setView([a, b], locationMap.getZoom());
                    scheduleReverseGeocode(a, b);
                }
            }

            locationMarker.on('dragend', syncInputs);
            locationMap.on('click', function (e) {
                locationMarker.setLatLng(e.latlng);
                syncInputs();
            });
            latInput.addEventListener('change', fromInputs);
            lngInput.addEventListener('change', fromInputs);

            setTimeout(function () { locationMap.invalidateSize(); }, 200);
        }

        $(document).ready(function($) {
            const swalInit = Swal;
            initLocationMap();

            $(document).on('click', '#btnReverseGeocode', function (e) {
                e.preventDefault();
                var lat = parseFloat(document.getElementById('latitude').value);
                var lng = parseFloat(document.getElementById('longitude').value);
                if (isNaN(lat) || isNaN(lng)) {
                    alert('Tentukan titik pada peta terlebih dahulu.');
                    return;
                }
                reverseGeocode(lat, lng);
            });
            dtable = $('#dtable').DataTable({
                "select": {
                    style: "single",
                    info: false
                },
                "serverSide": true,
                "stateSave": true,
                "sServerMethod": "GET",
                "deferRender": true,
                "rowId": 'location_id',
                "ajax": urlAjax,
                "columns": [{
                        data: 'location_id'
                    },
                    {
                        data: 'location_name'
                    },
                    {
                        data: 'city'
                    },
                    {
                        data: 'opening_hours'
                    },
                    {
                        data: 'coords'
                    },
                    {
                        data: 'contact_phone'
                    },
                    {
                        data: 'is_active'
                    },
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
                var id = rowData[0].location_id;
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
                            initLocationMap();
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
            initLocationMap();
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
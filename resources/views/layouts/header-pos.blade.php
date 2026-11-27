<!doctype html>

<html lang="en" class="light-style layout-menu-fixed layout-compact" dir="ltr" data-theme="theme-default"
    data-assets-path="{{ asset('assets') }}/" data-template="horizontal-menu-template-no-customizer" data-style="light">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>@yield('title', $title ?? 'Dashboard') | {{ config('app.name', 'Laravel') }}</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="base-url" content="{{ url('/') }}">

    <!-- Favicon -->
    {{-- Favicon = logo.png (aset identitas bawaan, di-resize via BrandAsset) --}}
    <link rel="icon" type="image/png" sizes="256x256" href="{{ \App\Support\BrandAsset::faviconUrl() }}" />
    <link rel="icon" type="image/png" href="{{ \App\Support\BrandAsset::logoUrl() }}" />
    <link rel="apple-touch-icon" href="{{ \App\Support\BrandAsset::logoUrl() }}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&ampdisplay=swap"
        rel="stylesheet" />

    <!-- Icons -->
    <link rel="stylesheet" href="{{ asset('assets') }}/vendor/fonts/remixicon/remixicon.css" />
    <link rel="stylesheet" href="{{ asset('assets') }}/vendor/fonts/flag-icons.css" />

    <!-- Menu waves for no-customizer fix -->
    <link rel="stylesheet" href="{{ asset('assets') }}/vendor/libs/node-waves/node-waves.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{ asset('assets') }}/vendor/css/rtl/core.css" />
    <link rel="stylesheet" href="{{ asset('assets') }}/vendor/css/rtl/theme-default.css" />
    <link rel="stylesheet" href="{{ asset('assets') }}/css/demo.css" />
    <link rel="stylesheet" href="{{ asset('app_local/css') }}/app-enhancements.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="{{ asset('assets') }}/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="{{ asset('assets') }}/vendor/libs/typeahead-js/typeahead.css" />
    <link rel="stylesheet" href="{{ asset('assets') }}/vendor/libs/apex-charts/apex-charts.css" />
    <link rel="stylesheet" href="{{ asset('assets') }}/vendor/libs/datatables-bs5/datatables.bootstrap5.css" />
    <link rel="stylesheet"
        href="{{ asset('assets') }}/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css" />
    <link rel="stylesheet"
        href="{{ asset('assets') }}/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/swiper/swiper.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/bootstrap-datepicker/bootstrap-datepicker.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />

    {{-- Top bar POS: ringkas, tanpa navbar/menu template --}}
    <style>
        .pos-topbar {
            position: sticky;
            top: 0;
            z-index: 1090;
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .6rem 1.25rem;
            background: #fff;
            border-bottom: 1px solid #e9eaf4;
            box-shadow: 0 2px 10px rgba(20, 20, 43, .04);
        }

        .pos-topbar .pos-brand {
            display: flex;
            align-items: center;
            gap: .6rem;
            font-weight: 700;
            color: #14142b;
            text-decoration: none;
        }

        .pos-topbar .pos-brand img {
            height: 34px;
            width: auto;
        }

        .pos-topbar .pos-title {
            font-weight: 700;
            color: #14142b;
        }

        .pos-topbar .pos-subtitle {
            font-size: .75rem;
            color: #6b7194;
        }

        .pos-topbar .btn-dashboard {
            border: 1px solid #d9dbee;
            color: #666CFF;
            font-weight: 600;
        }

        .pos-topbar .btn-dashboard:hover {
            background: #666CFF;
            border-color: #666CFF;
            color: #fff;
        }

        /* Konten POS memakai lebar penuh tanpa padding container template */
        .layout-wrapper.pos-mode .content-wrapper {
            margin-left: 0;
            min-height: calc(100vh - 60px);
            background: #f6f7fb;
        }

        .layout-wrapper.pos-mode .content-wrapper>.pos-content {
            padding: 1.25rem;
        }
    </style>

    @yield('customcss')

    <!-- Helpers -->
    <script src="{{ asset('assets') }}/vendor/js/helpers.js"></script>
    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="{{ asset('assets') }}/js/config.js"></script>
    <script src="{{ asset('assets') }}/vendor/libs/jquery/jquery.js"></script>
    <script src="{{ asset('assets') }}/vendor/libs/popper/popper.js"></script>
    <script src="{{ asset('assets') }}/vendor/js/bootstrap.js"></script>

    <script type="text/javascript">
        const _csrf_token = "{{ csrf_token() }}";
        $(function() {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': _csrf_token
                }
            });
        });
    </script>

</head>

<body>
    {{-- POS mode: tanpa navbar & menu horizontal — layar kasir full-fokus.
         Navigasi tetap tersedia via tombol Dashboard & Kembali di top bar. --}}
    <div class="layout-wrapper pos-mode">
        <div class="layout-container">
            <div class="layout-page">
                <div class="content-wrapper">
                    {{-- ===== Top bar ringkas ===== --}}
                    <div class="pos-topbar">
                        <a href="{{ route('rental.pos') }}" class="pos-brand">
                            <img src="{{ \App\Support\BrandAsset::logoUrl() }}" alt="logo">
                            <span class="d-none d-sm-inline">{{ config('app.name', 'Laravel') }}</span>
                        </a>
                        <div class="ms-2">
                            <div class="pos-title">@yield('title', $title ?? 'Booking POS')</div>
                            <div class="pos-subtitle">@yield('subtitle', $subtitle ?? '')</div>
                        </div>
                        <div class="ms-auto d-flex align-items-center gap-2">
                            <a href="{{ url()->previous() }}"
                                class="btn btn-sm btn-outline-secondary d-none d-md-inline-block">
                                <i class="ri-arrow-go-back-line me-1"></i> Kembali
                            </a>
                            <a href="{{ route('dashboard.index') }}" class="btn btn-sm btn-dashboard">
                                <i class="ri-dashboard-line me-1"></i> Dashboard
                            </a>
                        </div>
                    </div>
                    {{-- ===== / Top bar ===== --}}

                    {{-- Konten halaman POS --}}
                    <div class="pos-content">
                        @yield('content')
                    </div>

                    <div class="content-backdrop fade"></div>
                </div>
            </div>
        </div>
    </div>
    {{-- / Layout wrapper --}}

    @yield('appmodal')

    <!-- Core JS -->
    <script src="{{ asset('assets') }}/vendor/libs/node-waves/node-waves.js"></script>
    <script src="{{ asset('assets') }}/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="{{ asset('assets') }}/vendor/libs/i18n/i18n.js"></script>

    <!-- Vendors JS -->
    <script src="{{ asset('assets') }}/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="{{ asset('assets/vendor/libs/swiper/swiper.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/bootstrap-datepicker/bootstrap-datepicker.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
    <script src="{{ asset('app_local/js/settings.js') }}"></script>
    <script src="{{ asset('app_local/js/app-enhancements.js') }}"></script>

    <!-- Main JS -->
    <script src="{{ asset('assets') }}/js/main.js"></script>

    <!-- Page JS -->
    @yield('customjs')

    <!-- Global Notification Handler -->
    <script>
        $(document).ready(function() {
            @if(session('success'))
                Swal.fire({ icon: 'success', title: 'Berhasil!', text: "{{ session('success') }}", timer: 3000, showConfirmButton: false });
            @endif

            @if(session('error'))
                Swal.fire({ icon: 'error', title: 'Oops!', text: "{{ session('error') }}", confirmButtonColor: '#666cff' });
            @endif

            // Global AJAX Error Handler
            $(document).ajaxError(function(event, jqXHR, ajaxSettings, thrownError) {
                if (jqXHR.status === 401) {
                    Swal.fire({ icon: 'warning', title: 'Sesi Berakhir', text: 'Silakan login kembali.', didClose: () => { window.location.reload(); } });
                } else if (jqXHR.status === 403) {
                    Swal.fire({ icon: 'error', title: 'Akses Ditolak', text: 'Anda tidak memiliki izin untuk aksi ini.' });
                } else if (jqXHR.status >= 500) {
                    Swal.fire({ icon: 'error', title: 'Server Error', text: 'Terjadi kesalahan pada server. Coba lagi nanti.' });
                }
            });
        });
    </script>
</body>

</html>

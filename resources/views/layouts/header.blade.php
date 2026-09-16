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
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon/favicon.ico') }}" />

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

    <!-- Page CSS -->

    <link rel="stylesheet" href="{{ asset('assets') }}/vendor/css/pages/app-logistics-dashboard.css" />

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
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-navbar-full layout-horizontal layout-without-menu">
        <div class="layout-container">
            <!-- Navbar -->
            @include('layouts.navbar')
            <!-- / Navbar -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Menu -->
                @include('layouts.menu')
                <!-- / Menu -->

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <!-- Content -->
                    @yield('content')
                    <!--/ Content -->

                    <!-- Footer -->
                    <footer class="content-footer footer bg-footer-theme">
                        <div class="container-xxl">
                            <div
                                class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
                                <div class="text-body mb-2 mb-md-0">
                                    ©
                                    <script>
                                        document.write(new Date().getFullYear());
                                    </script>
                                    , made with <span class="text-danger"><i class="tf-icons ri-heart-fill"></i></span>
                                    by
                                    <a href="https://pixinvent.com" target="_blank" class="footer-link">Pixinvent</a>
                                </div>
                                <div class="d-none d-lg-inline-block">
                                    <a href="https://themeforest.net/licenses/standard" class="footer-link me-4"
                                        target="_blank">License</a>
                                    <a href="https://1.envato.market/pixinvent_portfolio" target="_blank"
                                        class="footer-link me-4">More Themes</a>

                                    <a href="https://demos.pixinvent.com/materialize-html-admin-template/documentation/"
                                        target="_blank" class="footer-link me-4">Documentation</a>

                                    <a href="https://pixinvent.ticksy.com/" target="_blank"
                                        class="footer-link d-none d-sm-inline-block">Support</a>
                                </div>
                            </div>
                        </div>
                    </footer>
                    <!-- / Footer -->

                    <div class="content-backdrop fade"></div>
                </div>
                <!--/ Content wrapper -->
            </div>

            <!--/ Layout container -->
        </div>
    </div>

    {{-- Dropdown fix: buka menu horizontal saat hover di seluruh link
         (template JS hanya trigger saat hover tepat di <a>, bukan icon/teks) --}}
    @once
        <script>
            (function() {
                function initMenuDropdown() {
                    var menuEl = document.getElementById('layout-menu');
                    if (!menuEl || !menuEl.classList.contains('menu-horizontal')) return;
                    if (menuEl.dataset.dropdownFixed) return;
                    menuEl.dataset.dropdownFixed = '1';

                    var inner = menuEl.querySelector('.menu-inner');
                    if (!inner) return;

                    var topItems = Array.prototype.slice.call(inner.children).filter(function(el) {
                        return el.classList && el.classList.contains('menu-item');
                    });

                    function closeOthers(except) {
                        topItems.forEach(function(li) {
                            if (li !== except) li.classList.remove('open');
                        });
                    }

                    var key = 'templateCustomizer-' + (window.templateName || '') + '--ShowDropdownOnHover';
                    var stored = localStorage.getItem(key);
                    var hoverMode = stored !== null
                        ? stored === 'true'
                        : (window.templateCustomizer !== undefined
                            ? window.templateCustomizer.settings.defaultShowDropdownOnHover
                            : true);

                    topItems.forEach(function(li) {
                        var toggle = li.querySelector('.menu-link.menu-toggle');
                        if (!toggle) return;

                        toggle.addEventListener('mouseenter', function() {
                            if (window.innerWidth > 1200) {
                                closeOthers(li);
                                li.classList.add('open');
                            }
                        });

                        li.addEventListener('mouseleave', function() {
                            if (window.innerWidth > 1200) {
                                li.classList.remove('open');
                            }
                        });

                        var clickBoundByTemplate = !hoverMode || window.Helpers.isMobileDevice;
                        if (!clickBoundByTemplate) {
                            toggle.addEventListener('click', function(e) {
                                e.preventDefault();
                                if (window.innerWidth > 1200) {
                                    closeOthers(li);
                                    li.classList.add('open');
                                } else {
                                    var isOpen = li.classList.contains('open');
                                    closeOthers();
                                    li.classList.toggle('open', !isOpen);
                                }
                            });
                        }
                    });
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initMenuDropdown);
                } else {
                    initMenuDropdown();
                }
            })();
        </script>
    @endonce

    <!-- Overlay -->
    <div class="layout-overlay layout-menu-toggle"></div>

    <!-- Drag Target Area To SlideIn Menu On Small Screens -->
    <div class="drag-target"></div>

    <!--/ Layout wrapper -->
    @yield('appmodal')

    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->
    <script src="{{ asset('assets') }}/vendor/libs/node-waves/node-waves.js"></script>
    <script src="{{ asset('assets') }}/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="{{ asset('assets') }}/vendor/libs/hammer/hammer.js"></script>
    <script src="{{ asset('assets') }}/vendor/libs/i18n/i18n.js"></script>
    <script src="{{ asset('assets') }}/vendor/libs/typeahead-js/typeahead.js"></script>
    <script src="{{ asset('assets') }}/vendor/js/menu.js"></script>
    <!-- endbuild -->

    <!-- Vendors JS -->
    <script src="{{ asset('assets') }}/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="{{ asset('assets') }}/vendor/libs/datatables-bs5/datatables-bootstrap5.js"></script>
    <script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/swiper/swiper.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/bootstrap-datepicker/bootstrap-datepicker.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/chartjs/chartjs.js') }}"></script>
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

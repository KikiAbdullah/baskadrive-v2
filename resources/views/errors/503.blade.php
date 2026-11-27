<!doctype html>

<html lang="en" class="light-style layout-wide customizer-hide" dir="ltr" data-theme="theme-default"
    data-assets-path="{{ asset('assets') }}/" data-template="horizontal-menu-template-no-customizer" data-style="light">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Error - {{ config('app.name') }}</title>

    <meta name="description" content="" />

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
    <link rel="stylesheet" href="{{ asset('assets/fonts/remixicon/remixicon.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/fonts/flag-icons.css') }}" />

    <!-- Menu waves for no-customizer fix -->
    <link rel="stylesheet" href="{{ asset('assets/libs/node-waves/node-waves.css') }}" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{ asset('assets/css/rtl/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/rtl/theme-default.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="{{ asset('assets/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/libs/typeahead-js/typeahead.css') }}" />

    <!-- Page CSS -->
    <!-- Page -->
    <link rel="stylesheet" href="{{ asset('assets/css/pages/page-misc.css') }}" />

    <!-- Helpers -->
    <script src="{{ asset('assets/js/helpers.js') }}"></script>
    <!--! Theme config MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Config: Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file. -->
    <script src="{{ asset('assets/js/config.js') }}"></script>
</head>

<body>
    <!-- Content -->

    <!-- Error -->
    <div class="misc-wrapper">
        <h1 class="mb-2 mx-2" style="font-size: 6rem; line-height: 6rem">503</h1>
        <h4 class="mb-2">Service Unavailable</h4>
        <p class="mb-6 mx-2">Oops, an error has occurred.</p>
        <div class="d-flex justify-content-center mt-9">
            <img src="{{ asset('assets/img/illustrations/misc-error-object.png') }}" alt="misc-error"
                class="img-fluid misc-object d-none d-lg-inline-block" width="160" />
            <img src="{{ asset('assets/img/illustrations/misc-bg-light.png') }}" alt="misc-error"
                class="misc-bg d-none d-lg-inline-block" data-app-light-img="illustrations/misc-bg-light.png"
                data-app-dark-img="illustrations/misc-bg-dark.png" />
            <div class="d-flex flex-column align-items-center">
                <img src="{{ asset('assets/img/illustrations/misc-server-error-illustration.png') }}"
                    alt="misc-server-error" class="img-fluid z-1" width="190" />
                <div>
                    <a href="#" onclick="history.back()" class="btn btn-primary text-center my-10">Back to
                        home</a>
                </div>
            </div>
        </div>
    </div>
    <!-- /Error -->

    <!-- / Content -->

    <!-- Core JS -->
    <!-- build:js assets/js/core.js -->
    <script src="{{ asset('assets/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap.js') }}"></script>
    <script src="{{ asset('assets/libs/node-waves/node-waves.js') }}"></script>
    <script src="{{ asset('assets/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('assets/libs/hammer/hammer.js') }}"></script>
    <script src="{{ asset('assets/libs/i18n/i18n.js') }}"></script>
    <script src="{{ asset('assets/libs/typeahead-js/typeahead.js') }}"></script>
    <script src="{{ asset('assets/js/menu.js') }}"></script>

    <!-- endbuild -->

    <!-- Vendors JS -->

    <!-- Main JS -->
    <script src="{{ asset('assets/js/main.js') }}"></script>

    <!-- Page JS -->
</body>

</html>

<!doctype html>

<html lang="id" class="light-style layout-wide customizer-hide" dir="ltr" data-theme="theme-default"
    data-assets-path="{{ asset('assets') }}/" data-template="horizontal-menu-template-no-customizer" data-style="light">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>@yield('title', config('app.name'))</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ \App\Support\BrandAsset::faviconUrl() }}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&ampdisplay=swap"
        rel="stylesheet" />

    <!-- Icons -->
    <link rel="stylesheet" href="{{ asset('assets/fonts/remixicon/remixicon.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/fonts/flag-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/libs/node-waves/node-waves.css') }}" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{ asset('assets/css/rtl/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/rtl/theme-default.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="{{ asset('assets/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/libs/typeahead-js/typeahead.css') }}" />

    <!-- Page CSS -->
    <link rel="stylesheet" href="{{ asset('assets/css/pages/page-misc.css') }}" />

    <!-- Helpers -->
    <script src="{{ asset('assets/js/helpers.js') }}"></script>
    <!--? Config: Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file. -->
    <script src="{{ asset('assets/js/config.js') }}"></script>
</head>

<body>
    <div class="misc-wrapper">
        <h1 class="mb-2 mx-2" style="font-size: 6rem; line-height: 6rem">@yield('code', '404')</h1>
        <h4 class="mb-2">@yield('title', 'Halaman tidak tersedia')</h4>
        <p class="mb-3 mx-2">@yield('message', 'Halaman yang Anda cari tidak ditemukan.')</p>

        <div class="d-flex justify-content-center mt-12">
            <img src="{{ asset('assets/img/illustrations/misc-not-authorized-object.png') }}"
                alt="ilustrasi" class="img-fluid misc-object d-none d-lg-inline-block" width="190" />
            <div class="d-flex flex-column align-items-center">
                <img src="{{ asset('assets/img/illustrations/misc-not-authorized-illustration.png') }}"
                    alt="ilustrasi" class="img-fluid z-1" width="160" />
                <a href="{{ route('dashboard.index') }}" class="btn btn-primary text-center my-10">
                    Kembali ke Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Core JS -->
    <script src="{{ asset('assets/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap.js') }}"></script>
    <script src="{{ asset('assets/libs/node-waves/node-waves.js') }}"></script>
    <script src="{{ asset('assets/js/main.js') }}"></script>
</body>

</html>

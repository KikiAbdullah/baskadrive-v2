<!doctype html>

<html lang="id" class="light-style layout-wide customizer-hide" dir="ltr" data-theme="theme-default"
    data-assets-path="{{ asset('assets') }}/" data-template="horizontal-menu-template-no-customizer" data-style="light">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Verifikasi Kontrak Sewa — {{ config('app.name') }}</title>

    <link rel="icon" type="image/png" href="{{ \App\Support\BrandAsset::faviconUrl() }}" />

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&ampdisplay=swap"
        rel="stylesheet" />

    <link rel="stylesheet" href="{{ asset('assets/fonts/remixicon/remixicon.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/libs/node-waves/node-waves.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/rtl/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/rtl/theme-default.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/pages/page-auth.css') }}" />

    <script src="{{ asset('assets/js/helpers.js') }}"></script>
    <script src="{{ asset('assets/js/config.js') }}"></script>
</head>

<body>
    <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    @if ($record)
                        <span class="badge rounded-pill bg-label-success p-3 d-inline-flex">
                            <i class="ri-checkbox-circle-line ri-2x"></i>
                        </span>
                        <h4 class="mt-4 mb-2">Kontrak Sewa Asli &amp; Terverifikasi</h4>
                        <p class="text-muted mb-4">Dokumen ini sah dan terdaftar pada sistem {{ config('app.name') }}.</p>
                        <table class="table table-borderless mb-0 text-start">
                            <tr>
                                <th class="text-muted fw-medium">Nomor Kontrak</th>
                                <td class="fw-semibold text-end">{{ $record->rental_code }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-medium">Penyewa</th>
                                <td class="fw-semibold text-end">{{ $record->customer?->full_name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-medium">Kendaraan</th>
                                <td class="fw-semibold text-end">
                                    {{ ($record->vehicle?->model?->brand?->brand_name ?? '').' '.($record->vehicle?->model?->model_name ?? '') }}
                                    ({{ $record->vehicle?->license_plate ?? '-' }})
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-medium">Periode</th>
                                <td class="fw-semibold text-end">
                                    {{ $record->rental_start_date?->format('d/m/Y') ?? '-' }} s.d.
                                    {{ $record->rental_end_date?->format('d/m/Y') ?? '-' }}
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-medium">Status</th>
                                <td class="fw-semibold text-end">{{ ucfirst($record->status ?? '-') }}</td>
                            </tr>
                        </table>
                    @else
                        <span class="badge rounded-pill bg-label-danger p-3 d-inline-flex">
                            <i class="ri-close-circle-line ri-2x"></i>
                        </span>
                        <h4 class="mt-4 mb-2">Kontrak Tidak Terverifikasi</h4>
                        <p class="text-muted mb-0">
                            QR tidak cocok dengan dokumen manapun, atau tanda verifikasi tidak valid. Hubungi pihak
                            {{ config('app.name') }} bila Anda merasa ini keliru.
                        </p>
                    @endif
                </div>
            </div>
            <p class="text-center text-muted mt-4 mb-0"><small>Halaman verifikasi publik &mdash; {{ config('app.name') }}</small></p>
        </div>
    </div>

    <script src="{{ asset('assets/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap.js') }}"></script>
    <script src="{{ asset('assets/js/main.js') }}"></script>
</body>

</html>

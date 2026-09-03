<!doctype html>

<html lang="en" class="light-style layout-wide customizer-hide" dir="ltr" data-theme="theme-default"
    data-assets-path="{{ asset('assets') }}/" data-template="vertical-menu-template" data-style="light">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Two Factor Auth - {{ config('app.name') }}</title>
    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon/favicon.ico') }}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&ampdisplay=swap"
        rel="stylesheet" />

    <!-- Icons -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/remixicon/remixicon.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/flag-icons.css') }}" />

    <!-- Menu waves for no-customizer fix -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/node-waves/node-waves.css') }}" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/core.css') }}" class="template-customizer-core-css" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/theme-default.css') }}"
        class="template-customizer-theme-css" />
    <link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/typeahead-js/typeahead.css') }}" />

    <!-- Page CSS -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}" />

    <!-- Helpers -->
    <script src="{{ asset('assets/vendor/js/helpers.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/template-customizer.js') }}"></script>
    <script src="{{ asset('assets/js/config.js') }}"></script>
</head>

<body>
    <!-- Content -->
    <div class="authentication-wrapper authentication-cover">
        <div class="authentication-inner row m-0">
            <div class="d-none d-lg-flex col-lg-7 col-xl-8 align-items-center justify-content-center p-12 pb-2">
                <img src="{{ asset('assets/img/illustrations/auth-login-illustration-light.png') }}"
                    class="auth-cover-illustration w-100" alt="auth-illustration"
                    data-app-light-img="illustrations/auth-login-illustration-light.png"
                    data-app-dark-img="illustrations/auth-login-illustration-dark.png" />
            </div>

            <div class="d-flex col-12 col-lg-5 col-xl-4 align-items-center authentication-bg position-relative py-sm-12 px-12 py-6">
                <div class="w-px-400 mx-auto pt-5 pt-lg-0">
                    <h4 class="mb-1">Two Factor Authentication</h4>
                    @if (!empty($nowa))
                        <p class="mb-5">Enter OTP Code sent to <b>{{ $nowa ?? '' }}</b></p>
                    @endif

                    @if (count($errors) > 0)
                        <div class="alert alert-danger">
                            @foreach ($errors->all() as $error)
                                {!! $error . '<br/>' !!}
                            @endforeach
                        </div>
                    @endif

                    @if (empty($nowa))
                        <div class="alert alert-danger mb-5">
                            WhatsApp number not found, please contact Administrator.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('verifyTwoFactor') }}" class="login-form">
                        @csrf

                        <div class="form-group">
                            <div class="d-flex justify-content-center gap-2 mb-5" id="inputs">
                                <input class="form-control text-center" style="width:50px;font-size:36px" type="text" inputmode="numeric" maxlength="1" autofocus />
                                <input class="form-control text-center" style="width:50px;font-size:36px" type="text" inputmode="numeric" maxlength="1" />
                                <input class="form-control text-center" style="width:50px;font-size:36px" type="text" inputmode="numeric" maxlength="1" />
                                <input class="form-control text-center" style="width:50px;font-size:36px" type="text" inputmode="numeric" maxlength="1" />
                                <input class="form-control text-center" style="width:50px;font-size:36px" type="text" inputmode="numeric" maxlength="1" />
                            </div>
                            <input type="hidden" name="otp">
                            <input type="hidden" name="redirect" value="{{ request()['redirect'] }}">
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mb-2">Confirm</button>
                        <a href="#!" onclick="event.preventDefault(); document.getElementById('frm-logout').submit();" class="btn btn-outline-secondary w-100">Back to Login</a>
                    </form>

                    <form id="frm-logout" action="{{ route('logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- / Content -->

    <!-- Core JS -->
    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/node-waves/node-waves.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/hammer/hammer.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/i18n/i18n.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/typeahead-js/typeahead.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/menu.js') }}"></script>
    <script src="{{ asset('assets/js/main.js') }}"></script>
    <script src="{{ asset('assets/js/pages-auth.js') }}"></script>

    <script type="text/javascript">
        const inputs = document.getElementById("inputs");

        inputs.addEventListener("input", function(e) {
            const target = e.target;
            const val = target.value;

            if (isNaN(val)) {
                target.value = "";
                return;
            }

            if (val != "") {
                const next = target.nextElementSibling;
                if (next) {
                    next.value = "";
                    next.focus();
                }
            }
        });

        inputs.addEventListener("keyup", function(e) {
            const target = e.target;
            const key = e.key.toLowerCase();

            if (key == "backspace" || key == "delete") {
                target.value = "";
                const prev = target.previousElementSibling;
                if (prev) {
                    prev.focus();
                }
                return;
            }
        });

        $(function() {
            $("body").on("submit", ".login-form", function(e) {
                const inputs = document.querySelectorAll("input.input, #inputs input");
                let otp = "";
                inputs.forEach(el => {
                    otp += el.value;
                });
                $('input[name="otp"]').val(otp);
                return true;
            });
        });
    </script>
</body>

</html>
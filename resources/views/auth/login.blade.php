<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <title>Masuk — {{ config('app.name') }}</title>

    {{-- Favicon & logo = satu sumber dari BrandAsset --}}
    <link rel="icon" type="image/png" href="{{ \App\Support\BrandAsset::faviconUrl() }}" />
    <link rel="apple-touch-icon" href="{{ \App\Support\BrandAsset::logoUrl() }}" />

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/css/auth.css') }}" />
</head>

<body>
    <main class="auth-shell">
        {{-- Panel branding --}}
        <section class="auth-brand">
            <div class="auth-brand__top">
                <span class="auth-brand__logo">
                    <img src="{{ \App\Support\BrandAsset::logoUrl() }}" alt="{{ config('app.name') }}" />
                </span>
                <span class="auth-brand__name">{{ config('app.name') }}</span>
            </div>

            <div class="auth-brand__body">
                <span class="auth-brand__eyebrow">Sistem Sewa &amp; Armada</span>
                <h1>Kelola sewa dan armada dalam satu tempat.</h1>
                <p>Pantau unit, serah terima, pembayaran, dan laporan operasional — semuanya terhubung dalam satu
                    sistem.</p>
                <img src="{{ asset('assets/img/illustrations/auth-login-illustration-light.png') }}"
                    class="auth-brand__art" alt="Ilustrasi layanan sewa" />
            </div>

            <div class="auth-brand__footer">&copy; {{ date('Y') }} {{ config('app.name') }}. Seluruh hak dilindungi.</div>
        </section>

        {{-- Panel form --}}
        <section class="auth-panel">
            <div class="auth-card">
                <div class="auth-card__mobile-logo">
                    <img src="{{ \App\Support\BrandAsset::logoUrl() }}" alt="{{ config('app.name') }}" />
                    <span>{{ config('app.name') }}</span>
                </div>

                <h2>Selamat datang kembali 👋</h2>
                <p class="auth-card__subtitle">Masuk dengan akun ERP Anda untuk melanjutkan.</p>

                @if ($errors->any())
                    <div class="auth-alert" role="alert">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="M12 8v5"></path>
                            <path d="M12 16h.01"></path>
                        </svg>
                        <div>
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="auth-form">
                    @csrf

                    <div class="field">
                        <label for="username">Username</label>
                        <div class="input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="8" r="4"></circle>
                                <path d="M4.5 20a7.5 7.5 0 0 1 15 0"></path>
                            </svg>
                            <input id="username" type="text" name="username" value="{{ old('username') }}"
                                placeholder="Username akun ERP" autocomplete="username" autofocus required />
                        </div>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <div class="input-wrap has-action">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="4" y="10" width="16" height="11" rx="2"></rect>
                                <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                            </svg>
                            <input id="password" type="password" name="password" placeholder="••••••••"
                                autocomplete="current-password" required />
                            <button type="button" class="toggle-password" data-target="password"
                                aria-label="Tampilkan password" title="Tampilkan password">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M3 3l18 18"></path>
                                    <path d="M10.6 5.2A9.8 9.8 0 0 1 12 5c5 0 9 4.5 10 7a11 11 0 0 1-2.2 3.1M6.2 6.3A11 11 0 0 0 2 12c1 2.5 5 7 10 7a9.7 9.7 0 0 0 3.2-.5"></path>
                                    <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="auth-form__options">
                        <label class="checkbox">
                            <input type="checkbox" name="remember" value="1" checked />
                            <span>Ingat saya</span>
                        </label>
                        <a href="{{ route('password.request') }}">Lupa password?</a>
                    </div>

                    <button type="submit" class="btn-submit">
                        <span>Masuk</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M5 12h14"></path>
                            <path d="m13 6 6 6-6 6"></path>
                        </svg>
                    </button>
                </form>

                <p class="auth-card__hint">Akses hanya untuk pengguna yang telah terdaftar.<br />Hubungi administrator jika
                    Anda belum memiliki akun.</p>
            </div>
        </section>
    </main>

    <script>
        document.querySelectorAll('.toggle-password').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.getElementById(button.dataset.target);
                var reveal = input.type === 'password';
                input.type = reveal ? 'text' : 'password';
                button.setAttribute('aria-label', reveal ? 'Sembunyikan password' : 'Tampilkan password');
                button.setAttribute('title', reveal ? 'Sembunyikan password' : 'Tampilkan password');
            });
        });

        document.querySelectorAll('.auth-form').forEach(function (form) {
            form.addEventListener('submit', function () {
                var button = form.querySelector('.btn-submit');
                if (!button || button.disabled) {
                    return;
                }
                button.disabled = true;
                button.querySelector('span').textContent = 'Memproses…';
            });
        });
    </script>
</body>

</html>

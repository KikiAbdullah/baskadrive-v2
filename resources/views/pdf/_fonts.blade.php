{{-- Font Inter (Google Font) di-embed base64 agar termuat di Chrome/Brave headless
    maupun dompdf. File: public/assets/fonts/inter --}}
@php
    $fontsDir = public_path('assets/fonts/inter');
    $inter400w = base64_encode(@file_get_contents($fontsDir . '/inter-400.woff2') ?: '');
    $inter500w = base64_encode(@file_get_contents($fontsDir . '/inter-500.woff2') ?: '');
    $inter700w = base64_encode(@file_get_contents($fontsDir . '/inter-700.woff2') ?: '');
    $inter900w = base64_encode(@file_get_contents($fontsDir . '/inter-900.woff2') ?: '');
    $inter400t = base64_encode(@file_get_contents($fontsDir . '/inter-400.ttf') ?: '');
    $inter700t = base64_encode(@file_get_contents($fontsDir . '/inter-700.ttf') ?: '');
    $inter900t = base64_encode(@file_get_contents($fontsDir . '/inter-900.ttf') ?: '');
@endphp
@font-face {
    font-family: 'Inter';
    font-style: normal;
    font-weight: 400;
    src: url(data:font/woff2;base64,{{ $inter400w }}) format('woff2'),
         url(data:font/ttf;base64,{{ $inter400t }}) format('truetype');
}

@font-face {
    font-family: 'Inter';
    font-style: normal;
    font-weight: 500;
    src: url(data:font/woff2;base64,{{ $inter500w }}) format('woff2');
}

@font-face {
    font-family: 'Inter';
    font-style: normal;
    font-weight: 700;
    src: url(data:font/woff2;base64,{{ $inter700w }}) format('woff2'),
         url(data:font/ttf;base64,{{ $inter700t }}) format('truetype');
}

@font-face {
    font-family: 'Inter';
    font-style: normal;
    font-weight: 900;
    src: url(data:font/woff2;base64,{{ $inter900w }}) format('woff2'),
         url(data:font/ttf;base64,{{ $inter900t }}) format('truetype');
}

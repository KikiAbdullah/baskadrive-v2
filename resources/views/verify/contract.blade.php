<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi Kontrak Sewa — BaskaDrive</title>
    <link rel="icon" type="image/png" sizes="256x256" href="{{ \App\Support\BrandAsset::faviconUrl() }}" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f5fa; color: #3b4055; display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 16px; }
        .box { max-width: 440px; width: 100%; background: #fff; border-radius: 12px; box-shadow: 0 6px 24px rgba(0,0,0,.08); padding: 32px; text-align: center; }
        .badge-ico { width: 64px; height: 64px; border-radius: 50%; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; font-size: 32px; }
        .ok { background: #e6f7ee; color: #28c76f; }
        .bad { background: #fdeaea; color: #ea5455; }
        h1 { font-size: 20px; margin-bottom: 8px; }
        p { color: #6b7280; font-size: 14px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; text-align: left; }
        td { padding: 8px 6px; border-bottom: 1px solid #eef0f4; }
        td:first-child { color: #6b7280; width: 42%; }
        td:last-child { font-weight: 600; }
        .foot { margin-top: 20px; font-size: 11px; color: #a8aab4; }
    </style>
</head>

<body>
    <div class="box">
        @if($record)
            <div class="badge-ico ok">&#10003;</div>
            <h1>Kontrak Sewa Asli &amp; Terverifikasi</h1>
            <p>Dokumen ini sah dan terdaftar pada sistem BaskaDrive.</p>
            <table>
                <tr><td>Nomor Kontrak</td><td>{{ $record->rental_code }}</td></tr>
                <tr><td>Penyewa</td><td>{{ $record->customer?->full_name ?? '-' }}</td></tr>
                <tr><td>Kendaraan</td>
                    <td>{{ ($record->vehicle?->model?->brand?->brand_name ?? '').' '.($record->vehicle?->model?->model_name ?? '') }} ({{ $record->vehicle?->license_plate ?? '-' }})</td></tr>
                <tr><td>Periode</td><td>{{ $record->rental_start_date?->format('d/m/Y') ?? '-' }} s.d. {{ $record->rental_end_date?->format('d/m/Y') ?? '-' }}</td></tr>
                <tr><td>Status</td><td>{{ ucfirst($record->status ?? '-') }}</td></tr>
            </table>
        @else
            <div class="badge-ico bad">&#10007;</div>
            <h1>Kontrak Tidak Terverifikasi</h1>
            <p>QR tidak cocok dengan dokumen manapun, atau tanda verifikasi tidak valid. Hubungi pihak BaskaDrive bila Anda merasa ini keliru.</p>
        @endif
        <div class="foot">Halaman verifikasi publik &mdash; BaskaDrive Rental ERP</div>
    </div>
</body>

</html>

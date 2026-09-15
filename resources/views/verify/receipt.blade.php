<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi Kwitansi — BaskaDrive</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f5fa; color: #3b4055; display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 16px; }
        .box { max-width: 420px; width: 100%; background: #fff; border-radius: 12px; box-shadow: 0 6px 24px rgba(0,0,0,.08); padding: 32px; text-align: center; }
        .badge-ico { width: 64px; height: 64px; border-radius: 50%; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; font-size: 32px; }
        .ok { background: #e6f7ee; color: #28c76f; }
        .bad { background: #fdeaea; color: #ea5455; }
        h1 { font-size: 20px; margin-bottom: 8px; }
        p { color: #6b7280; font-size: 14px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; text-align: left; }
        td { padding: 8px 6px; border-bottom: 1px solid #eef0f4; }
        td:first-child { color: #6b7280; width: 40%; }
        td:last-child { font-weight: 600; }
        .foot { margin-top: 20px; font-size: 11px; color: #a8aab4; }
    </style>
</head>

<body>
    <div class="box">
        @if($record)
            <div class="badge-ico ok">&#10003;</div>
            <h1>Kwitansi Asli &amp; Terverifikasi</h1>
            <p>Dokumen ini sah dan terdaftar pada sistem BaskaDrive.</p>
            <table>
                <tr><td>Nomor Kwitansi</td><td>PAY-{{ str_pad($record->payment_id, 5, '0', STR_PAD_LEFT) }}</td></tr>
                @if($record->invoice)
                    <tr><td>Invoice</td><td>{{ $record->invoice->invoice_number }}</td></tr>
                @endif
                <tr><td>Nominal</td><td>Rp {{ number_format($record->amount, 0, ',', '.') }}</td></tr>
                <tr><td>Tanggal Bayar</td><td>{{ $record->payment_date?->format('d F Y H:i') ?? '-' }}</td></tr>
                <tr><td>Status</td><td>{{ ucfirst($record->status ?? '-') }}</td></tr>
            </table>
        @else
            <div class="badge-ico bad">&#10007;</div>
            <h1>Kwitansi Tidak Dikenal</h1>
            <p>Tautan/QR code yang Anda pindai tidak valid atau dokumen dengan ID #{{ $id }} tidak ditemukan. Hubungi kantor BaskaDrive terdekat jika Anda menerima dokumen ini.</p>
        @endif
        <div class="foot">Sistem Verifikasi Dokumen {{ config('app.name') }}</div>
    </div>
</body>

</html>

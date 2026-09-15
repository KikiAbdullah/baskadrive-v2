<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; color:#333;">
    <p>Yth. {{ $item->rental?->customer?->full_name ?? 'Pelanggan' }},</p>
    <p>
        Berikut kami lampirkan invoice <strong>{{ $item->invoice_number }}</strong>
        untuk sewa <strong>{{ $item->rental?->rental_code ?? '-' }}</strong>.
    </p>
    <ul>
        <li>Total Tagihan: <strong>Rp {{ number_format($item->total_amount, 0, ',', '.') }}</strong></li>
        <li>Jatuh Tempo: <strong>{{ $item->due_date?->format('d F Y') }}</strong></li>
    </ul>
    <p>{{ $settings['invoice_footer_note'] ?? 'Terima kasih atas kepercayaan Anda.' }}</p>
    <p>
        Hormat kami,<br>
        <strong>{{ $settings['company_name'] ?? config('app.name') }}</strong><br>
        {{ $settings['company_phone'] ?? '' }} {{ !empty($settings['company_email']) ? ' | '.$settings['company_email'] : '' }}
    </p>
</body>
</html>

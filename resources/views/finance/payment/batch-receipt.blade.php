<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Kwitansi Batch {{ substr($groupId, 0, 8) }}</title>
    @php
        // Logo data-URI (kompatibel Chrome & Dompdf) — pola sama dengan kwitansi tunggal
        $logoDataUri = null;
        if (!empty($settings['company_logo'])) {
            $logoPath = public_path('storage/' . $settings['company_logo']);
            if (is_file($logoPath)) {
                $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
                $mime = match ($ext) { 'svg' => 'image/svg+xml', 'jpg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', default => 'image/png' };
                $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
            }
        }
        if ($logoDataUri === null) {
            // Fallback: logo bawaan app_local/img/logo.png (BrandAsset)
            $logoDataUri = \App\Support\BrandAsset::logoDataUri();
        }

        // Kwitansi batch dikenali dari pembayaran pertama grup (HMAC sama dgn kwitansi tunggal)
        $receiptNo = 'BATCH-' . strtoupper(substr($groupId, 0, 8));
        $verifyUrl = route('verify.receipt', ['payment' => $item->payment_id, 't' => \App\Support\QrCode::receiptSignature($item->payment_id)]);
        $customer = $item->rental?->customer;
    @endphp
    <style>
        @page {
            size: A4;
            margin: 12mm 14mm 13mm 14mm;
        }

        body {
            margin: 0;
            padding: 0;
            color: #3b4055;
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10px;
        }

        .head {
            background: #666cff;
            color: #ffffff;
            padding: 5mm 6mm;
            border-radius: 2.5mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 3mm;
        }

        .brand img.logo {
            width: 11mm;
            height: 11mm;
            border-radius: 2mm;
            background: #ffffff;
            padding: 1mm;
        }

        .brand-name {
            font-size: 13px;
            font-weight: bold;
        }

        .brand-tag {
            font-size: 8px;
            opacity: .85;
        }

        .head-right {
            text-align: right;
            font-size: 8px;
            opacity: .95;
        }

        .doc-title {
            font-size: 14px;
            font-weight: bold;
            letter-spacing: .5px;
        }

        .chip {
            display: inline-block;
            background: #28c76f;
            color: #fff;
            font-weight: bold;
            font-size: 8px;
            padding: 1.5mm 3mm;
            border-radius: 5mm;
            letter-spacing: 1px;
            margin-bottom: 1.5mm;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4mm;
        }

        .meta td {
            padding: 1.2mm 0;
            vertical-align: top;
        }

        .meta td.lbl {
            color: #6b7280;
            width: 34%;
        }

        .meta td.val {
            font-weight: bold;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4mm;
        }

        table.items th,
        table.items td {
            border: .3mm solid #e0e3e8;
            padding: 2mm 2.5mm;
            font-size: 9px;
        }

        table.items th {
            background: #f1f2f8;
            text-align: left;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: #5b6279;
        }

        .num {
            text-align: right;
            white-space: nowrap;
        }

        tr.total td {
            background: #f1f2f8;
            font-weight: bold;
            font-size: 10.5px;
        }

        .foot {
            margin-top: 5mm;
            display: flex;
            justify-content: space-between;
            gap: 6mm;
        }

        .verify {
            width: 58%;
            border: .3mm dashed #b9bdc9;
            border-radius: 2mm;
            padding: 3mm;
            font-size: 8px;
            color: #6b7280;
            display: flex;
            gap: 3mm;
            align-items: center;
        }

        .verify img {
            width: 20mm;
            height: 20mm;
            flex-shrink: 0;
        }

        .verify code {
            font-size: 8px;
            color: #3b4055;
        }

        .sign {
            width: 34%;
            text-align: center;
            padding-top: 2mm;
        }

        .sign .line {
            border-top: .3mm solid #3b4055;
            margin-top: 16mm;
            padding-top: 1.5mm;
            font-size: 9px;
        }

        .thanks {
            margin-top: 5mm;
            font-size: 8.5px;
            color: #6b7280;
            border-top: .3mm solid #e0e3e8;
            padding-top: 2.5mm;
        }
    </style>
</head>

<body>
    <div class="page">
        <div class="head">
            <div class="brand">
                @if($logoDataUri)
                    <img class="logo" src="{{ $logoDataUri }}" alt="logo">
                @endif
                <div>
                    <div class="brand-name">{{ $settings['company_name'] ?? config('app.name', 'BaskaDrive') }}</div>
                    <div class="brand-tag">{{ $settings['company_tagline'] ?? 'Rental Kendaraan' }}</div>
                </div>
            </div>
            <div class="head-right">
                <div class="doc-title">KWITANSI PEMBAYARAN BORONGAN</div>
                <div>No. {{ $receiptNo }}</div>
            </div>
        </div>

        <table class="meta">
            <tr>
                <td class="lbl">Diterima dari</td>
                <td class="val">{{ $customer?->full_name ?? '-' }}@if($customer?->customer_company_name) &nbsp;({{ $customer->customer_company_name }})@endif</td>
                <td class="lbl" style="width: 22%;">Tanggal</td>
                <td class="val">{{ $item->payment_date?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td class="lbl">Metode Pembayaran</td>
                <td class="val">{{ ucfirst(str_replace('_', ' ', $item->payment_method)) }}@if($item->reference_number) &nbsp;&bull;&nbsp; Ref: {{ $item->reference_number }}@endif</td>
                <td class="lbl">Jumlah Invoice</td>
                <td class="val">{{ $payments->count() }} invoice</td>
            </tr>
            @if($item->notes)
                <tr>
                    <td class="lbl">Catatan</td>
                    <td class="val" colspan="3">{{ $item->notes }}</td>
                </tr>
            @endif
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th style="width: 6%;">No</th>
                    <th style="width: 22%;">Invoice</th>
                    <th style="width: 14%;">Sewa</th>
                    <th class="num">Total Invoice</th>
                    <th class="num">Dibayar Sblmnya</th>
                    <th class="num">Bayar Ini</th>
                    <th class="num">Sisa Setelah Ini</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payments as $i => $p)
                    @php
                        $inv = $p->invoice;
                        $paidBefore = (float) $inv->paid_amount - (float) $p->amount;
                        $remaining = (float) $inv->total_amount - (float) $inv->paid_amount;
                    @endphp
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $inv->invoice_number }}</td>
                        <td>{{ $inv->rental?->rental_code ?? '-' }}</td>
                        <td class="num">Rp {{ number_format((float) $inv->total_amount, 2, ',', '.') }}</td>
                        <td class="num">Rp {{ number_format(max(0, $paidBefore), 2, ',', '.') }}</td>
                        <td class="num"><strong>Rp {{ number_format((float) $p->amount, 2, ',', '.') }}</strong></td>
                        <td class="num">{{ $remaining > 0.01 ? 'Rp ' . number_format($remaining, 2, ',', '.') : 'LUNAS' }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="5" class="num">TOTAL DIBAYAR</td>
                    <td class="num">Rp {{ number_format((float) $total, 2, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        <div class="foot">
            <div class="verify">
                {!! \App\Support\QrCode::dataUriSvg($verifyUrl, 90) !!}
                <div>
                    <strong>Verifikasi keaslian kwitansi</strong><br>
                    Pindai QR Code di atas atau kunjungi <code>{{ url('/verify/receipt') . '/' . $item->payment_id }}</code>.<br>
                    Kwitansi batch ini sah bila halaman verifikasi menunjukkan status VALID dengan
                    nomor kwitansi <code>{{ $item->payment_id }}</code>.
                </div>
            </div>
            <div class="sign">
                <div class="line">Penerima, {{ auth()->user()?->full_name ?? '' }}</div>
            </div>
        </div>

        <div class="thanks">
            Terima kasih, {{ $customer?->full_name ?? 'Pelanggan' }}. Kwitansi borongan ini merangkum pembayaran
            {{ $payments->count() }} invoice sekaligus dan adalah bukti pembayaran yang sah dari {{ $settings['company_name'] ?? 'kami' }}.
            {{ $settings['invoice_footer_note'] ?? '' }}
        </div>
    </div>
</body>

</html>

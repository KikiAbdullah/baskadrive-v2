<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Kwitansi {{ $item->payment_id }}</title>
    @php
        // Logo data-URI (kompatibel Chrome & Dompdf)
        $logoDataUri = null;
        if (!empty($settings['company_logo'])) {
            $logoPath = public_path('storage/' . $settings['company_logo']);
            if (is_file($logoPath)) {
                $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
                $mime = match ($ext) { 'svg' => 'image/svg+xml', 'jpg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', default => 'image/png' };
                $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
            }
        }
        if ($logoDataUri === null && is_file(public_path('app_local/img/logo-default.svg'))) {
            // Logo default SVG vektor BaskaDrive saat logo custom belum diunggah (audit 3)
            $logoDataUri = 'data:image/svg+xml;base64,' . base64_encode(file_get_contents(public_path('app_local/img/logo-default.svg')));
        }

        $payMap = [
            'paid' => ['Lunas', 'chip-green'],
            'partial' => ['Dibayar Sebagian', 'chip-amber'],
            'completed' => ['Selesai', 'chip-green'],
            'pending' => ['Pending', 'chip-amber'],
            'failed' => ['Gagal', 'chip-red'],
            'refunded' => ['Refund', 'chip-gray'],
        ];
        $pay = $payMap[$item->status] ?? [ucfirst($item->status ?? '-'), 'chip-gray'];
    @endphp
    <style>
        /* ===== FONT: Inter 400/700/900 (base64 TTF) ===== */
        @font-face { font-family: 'Inter'; font-weight: 400; src: url(data:font/ttf;base64,{{ base64_encode(file_get_contents(public_path('assets/fonts/inter/inter-400.ttf'))) }}) format('truetype'); }
        @font-face { font-family: 'Inter'; font-weight: 700; src: url(data:font/ttf;base64,{{ base64_encode(file_get_contents(public_path('assets/fonts/inter/inter-700.ttf'))) }}) format('truetype'); }
        @font-face { font-family: 'Inter'; font-weight: 900; src: url(data:font/ttf;base64,{{ base64_encode(file_get_contents(public_path('assets/fonts/inter/inter-900.ttf'))) }}) format('truetype'); }

        @page {
            size: A4;
            margin: 12mm 14mm 13mm 14mm;
        }

        body {
            margin: 0;
            padding: 0;
            color: #3b4055;
            font-family: 'Inter', Helvetica, Arial, sans-serif;
            font-size: 10px;
        }

        .page { width: 100%; position: relative; }

        /* ================= HEADER (band biru) ================= */
        .head {
            background: #666cff;
            color: #ffffff;
            padding: 5mm 6mm;
            border-radius: 2.5mm;
        }

        table.head-t { width: 100%; border-collapse: collapse; }
        table.head-t td { vertical-align: middle; }

        .logo-cell {
            width: 14mm;
            height: 14mm;
            background: #ffffff;
            text-align: center;
            vertical-align: middle;
            font-weight: 900;
            font-size: 18px;
            color: #666cff;
            letter-spacing: -0.5px;
        }

        .logo-cell img { max-width: 12mm; max-height: 12mm; display: block; margin: 0 auto; }

        .brand-name {
            font-size: 17px;
            font-weight: 900;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            line-height: 1.1;
        }

        .brand-tag {
            font-size: 7px;
            font-weight: 700;
            letter-spacing: 2.2px;
            text-transform: uppercase;
            color: #d5d8ff;
            margin-top: 1mm;
        }

        .head-doc { text-align: right; }
        .head-doc .no { font-size: 13.5px; font-weight: 900; letter-spacing: 0.5px; }
        .head-doc .dt { font-size: 8px; color: #d5d8ff; margin-top: 0.8mm; }

        .head-contact {
            margin-top: 2.5mm;
            font-size: 7.5px;
            color: #d5d8ff;
            border-top: 1px solid #8b94ff;
            padding-top: 1.8mm;
            line-height: 1.55;
        }

        /* ================= TITLE + CHIP ================= */
        table.title-t { width: 100%; border-collapse: collapse; margin-top: 3.5mm; }
        table.title-t td { vertical-align: middle; }

        .doc-title {
            margin: 0;
            font-size: 21px;
            font-weight: 900;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #3b4055;
            line-height: 1.1;
        }

        .doc-sub {
            margin: 1mm 0 0;
            font-size: 7.5px;
            color: #a8aab4;
            letter-spacing: 2px;
            text-transform: uppercase;
            font-weight: 700;
        }

        .chip {
            display: inline-block;
            padding: 1.1mm 2.8mm;
            border-radius: 10px;
            font-size: 7.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-left: 1.5mm;
        }

        .chip-green { background: #72e12829; color: #3ea016; border: 1px solid #b8ef9c; }
        .chip-amber { background: #fdb52829; color: #b07d0e; border: 1px solid #f5dfa3; }
        .chip-red { background: #ff4d4929; color: #d33a3a; border: 1px solid #f5bcbc; }
        .chip-gray { background: #82868b1f; color: #6a6f85; border: 1px solid #dcdfe8; }

        /* ================= CARD ================= */
        .card {
            border: 1px solid #e5e5e8;
            border-radius: 2.5mm;
            margin-top: 3mm;
            overflow: hidden;
        }

        .card-head {
            background: #666cff14;
            color: #4c52f0;
            font-size: 8px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.8px;
            padding: 1.9mm 4mm;
        }

        .card-head .letter {
            display: inline-block;
            background: #666cff;
            color: #ffffff;
            width: 4.5mm;
            height: 4.5mm;
            text-align: center;
            line-height: 4.5mm;
            border-radius: 1mm;
            margin-right: 2mm;
            font-size: 9px;
        }

        table.kv { width: 100%; border-collapse: collapse; }

        table.kv td {
            padding: 1.15mm 4mm;
            vertical-align: top;
            border-bottom: 1px solid #f2f3f8;
        }

        table.kv tr:last-child td { border-bottom: none; }

        table.kv td.k { width: 22%; color: #a8aab4; font-size: 8.5px; }
        table.kv td.v { font-weight: 700; color: #3b4055; width: 28%; }

        /* ================= TOTAL BAR ================= */
        .total-bar {
            background: #666cff;
            color: #ffffff;
            padding: 2.6mm 4mm;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            width: 100%;
        }

        .total-bar .amt { float: right; font-size: 15px; font-weight: 900; text-transform: none; letter-spacing: 0; }

        /* ================= NOTE ================= */
        .note {
            border: 1px solid #e5e5e8;
            border-left: 3px solid #666cff;
            border-radius: 0 2.5mm 2.5mm 0;
            padding: 2mm 4mm;
            font-size: 8px;
            line-height: 1.55;
            color: #676a7b;
            margin-top: 2.5mm;
        }

        .note .t {
            font-weight: 900;
            font-size: 8px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #666cff;
            margin-bottom: 1mm;
        }

        /* ================= FOOTER ================= */
        .foot {
            position: absolute;
            bottom: 6mm;
            left: 14mm;
            right: 14mm;
            border-top: 1px solid #e5e5e8;
            padding-top: 2mm;
            font-size: 7.5px;
            color: #a8aab4;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="page">

        {{-- ============ HEADER BAND ============ --}}
        <div class="head">
            <table class="head-t">
                <tr>
                    <td style="width:60%;">
                        <table>
                            <tr>
                                <td class="logo-cell">
                                    @if($logoDataUri)
                                        <img src="{{ $logoDataUri }}" alt="">
                                    @else
                                        {{ strtoupper(substr($settings['company_name'] ?? 'BD', 0, 2)) }}
                                    @endif
                                </td>
                                <td style="padding-left: 3mm;">
                                    <div class="brand-name">{{ $settings['company_name'] }}</div>
                                    <div class="brand-tag">{{ $settings['company_tagline'] ?: 'Rental Kendaraan' }}</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td class="head-doc">
                        <div class="no">PAY-{{ str_pad($item->payment_id, 5, '0', STR_PAD_LEFT) }}</div>
                        <div class="dt">{{ $item->payment_date?->locale('id')->translatedFormat('d F Y') ?? '-' }}</div>
                    </td>
                </tr>
            </table>
            <div class="head-contact">
                {{ $settings['company_address'] ?: '-' }}
                @if($settings['company_phone']) &nbsp;&bull;&nbsp; {{ $settings['company_phone'] }} @endif
                @if($settings['company_email']) &nbsp;&bull;&nbsp; {{ $settings['company_email'] }} @endif
            </div>
        </div>

        {{-- ============ TITLE + CHIP ============ --}}
        <div class="title-band">
            <table class="title-t">
                <tr>
                    <td>
                        <h1 class="doc-title">Kwitansi</h1>
                        <p class="doc-sub">Bukti Pembayaran Sewa Kendaraan</p>
                    </td>
                    <td style="text-align: right;">
                        <span class="chip {{ $pay[1] }}">{{ $pay[0] }}</span>
                    </td>
                </tr>
            </table>
        </div>

        {{-- ============ CARD A: DETAIL PEMBAYARAN ============ --}}
        <div class="card">
            <div class="card-head"><span class="letter">A</span> Detail Pembayaran</div>
            <table class="kv">
                <tr>
                    <td class="k">No. Kwitansi</td>
                    <td class="v">PAY-{{ str_pad($item->payment_id, 5, '0', STR_PAD_LEFT) }}</td>
                    <td class="k">No. Invoice</td>
                    <td class="v">{{ $item->invoice?->invoice_number ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="k">Kode Sewa</td>
                    <td class="v">{{ $item->rental?->rental_code ?? '-' }}</td>
                    <td class="k">Pelanggan</td>
                    <td class="v">{{ $item->rental?->customer?->full_name ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="k">Tanggal Bayar</td>
                    <td class="v">{{ $item->payment_date?->locale('id')->translatedFormat('d F Y') ?? '-' }}</td>
                    <td class="k">Metode</td>
                    <td class="v">{{ ucfirst($item->payment_method ?? '-') }}</td>
                </tr>
                <tr>
                    <td class="k">No. Referensi</td>
                    <td class="v">{{ $item->reference_number ?? '-' }}</td>
                    <td class="k">Status</td>
                    <td class="v">{{ ucfirst($item->status ?? '-') }}</td>
                </tr>
            </table>
        </div>

        {{-- ============ TOTAL BAR ============ --}}
        <div class="card">
            <div class="card-head"><span class="letter">B</span> Jumlah Dibayarkan</div>
            <div class="total-bar">
                TOTAL DIBAYAR
                <span class="amt">{{ \App\Support\AppSettings::money($item->amount ?? 0) }}</span>
            </div>
        </div>

        {{-- ============ NOTE ============ --}}
        <div class="note">
            <div class="t">Keterangan</div>
            Terima kasih, {{ $item->rental?->customer?->full_name ?? 'Pelanggan' }}. Kwitansi ini adalah bukti pembayaran yang sah dari {{ $settings['company_name'] }}.
            {{ $settings['invoice_footer_note'] ?? '' }}
        </div>

        {{-- ============ QR VERIFIKASI KEASLIAN (audit 3) ============ --}}
        <div style="margin-top: 4mm;">
            <table style="width: 100%;">
                <tr>
                    <td></td>
                    <td style="text-align: right; font-size: 7.5px; color: #6b7280; padding-top: 2mm;">
                        Pindai untuk verifikasi<br>keaslian kwitansi
                    </td>
                    <td style="width: 4mm;"></td>
                    <td>
                        <img src="{{ \App\Support\QrCode::dataUriSvg(route('verify.receipt', ['payment' => $item->payment_id, 't' => \App\Support\QrCode::receiptSignature($item->payment_id)]), 100) }}"
                            style="width: 20mm; height: 20mm;" alt="QR Verifikasi">
                    </td>
                </tr>
            </table>
        </div>

        <div class="foot">
            Kwitansi No. PAY-{{ str_pad($item->payment_id, 5, '0', STR_PAD_LEFT) }} &mdash; dicetak otomatis dari sistem {{ $settings['company_name'] }} pada {{ now()->locale('id')->translatedFormat('d F Y H:i') }} &mdash; dokumen sah tanpa tanda tangan basah
        </div>
    </div>
</body>

</html>

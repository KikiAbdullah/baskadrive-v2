<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Kontrak Sewa - {{ $rental->rental_code }}</title>
    @php
        // Logo data-URI (kompatibel Chrome & Dompdf)
        $logoDataUri = null;
        if (!empty($settings['company_logo'])) {
            $logoPath = public_path('storage/' . $settings['company_logo']);
            if (is_file($logoPath)) {
                $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
                $mime = match ($ext) {
                    'svg' => 'image/svg+xml',
                    'jpg', 'jpeg' => 'image/jpeg',
                    'webp' => 'image/webp',
                    default => 'image/png',
                };
                $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
            }
        }
        if ($logoDataUri === null) {
            // Fallback: logo bawaan app_local/img/logo.png (BrandAsset)
            $logoDataUri = \App\Support\BrandAsset::logoDataUri();
        }

        $taxLabel = $settings['tax_label'] ?? 'PPN';
        $taxPercent = $rental->tax_percent ?? ($settings['tax_enabled'] ?? true ? $settings['tax_percent'] : 0);
        $depositAmount = $rental->deposit_amount ?? 0;
        $subtotal = ($rental->total_amount ?? 0) - ($rental->tax_amount ?? 0);

        $stMap = [
            'reserved' => ['Reservasi', 'chip-amber'],
            'ongoing' => ['Berjalan', 'chip-blue'],
            'completed' => ['Selesai', 'chip-green'],
            'cancelled' => ['Dibatalkan', 'chip-gray'],
        ];
        $st = $stMap[$rental->status] ?? [ucfirst($rental->status), 'chip-gray'];
        $payMap = [
            'paid' => ['Lunas', 'chip-green'],
            'partial' => ['Dibayar Sebagian', 'chip-amber'],
            'unpaid' => ['Belum Bayar', 'chip-red'],
            'refunded' => ['Refund', 'chip-gray'],
        ];
        $pay = $payMap[$rental->payment_status] ?? [ucfirst($rental->payment_status ?? '-'), 'chip-gray'];
    @endphp
    <style>
        /* ===== FONT: Inter 400/700/900 (base64 TTF) ===== */
        @font-face {
            font-family: 'Inter';
            font-weight: 400;
            src: url(data:font/ttf;base64,{{ base64_encode(file_get_contents(public_path('assets/fonts/inter/inter-400.ttf'))) }}) format('truetype');
        }

        @font-face {
            font-family: 'Inter';
            font-weight: 700;
            src: url(data:font/ttf;base64,{{ base64_encode(file_get_contents(public_path('assets/fonts/inter/inter-700.ttf'))) }}) format('truetype');
        }

        @font-face {
            font-family: 'Inter';
            font-weight: 900;
            src: url(data:font/ttf;base64,{{ base64_encode(file_get_contents(public_path('assets/fonts/inter/inter-900.ttf'))) }}) format('truetype');
        }

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

        .page {
            width: 100%;
            position: relative;
        }

        /* ================= HEADER (band biru) ================= */
        .head {
            background: #666cff;
            color: #ffffff;
            padding: 5mm 6mm;
            border-radius: 2.5mm;
        }

        table.head-t {
            width: 100%;
            border-collapse: collapse;
        }

        table.head-t td {
            vertical-align: middle;
        }

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

        .logo-cell img {
            max-width: 12mm;
            max-height: 12mm;
            display: block;
            margin: 0 auto;
        }

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

        .head-doc {
            text-align: right;
        }

        .head-doc .no {
            font-size: 13.5px;
            font-weight: 900;
            letter-spacing: 0.5px;
        }

        .head-doc .dt {
            font-size: 8px;
            color: #d5d8ff;
            margin-top: 0.8mm;
        }

        .head-contact {
            margin-top: 2.5mm;
            font-size: 7.5px;
            color: #d5d8ff;
            border-top: 1px solid #8b94ff;
            padding-top: 1.8mm;
            line-height: 1.55;
        }

        /* ================= TITLE + CHIPS ================= */
        table.title-t {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3.5mm;
        }

        table.title-t td {
            vertical-align: middle;
        }

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

        .chip-blue {
            background: #666cff1f;
            color: #4c52f0;
            border: 1px solid #c5c9ff;
        }

        .chip-green {
            background: #72e12829;
            color: #3ea016;
            border: 1px solid #b8ef9c;
        }

        .chip-amber {
            background: #fdb52829;
            color: #b07d0e;
            border: 1px solid #f5dfa3;
        }

        .chip-red {
            background: #ff4d4929;
            color: #d33a3a;
            border: 1px solid #f5bcbc;
        }

        .chip-gray {
            background: #82868b1f;
            color: #6a6f85;
            border: 1px solid #dcdfe8;
        }

        /* ================= CARDS ================= */
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

        table.kv {
            width: 100%;
            border-collapse: collapse;
        }

        table.kv td {
            padding: 1.15mm 4mm;
            vertical-align: top;
            border-bottom: 1px solid #f2f3f8;
        }

        table.kv tr:last-child td {
            border-bottom: none;
        }

        table.kv td.k {
            width: 22%;
            color: #a8aab4;
            font-size: 8.5px;
        }

        table.kv td.v {
            font-weight: 700;
            color: #3b4055;
            width: 28%;
        }

        /* ================= STRIP PERIODE ================= */
        table.strip {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3mm;
            border: 1px solid #e5e5e8;
            border-radius: 2.5mm;
            overflow: hidden;
        }

        table.strip th {
            background: #666cff;
            color: #ffffff;
            font-size: 7.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            text-align: left;
            padding: 1.9mm 4mm;
        }

        table.strip th.r {
            text-align: right;
        }

        table.strip td {
            padding: 1.8mm 4mm;
            font-size: 10px;
            font-weight: 700;
            color: #3b4055;
            background: #f8f9ff;
            border-top: 1px solid #e5e5e8;
        }

        table.strip td.r {
            text-align: right;
        }

        /* ================= TABEL ITEM ================= */
        .card .items {
            width: 100%;
            border-collapse: collapse;
        }

        .card .items th {
            background: #f4f5ff;
            color: #7d82a0;
            font-size: 7.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            text-align: left;
            padding: 1.7mm 4mm;
            border-bottom: 1px solid #e5e5e8;
        }

        .card .items th.r,
        .card .items td.r {
            text-align: right;
        }

        .card .items td {
            padding: 1.5mm 4mm;
            vertical-align: top;
            border-bottom: 1px solid #f2f3f8;
            color: #3b4055;
        }

        .card .items tr:last-child td {
            border-bottom: none;
        }

        .item-sub {
            font-size: 8px;
            color: #a8aab4;
        }

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

        .total-bar .amt {
            float: right;
            font-size: 15px;
            font-weight: 900;
            text-transform: none;
            letter-spacing: 0;
        }

        /* ================= SUMMARY ================= */
        table.sum {
            width: 100%;
            border-collapse: collapse;
        }

        table.sum td {
            padding: 1.3mm 4mm;
            border-bottom: 1px solid #f2f3f8;
            font-size: 9.5px;
        }

        table.sum td.k {
            color: #a8aab4;
        }

        table.sum td.v {
            text-align: right;
            font-weight: 700;
            color: #3b4055;
        }

        table.sum td.v.green {
            color: #3ea016;
        }

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
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* Klausul panjang / banyak add-on: perkecil font agar blok TTD tidak terpotong (audit 3) */
        .note.note-compact {
            font-size: 7px;
            line-height: 1.3;
        }

        .note .t {
            font-weight: 900;
            font-size: 8px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #666cff;
            margin-bottom: 1mm;
        }

        /* ================= TTD ================= */
        table.ttd {
            width: 100%;
            margin-top: 5mm;
        }

        table.ttd td {
            width: 33.3%;
            text-align: center;
            font-size: 9px;
            color: #3b4055;
        }

        .line {
            border-top: 1.5px solid #666cff;
            margin: 6mm 8mm 1mm;
            padding-top: 1mm;
            font-weight: 700;
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
                                    @if ($logoDataUri)
                                        <img src="{{ $logoDataUri }}" alt="">
                                    @else
                                        {{ strtoupper(substr($settings['company_name'] ?? 'BD', 0, 2)) }}
                                    @endif
                                </td>
                                <td style="padding-left: 3mm;">
                                    <div class="brand-name">{{ $settings['company_name'] }}</div>
                                    <div class="brand-tag">{{ $settings['company_tagline'] ?: 'Rental Kendaraan' }}
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td class="head-doc">
                        <div class="no">{{ $rental->rental_code ?? '-' }}</div>
                        <div class="dt">Diterbitkan:
                            {{ $rental->created_at?->format('d F Y') ?? now()->format('d F Y') }}</div>
                    </td>
                </tr>
            </table>
            <div class="head-contact">
                {{ $settings['company_address'] ?: '-' }}
                @if ($settings['company_phone'])
                    &nbsp;&bull;&nbsp; {{ $settings['company_phone'] }}
                @endif
                @if ($settings['company_email'])
                    &nbsp;&bull;&nbsp; {{ $settings['company_email'] }}
                @endif
            </div>
        </div>

        {{-- ============ TITLE + CHIPS ============ --}}
        <div class="title-band">
            <table class="title-t">
                <tr>
                    <td>
                        <h1 class="doc-title">Kontrak Sewa</h1>
                        <p class="doc-sub">Perjanjian Persewaan Kendaraan</p>
                    </td>
                    <td style="text-align: right;">
                        <span class="chip {{ $st[1] }}">{{ $st[0] }}</span>
                        <span class="chip {{ $pay[1] }}">{{ $pay[0] }}</span>
                    </td>
                </tr>
            </table>
        </div>

        {{-- ============ CARD A: PENYEWA & KENDARAAN ============ --}}
        <div class="card">
            <div class="card-head"><span class="letter">A</span> Data Penyewa &nbsp;&mdash;&nbsp; Data Kendaraan</div>
            <table class="kv">
                <tr>
                    <td class="k">Nama Penyewa</td>
                    <td class="v">{{ $rental->customer?->full_name ?? '-' }}</td>
                    <td class="k">Merk / Model</td>
                    <td class="v">{{ $rental->vehicle?->model?->brand?->brand_name ?? '-' }}
                        {{ $rental->vehicle?->model?->model_name ?? '' }}</td>
                </tr>
                <tr>
                    <td class="k">Tipe</td>
                    <td class="v">
                        {{ $rental->customer?->customer_type == 'corporate' ? 'Perusahaan' : 'Individu' }}</td>
                    <td class="k">No. Polisi</td>
                    <td class="v">{{ $rental->vehicle?->license_plate ?? '-' }}
                        ({{ $rental->vehicle?->year ?? '-' }})</td>
                </tr>
                <tr>
                    <td class="k">Telepon</td>
                    <td class="v">{{ $rental->customer?->phone ?? '-' }}</td>
                    <td class="k">Warna</td>
                    <td class="v">{{ $rental->vehicle?->color ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="k">Email</td>
                    <td class="v">{{ $rental->customer?->email ?? '-' }}</td>
                    <td class="k">Transmisi</td>
                    <td class="v">{{ $rental->vehicle?->model?->transmission ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="k">No. KTP</td>
                    <td class="v">{{ $rental->customer?->id_card_number ?? '-' }}</td>
                    <td class="k">Sopir</td>
                    <td class="v">
                        {{ $rental->is_with_driver ? $rental->driver?->full_name ?? '-' : 'Lepas Kunci' }}</td>
                </tr>
                <tr>
                    <td class="k">No. SIM</td>
                    <td class="v">{{ $rental->customer?->driver_license_number ?? '-' }}</td>
                    <td class="k">Odometer</td>
                    <td class="v">{{ number_format($rental->vehicle?->mileage ?? 0, 0, ',', '.') }} KM</td>
                </tr>
            </table>
        </div>

        {{-- ============ STRIP B: PERIODE ============ --}}
        <table class="strip">
            <tr>
                <th>Lokasi Ambil</th>
                <th>Mulai Sewa</th>
                <th>Pengembalian</th>
                <th class="r">Durasi</th>
            </tr>
            <tr>
                <td>{{ $rental->pickupLocation?->location_name ?? '-' }}</td>
                <td>{{ $rental->rental_start_date?->format('d M Y, H:i') ?? '-' }}</td>
                <td>{{ $rental->rental_end_date?->format('d M Y, H:i') ?? '-' }}</td>
                <td class="r">{{ $rental->rental_days ?? 0 }} Hari</td>
            </tr>
        </table>

        {{-- ============ CARD C: RINCIAN BIAYA ============ --}}
        <div class="card">
            <div class="card-head"><span class="letter">B</span> Rincian Biaya Sewa</div>
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:46%;">Deskripsi</th>
                        <th style="width:18%;" class="r">Harga</th>
                        <th style="width:12%;" class="r">Qty</th>
                        <th style="width:24%;" class="r">Jumlah (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong>Sewa Kendaraan</strong><br>
                            <span class="item-sub">{{ $rental->vehicle?->model?->brand?->brand_name }}
                                {{ $rental->vehicle?->model?->model_name }} &mdash;
                                {{ $rental->vehicle?->license_plate }}</span>
                        </td>
                        <td class="r">Rp {{ number_format($rental->base_rate_per_day ?? 0, 0, ',', '.') }}</td>
                        <td class="r">{{ $rental->rental_days ?? 0 }} hari</td>
                        <td class="r">
                            <strong>{{ number_format($rental->total_base_price ?? 0, 0, ',', '.') }}</strong></td>
                    </tr>
                    @if ($rental->insurance_fee > 0)
                        <tr>
                            <td><strong>Asuransi</strong><br><span class="item-sub">Proteksi kendaraan selama masa
                                    sewa</span></td>
                            <td class="r">&mdash;</td>
                            <td class="r">1x</td>
                            <td class="r">
                                <strong>{{ number_format($rental->insurance_fee, 0, ',', '.') }}</strong></td>
                        </tr>
                    @endif
                    @if ($rental->is_with_driver && $rental->driver_fee > 0)
                        <tr>
                            <td>
                                <strong>Biaya Sopir</strong><br>
                                <span
                                    class="item-sub">{{ $rental->driver?->full_name ?? '-' }}{{ $rental->driver?->license_number ? ' (SIM ' . $rental->driver->license_number . ')' : '' }}</span>
                            </td>
                            <td class="r">Rp
                                {{ number_format($rental->driver_fee / max($rental->rental_days ?? 1, 1), 0, ',', '.') }}
                            </td>
                            <td class="r">{{ $rental->rental_days ?? 0 }} hari</td>
                            <td class="r"><strong>{{ number_format($rental->driver_fee, 0, ',', '.') }}</strong>
                            </td>
                        </tr>
                    @endif
                    @if ($rental->young_driver_fee > 0)
                        <tr>
                            <td><strong>Biaya Young Driver</strong></td>
                            <td class="r">&mdash;</td>
                            <td class="r">1x</td>
                            <td class="r">
                                <strong>{{ number_format($rental->young_driver_fee, 0, ',', '.') }}</strong></td>
                        </tr>
                    @endif
                    @forelse($rental->details ?? [] as $d)
                        <tr>
                            <td><strong>{{ $d->item_name }}</strong><br><span class="item-sub">Layanan tambahan</span>
                            </td>
                            <td class="r">Rp {{ number_format($d->unit_price ?? 0, 0, ',', '.') }}</td>
                            <td class="r">{{ $d->quantity }}x</td>
                            <td class="r"><strong>{{ number_format($d->total_price ?? 0, 0, ',', '.') }}</strong>
                            </td>
                        </tr>
                    @empty
                    @endforelse
                    @if ($rental->discount_amount > 0)
                        <tr>
                            <td><strong>Diskon</strong></td>
                            <td class="r">&mdash;</td>
                            <td class="r">1x</td>
                            <td class="r">
                                <strong>-{{ number_format($rental->discount_amount, 0, ',', '.') }}</strong></td>
                        </tr>
                    @endif
                </tbody>
            </table>
            <div class="total-bar">
                TOTAL BIAYA SEWA
                <span class="amt">Rp {{ number_format($rental->total_amount ?? 0, 0, ',', '.') }}</span>
            </div>
        </div>

        {{-- ============ CARD D: RINGKASAN ============ --}}
        <div class="card">
            <div class="card-head"><span class="letter">C</span> Ringkasan Pembayaran</div>
            <table class="sum">
                <tr>
                    <td class="k">{{ $taxLabel }}
                        ({{ rtrim(rtrim(number_format($taxPercent, 2, ',', '.'), '0'), ',') }}%)</td>
                    <td class="v">Rp {{ number_format($rental->tax_amount ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="k">Status Pembayaran</td>
                    <td class="v">{{ $pay[0] }}</td>
                </tr>
                <tr>
                    <td class="k">Deposit Jaminan</td>
                    <td class="v">
                        {{ $depositAmount > 0 ? 'Rp ' . number_format($depositAmount, 0, ',', '.') : 'Tanpa deposit' }}
                    </td>
                </tr>
            </table>
        </div>

        {{-- ============ NOTE ============ --}}
        @php
            // Kontrol ukuran font dinamis & cegah pemotongan blok (audit 3): klausul panjang -> font lebih kecil
            $termsLength = mb_strlen($settings['contract_terms'] ?? '');
            $addonCount = ($rental->details ?? collect())->count();
            $compact = ($termsLength > 900 || $addonCount > 4);
        @endphp
        <div class="note {{ $compact ? 'note-compact' : '' }}">
            <div class="t">Syarat &amp; Ketentuan</div>
            {!! nl2br(e($settings['contract_terms'] ?? '')) !!}
        </div>

        {{-- ============ QR VERIFIKASI KEASLIAN (butir 3 audit_12092026) ============ --}}
        <table style="width: 100%; margin-top: 3mm; page-break-inside: avoid; break-inside: avoid;">
            <tr>
                <td style="font-size: 7.5px; color: #6b7280; line-height: 1.5;">
                    <strong>Verifikasi Keaslian Dokumen</strong><br>
                    Pindai QR di samping menggunakan aplikasi pemindai untuk memeriksa
                    keaslian kontrak ini pada halaman verifikasi publik {{ $settings['company_name'] }}.
                    URL tanpa tanda verifikasi tidak sah sebagai bukti.
                </td>
                <td style="width: 4mm;"></td>
                <td style="text-align: right;">
                    <img src="{{ \App\Support\QrCode::dataUriSvg(route('verify.contract', ['rental' => $rental->rental_id, 't' => \App\Support\QrCode::contractSignature($rental->rental_id)]), 100) }}"
                        style="width: 18mm; height: 18mm;" alt="QR Verifikasi Kontrak">
                </td>
            </tr>
        </table>

        {{-- ============ TTD ============ --}}
        <table class="ttd" style="page-break-inside: avoid; break-inside: avoid;">
            <tr>
                <td>
                    <div class="line">Pihak Penyewa</div>
                    {{ $rental->customer?->full_name ?? '' }}
                </td>
                <td>
                    <div class="line">&nbsp;</div>
                    {{ $settings['company_name'] }}
                </td>
                <td>
                    <div class="line">Mengetahui</div>
                    {{ $rental->employee?->full_name ?? $settings['company_name'] }}
                </td>
            </tr>
        </table>

        <div class="foot">
            Kontrak No. {{ $rental->rental_code }} &mdash; dicetak otomatis dari sistem
            {{ $settings['company_name'] }} pada {{ now()->format('d F Y H:i') }} &mdash; dokumen sah tanpa tanda
            tangan basah
        </div>
    </div>
</body>

</html>

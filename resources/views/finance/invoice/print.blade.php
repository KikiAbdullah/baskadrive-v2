<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Invoice {{ $item->invoice_number }}</title>
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

        // FIN-14: formatter nominal dua desimal (sen) & tanggal bulan Indonesia
        // selaras dengan helper bertipe modul Laporan; FIN-13: snapshot line item
        // dari InvoiceLineItems agar layar/PDF menjumlah ke subtotal.
        use App\Support\AppSettings as FinSettings;
        use App\Support\InvoiceLineItems;

        $money = fn ($v) => FinSettings::money($v);
        $idDate = fn ($d) => $d ? $d->locale('id')->translatedFormat('d F Y') : '-';
        $idDateTime = fn ($d) => $d ? $d->locale('id')->translatedFormat('d M Y, H:i') : '-';

        // FIN-13: status dokumen (tersimpan) dan status settlement (nominal) dipisah
        // — cancelled/overdue tidak lagi tersembunyi di balik perhitungan nominal.
        $statusLabels = [
            'draft' => ['Draft', 'chip-amber'],
            'sent' => ['Terkirim', 'chip-blue'],
            'partially_paid' => ['Dibayar Sebagian', 'chip-amber'],
            'paid' => ['Lunas', 'chip-green'],
            'overdue' => ['Jatuh Tempo', 'chip-red'],
            'cancelled' => ['Dibatalkan', 'chip-red'],
        ];
        [$statusLabel, $statusChip] = $statusLabels[$item->status] ?? [ucfirst($item->status ?? '-'), 'chip-amber'];

        $taxLabel = $settings['tax_label'] ?? 'PPN';
        $taxPercent = $item->rental?->tax_percent ?? ($settings['tax_enabled'] ?? true ? $settings['tax_percent'] : 0);
        $depositAmount = $item->rental?->deposit_amount ?? 0;
        $remaining = ($item->total_amount ?? 0) - ($item->paid_amount ?? 0);
        $settlementLabel = $remaining <= 0 ? 'Lunas' : ((($item->paid_amount ?? 0) > 0) ? 'Dibayar Sebagian' : 'Belum Dibayar');
        $settlementChip = $remaining <= 0 ? 'chip-green' : ((($item->paid_amount ?? 0) > 0) ? 'chip-amber' : 'chip-red');

        $lineItems = InvoiceLineItems::for($item);
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

        /* ================= TITLE + CHIPS ================= */
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

        .chip-blue { background: #666cff1f; color: #4c52f0; border: 1px solid #c5c9ff; }
        .chip-green { background: #72e12829; color: #3ea016; border: 1px solid #b8ef9c; }
        .chip-amber { background: #fdb52829; color: #b07d0e; border: 1px solid #f5dfa3; }
        .chip-red { background: #ff4d4929; color: #d33a3a; border: 1px solid #f5bcbc; }
        .chip-gray { background: #82868b1f; color: #6a6f85; border: 1px solid #dcdfe8; }

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

        table.kv { width: 100%; border-collapse: collapse; }

        table.kv td {
            padding: 1.15mm 4mm;
            vertical-align: top;
            border-bottom: 1px solid #f2f3f8;
        }

        table.kv tr:last-child td { border-bottom: none; }

        table.kv td.k { width: 22%; color: #a8aab4; font-size: 8.5px; }
        table.kv td.v { font-weight: 700; color: #3b4055; width: 28%; }

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

        table.strip th.r { text-align: right; }

        table.strip td {
            padding: 1.8mm 4mm;
            font-size: 10px;
            font-weight: 700;
            color: #3b4055;
            background: #f8f9ff;
            border-top: 1px solid #e5e5e8;
        }

        table.strip td.r { text-align: right; }

        /* ================= TABEL ITEM ================= */
        .card .items { width: 100%; border-collapse: collapse; }

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

        .card .items th.r, .card .items td.r { text-align: right; }

        .card .items td {
            padding: 1.5mm 4mm;
            vertical-align: top;
            border-bottom: 1px solid #f2f3f8;
            color: #3b4055;
        }

        .card .items tr:last-child td { border-bottom: none; }

        .item-sub { font-size: 8px; color: #a8aab4; }

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

        /* ================= SUMMARY ================= */
        table.sum { width: 100%; border-collapse: collapse; }

        table.sum td {
            padding: 1.3mm 4mm;
            border-bottom: 1px solid #f2f3f8;
            font-size: 9.5px;
        }

        table.sum td.k { color: #a8aab4; }
        table.sum td.v { text-align: right; font-weight: 700; color: #3b4055; }
        table.sum td.v.green { color: #3ea016; }

        /* ================= PAYMENTS ================= */
        table.pays { width: 100%; border-collapse: collapse; }

        table.pays th {
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

        table.pays th.r { text-align: right; }

        table.pays td {
            padding: 1.5mm 4mm;
            border-bottom: 1px solid #f2f3f8;
            font-size: 9px;
            color: #676a7b;
        }

        table.pays td.r { text-align: right; font-weight: 700; color: #3b4055; }

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
                        <div class="no">{{ $item->invoice_number ?? '-' }}</div>
                        <div class="dt">Terbit: {{ $idDate($item->issue_date) }}</div>
                    </td>
                </tr>
            </table>
            <div class="head-contact">
                {{ $settings['company_address'] ?: '-' }}
                @if($settings['company_phone']) &nbsp;&bull;&nbsp; {{ $settings['company_phone'] }} @endif
                @if($settings['company_email']) &nbsp;&bull;&nbsp; {{ $settings['company_email'] }} @endif
            </div>
        </div>

        {{-- ============ TITLE + CHIPS ============ --}}
        <div class="title-band">
            <table class="title-t">
                <tr>
                    <td>
                        <h1 class="doc-title">Invoice</h1>
                        <p class="doc-sub">Tagihan Sewa Kendaraan</p>
                    </td>
                    <td style="text-align: right;">
                        <span class="chip {{ $statusChip }}">{{ $statusLabel }}</span>
                        <span class="chip {{ $settlementChip }}">{{ $settlementLabel }}</span>
                        @if($depositAmount > 0)
                            <span class="chip chip-blue">Deposit {{ $money($depositAmount) }}</span>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        {{-- ============ CARD A: PENERBIT & PELANGGAN ============ --}}
        <div class="card">
            <div class="card-head"><span class="letter">A</span> Ditagihkan Kepada &nbsp;&mdash;&nbsp; Detail Kendaraan</div>
            <table class="kv">
                <tr>
                    <td class="k">Nama</td>
                    <td class="v">{{ $item->rental?->customer?->full_name ?? '-' }}</td>
                    <td class="k">Merk / Model</td>
                    <td class="v">{{ $item->rental?->vehicle?->model?->brand?->brand_name ?? '-' }} {{ $item->rental?->vehicle?->model?->model_name ?? '' }}</td>
                </tr>
                <tr>
                    <td class="k">Tipe</td>
                    <td class="v">{{ $item->rental?->customer?->customer_type == 'corporate' ? 'Perusahaan' : 'Individu' }}</td>
                    <td class="k">No. Polisi</td>
                    <td class="v">{{ $item->rental?->vehicle?->license_plate ?? '-' }} ({{ $item->rental?->vehicle?->year ?? '-' }})</td>
                </tr>
                <tr>
                    <td class="k">Telepon</td>
                    <td class="v">{{ $item->rental?->customer?->phone ?? '-' }}</td>
                    <td class="k">Warna</td>
                    <td class="v">{{ $item->rental?->vehicle?->color ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="k">Email</td>
                    <td class="v">{{ $item->rental?->customer?->email ?? '-' }}</td>
                    <td class="k">Kode Booking</td>
                    <td class="v">{{ $item->rental?->rental_code ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="k">Alamat</td>
                    <td class="v">{{ $item->rental?->customer?->address ? \Illuminate\Support\Str::limit($item->rental->customer->address . ($item->rental->customer->city ? ', ' . $item->rental->customer->city : ''), 38) : '-' }}</td>
                    <td class="k">Sopir</td>
                    <td class="v">{{ $item->rental?->is_with_driver ? ($item->rental->driver?->full_name ?? '-') : 'Lepas Kunci' }}</td>
                </tr>
                <tr>
                    <td class="k">No. KTP</td>
                    <td class="v">{{ $item->rental?->customer?->id_card_number ?? '-' }}</td>
                    <td class="k">Transmisi</td>
                    <td class="v">{{ $item->rental?->vehicle?->model?->transmission ?? '-' }}</td>
                </tr>
            </table>
        </div>

        {{-- ============ STRIP B: PERIODE ============ --}}
        <table class="strip">
            <tr>
                <th>Lokasi Ambil</th>
                <th>Mulai Sewa</th>
                <th>Pengembalian</th>
                <th class="r">Jatuh Tempo</th>
            </tr>
            <tr>
                <td>{{ $item->rental?->pickupLocation?->location_name ?? '-' }}</td>
                <td>{{ $idDateTime($item->rental?->rental_start_date) }}</td>
                <td>{{ $idDateTime($item->rental?->rental_end_date) }}</td>
                <td class="r">{{ $idDate($item->due_date) }}</td>
            </tr>
        </table>

        {{-- ============ CARD C: RINCIAN TAGIHAN ============ --}}
        <div class="card">
            <div class="card-head"><span class="letter">B</span> Rincian Tagihan</div>
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
                    {{-- FIN-13: snapshot line item lengkap dari sumber kebenaran tersimpan,
                         komponen nol tidak dirender, jumlah baris = sub_total --}}
                    @forelse($lineItems as $line)
                        <tr>
                            <td>
                                <strong>{{ $line['name'] }}</strong><br>
                                <span class="item-sub">{{ $line['sub'] }}</span>
                            </td>
                            <td class="r">{{ $money($line['unit']) }}</td>
                            <td class="r">{{ $line['qty'] }}</td>
                            <td class="r"><strong>{{ $money($line['total']) }}</strong></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="r">
                                {{ $money($item->sub_total ?? 0) }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="total-bar">
                TOTAL TAGIHAN
                <span class="amt">{{ $money($item->total_amount ?? 0) }}</span>
            </div>
        </div>

        {{-- ============ CARD D: RINGKASAN PEMBAYARAN ============ --}}
        <div class="card">
            <div class="card-head"><span class="letter">C</span> Ringkasan Pembayaran</div>
            <table class="sum">
                <tr>
                    <td class="k">Subtotal</td>
                    <td class="v">{{ $money($item->sub_total ?? 0) }}</td>
                </tr>
                <tr>
                    <td class="k">Diskon</td>
                    <td class="v">- {{ $money($item->discount ?? 0) }}</td>
                </tr>
                @if(($item->tax ?? 0) > 0)
                    <tr>
                        <td class="k">{{ $taxLabel }} ({{ rtrim(rtrim(number_format($taxPercent, 2, ',', '.'), '0'), ',') }}%)</td>
                        <td class="v">{{ $money($item->tax ?? 0) }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="k">Terbayar</td>
                    <td class="v green">{{ $money($item->paid_amount ?? 0) }}</td>
                </tr>
                <tr>
                    <td class="k">Sisa Tagihan</td>
                    <td class="v">{{ $money($remaining) }}</td>
                </tr>
                <tr>
                    <td class="k">Deposit Jaminan</td>
                    <td class="v">{{ $depositAmount > 0 ? $money($depositAmount) : 'Tanpa deposit' }}</td>
                </tr>
            </table>
        </div>

        {{-- ============ CARD E: RIWAYAT PEMBAYARAN (bila ada) ============ --}}
        @if($item->payments && $item->payments->count())
            <div class="card">
                <div class="card-head"><span class="letter">D</span> Riwayat Pembayaran</div>
                <table class="pays">
                    <thead>
                        <tr>
                            <th style="width:22%;">Tanggal</th>
                            <th style="width:28%;">Metode</th>
                            <th style="width:28%;">Referensi</th>
                            <th style="width:22%;" class="r">Jumlah (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($item->payments->take(5) as $p)
                            <tr>
                                <td>{{ $idDate($p->payment_date) }}</td>
                                <td>{{ ucfirst($p->payment_method ?? '-') }}</td>
                                <td>{{ $p->reference_number ?? '-' }}</td>
                                <td class="r">{{ $money($p->amount ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- ============ NOTE ============ --}}
        <div class="note">
            <div class="t">Catatan Pembayaran</div>
            {{ $item->notes ?: (($depositAmount > 0)
                ? 'Deposit jaminan sebesar ' . $money($depositAmount) . ' dibayarkan terpisah dan dikembalikan setelah kendaraan diterima dalam kondisi baik. Deposit tidak termasuk dalam total tagihan.'
                : 'Sewa ini tanpa deposit jaminan.') }}
            Pembayaran via transfer bank a.n. {{ $settings['company_name'] }} — sertakan nomor invoice {{ $item->invoice_number ?? '-' }} pada berita transfer.
        </div>

        <div class="foot">
            Invoice No. {{ $item->invoice_number ?? '-' }} &mdash; dicetak otomatis dari sistem {{ $settings['company_name'] }} pada {{ now()->format('d F Y H:i') }} &mdash; {{ $settings['invoice_footer_note'] ?: 'Terima kasih telah mempercayakan perjalanan Anda kepada kami.' }}
        </div>
    </div>
</body>

</html>

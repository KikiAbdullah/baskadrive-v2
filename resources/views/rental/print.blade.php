<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kontrak Sewa - {{ $rental->rental_code }}</title>
    <style>
        @page {
            size: A4;
            margin: 2cm;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            font-size: 13px;
            line-height: 1.5;
        }

        .header {
            text-align: center;
            border-bottom: 3px solid #666cff;
            padding-bottom: 12px;
            margin-bottom: 24px;
        }

        .header h2 {
            margin: 0;
            color: #666cff;
            letter-spacing: 1px;
        }

        .header p {
            margin: 4px 0 0;
            color: #697a8d;
        }

        .section-title {
            font-size: 14px;
            font-weight: 700;
            border-left: 4px solid #666cff;
            padding-left: 8px;
            margin: 20px 0 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table.info td {
            padding: 4px 0;
            vertical-align: top;
        }

        table.info td.label {
            width: 38%;
            color: #697a8d;
        }

        table.info td.value {
            width: 62%;
            font-weight: 600;
        }

        table.bordered {
            border: 1px solid #d9dee3;
            margin-top: 6px;
        }

        table.bordered th,
        table.bordered td {
            border: 1px solid #d9dee3;
            padding: 6px 8px;
            text-align: left;
        }

        table.bordered th {
            background: #f6f7fb;
        }

        .text-end {
            text-align: right;
        }

        .total-box {
            margin-top: 12px;
            text-align: right;
            font-size: 16px;
            font-weight: 700;
            color: #666cff;
        }

        .signatures {
            margin-top: 48px;
            display: flex;
            justify-content: space-between;
        }

        .signature-box {
            width: 45%;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #333;
            margin-top: 48px;
            padding-top: 6px;
        }

        .footer {
            margin-top: 32px;
            text-align: center;
            color: #999;
            font-size: 11px;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="no-print" style="text-align:right; margin-bottom: 12px;">
        <button onclick="window.print()"
            style="padding:8px 16px; background:#666cff; color:#fff; border:none; border-radius:4px; cursor:pointer;">Cetak / PDF</button>
    </div>

    <div class="header">
        <h2>BASKA DRIVE</h2>
        <p>KONTRAK PERSEWAAN KENDARAAN</p>
        <p>No. Kontrak: {{ $rental->rental_code }}</p>
    </div>

    <div class="section-title">Data Pelanggan</div>
    <table class="info">
        <tr>
            <td class="label">Nama</td>
            <td class="value">{{ $rental->customer?->full_name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Telepon</td>
            <td class="value">{{ $rental->customer?->phone ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Email</td>
            <td class="value">{{ $rental->customer?->email ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Tipe</td>
            <td class="value">{{ $rental->customer?->customer_type == 'corporate' ? 'Perusahaan' : 'Individu' }}</td>
        </tr>
    </table>

    <div class="section-title">Data Kendaraan</div>
    <table class="info">
        <tr>
            <td class="label">Plat Nomor</td>
            <td class="value">{{ $rental->vehicle?->license_plate ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Merk / Model</td>
            <td class="value">
                {{ $rental->vehicle?->model?->brand?->brand_name ?? '' }}
                {{ $rental->vehicle?->model?->model_name ?? '' }}
                ({{ $rental->vehicle?->year ?? '' }})
            </td>
        </tr>
        <tr>
            <td class="label">Warna</td>
            <td class="value">{{ $rental->vehicle?->color ?? '-' }}</td>
        </tr>
        @if($rental->is_with_driver)
            <tr>
                <td class="label">Sopir</td>
                <td class="value">{{ $rental->driver?->full_name ?? '-' }}</td>
            </tr>
        @endif
    </table>

    <div class="section-title">Periode Sewa</div>
    <table class="info">
        <tr>
            <td class="label">Mulai</td>
            <td class="value">{{ $rental->rental_start_date?->format('d F Y H:i') ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Selesai</td>
            <td class="value">{{ $rental->rental_end_date?->format('d F Y H:i') ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Durasi</td>
            <td class="value">{{ $rental->rental_days ?? 0 }} hari</td>
        </tr>
        <tr>
            <td class="label">Penjemputan</td>
            <td class="value">{{ $rental->pickupLocation?->location_name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Pengembalian</td>
            <td class="value">{{ $rental->returnLocation?->location_name ?? '-' }}</td>
        </tr>
    </table>

    <div class="section-title">Rincian Biaya</div>
    <table class="bordered">
        <thead>
            <tr>
                <th>Keterangan</th>
                <th class="text-end">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Tarif Dasar ({{ number_format($rental->base_rate_per_day ?? 0, 0, ',', '.') }} x {{ $rental->rental_days ?? 0 }} hari)</td>
                <td class="text-end">{{ number_format($rental->total_base_price ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Asuransi</td>
                <td class="text-end">{{ number_format($rental->insurance_fee ?? 0, 0, ',', '.') }}</td>
            </tr>
            @if($rental->driver_fee)
                <tr>
                    <td>Biaya Sopir</td>
                    <td class="text-end">{{ number_format($rental->driver_fee, 0, ',', '.') }}</td>
                </tr>
            @endif
            @if($rental->discount_amount)
                <tr>
                    <td>Diskon</td>
                    <td class="text-end">-{{ number_format($rental->discount_amount, 0, ',', '.') }}</td>
                </tr>
            @endif
            <tr>
                <td>Pajak (11%)</td>
                <td class="text-end">{{ number_format($rental->tax_amount ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Deposit</td>
                <td class="text-end">{{ number_format($rental->deposit_amount ?? 0, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>
    <div class="total-box">
        TOTAL: Rp {{ number_format($rental->total_amount ?? 0, 0, ',', '.') }}
    </div>

    @if($rental->notes)
        <div class="section-title">Catatan</div>
        <p>{{ $rental->notes }}</p>
    @endif

    <div class="signatures">
        <div class="signature-box">
            <div class="signature-line">Pihak Penyewa</div>
        </div>
        <div class="signature-box">
            <div class="signature-line">Pihak Baska Drive</div>
        </div>
    </div>

    <div class="footer">
        Dokumen ini dicetak secara otomatis dari sistem Baska Drive pada {{ now()->format('d F Y H:i') }}
    </div>
</body>

</html>
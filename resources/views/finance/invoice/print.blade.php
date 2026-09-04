<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $item->invoice_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            color: #1f2937;
            margin: 0;
            padding: 24px;
            font-size: 13px;
        }

        .doc {
            max-width: 800px;
            margin: 0 auto;
        }

        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .company-name {
            font-size: 20px;
            font-weight: 700;
            color: #2563eb;
        }

        .title {
            font-size: 22px;
            font-weight: 700;
        }

        .info-grid {
            display: flex;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .info-grid div {
            width: 48%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            text-align: left;
        }

        th {
            background: #f3f4f6;
        }

        .text-end {
            text-align: right;
        }

        .totals {
            margin-top: 12px;
            margin-left: auto;
            width: 320px;
        }

        .footer {
            margin-top: 32px;
            text-align: center;
            color: #6b7280;
            font-size: 11px;
        }

        @media print {
            body {
                padding: 0;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="doc">
        <div class="doc-header">
            <div>
                <div class="company-name">BaskaDrive</div>
                <div>Rental Mobil &amp; Sewa Kendaraan</div>
                <div>invoice@baskadrive.com</div>
            </div>
            <div class="text-end">
                <div class="title">INVOICE</div>
                <div>No: {{ $item->invoice_number }}</div>
                <div>Tanggal: {{ $item->issue_date?->format('d F Y') }}</div>
                <div>Jatuh Tempo: {{ $item->due_date?->format('d F Y') }}</div>
            </div>
        </div>

        <div class="info-grid">
            <div>
                <strong>Kepada:</strong><br>
                {{ $item->rental?->customer?->full_name ?? '-' }}<br>
                {{ $item->rental?->customer?->phone ?? '' }}<br>
                {{ $item->rental?->customer?->email ?? '' }}
            </div>
            <div class="text-end">
                <strong>Kendaraan:</strong><br>
                {{ $item->rental?->vehicle?->license_plate ?? '-' }}
                ({{ $item->rental?->vehicle?->model?->brand?->brand_name ?? '' }} {{ $item->rental?->vehicle?->model?->model_name ?? '' }})<br>
                Kode Sewa: {{ $item->rental?->rental_code ?? '-' }}
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Harga</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($item->rental?->details ?? [] as $d)
                    <tr>
                        <td>{{ $d->item_name }}</td>
                        <td class="text-end">{{ $d->quantity }}</td>
                        <td class="text-end">Rp {{ number_format($d->unit_price ?? 0, 0, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format($d->total_price ?? 0, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center">Tidak ada rincian item.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <table class="totals">
            <tr>
                <td>Subtotal</td>
                <td class="text-end">Rp {{ number_format($item->sub_total ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Pajak</td>
                <td class="text-end">Rp {{ number_format($item->tax ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Diskon</td>
                <td class="text-end">(Rp {{ number_format($item->discount ?? 0, 0, ',', '.') }})</td>
            </tr>
            <tr>
                <td><strong>Total</strong></td>
                <td class="text-end"><strong>Rp {{ number_format($item->total_amount ?? 0, 0, ',', '.') }}</strong></td>
            </tr>
            <tr>
                <td>Terbayar</td>
                <td class="text-end">Rp {{ number_format($item->paid_amount ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td><strong>Sisa</strong></td>
                <td class="text-end"><strong>Rp {{ number_format(($item->total_amount ?? 0) - ($item->paid_amount ?? 0), 0, ',', '.') }}</strong></td>
            </tr>
        </table>

        <div class="footer">
            Terima kasih atas kepercayaan Anda kepada BaskaDrive.
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>

</html>

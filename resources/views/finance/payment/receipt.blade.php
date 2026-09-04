<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Kwitansi {{ $item->payment_id }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            color: #1f2937;
            margin: 0;
            padding: 24px;
            font-size: 13px;
        }

        .doc {
            max-width: 480px;
            margin: 0 auto;
            border: 1px solid #d1d5db;
            padding: 20px;
        }

        .title {
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px dashed #e5e7eb;
        }

        .label {
            color: #6b7280;
        }

        .amount {
            margin-top: 16px;
            text-align: center;
            font-size: 20px;
            font-weight: 700;
            color: #2563eb;
        }

        .footer {
            margin-top: 24px;
            text-align: center;
            color: #6b7280;
            font-size: 11px;
        }

        @media print {
            body {
                padding: 0;
            }
        }
    </style>
</head>

<body>
    <div class="doc">
        <div class="title">KWITANSI PEMBAYARAN</div>
        <div class="row"><span class="label">No. Kwitansi</span><span>PAY-{{ str_pad($item->payment_id, 5, '0', STR_PAD_LEFT) }}</span></div>
        <div class="row"><span class="label">No. Invoice</span><span>{{ $item->invoice?->invoice_number ?? '-' }}</span></div>
        <div class="row"><span class="label">Kode Sewa</span><span>{{ $item->rental?->rental_code ?? '-' }}</span></div>
        <div class="row"><span class="label">Pelanggan</span><span>{{ $item->rental?->customer?->full_name ?? '-' }}</span></div>
        <div class="row"><span class="label">Tanggal</span><span>{{ $item->payment_date?->format('d F Y') }}</span></div>
        <div class="row"><span class="label">Metode</span><span>{{ ucfirst($item->payment_method ?? '-') }}</span></div>
        <div class="row"><span class="label">No. Referensi</span><span>{{ $item->reference_number ?? '-' }}</span></div>

        <div class="amount">Rp {{ number_format($item->amount ?? 0, 0, ',', '.') }}</div>

        <div class="footer">
            Dicetak oleh BaskaDrive &mdash; {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>

</html>

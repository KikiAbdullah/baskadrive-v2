<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Label - {{ $item->license_plate }}</title>
    <style>
        @page {
            size: 50mm 30mm;
            margin: 2mm;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Helvetica, Arial, sans-serif;
            width: 46mm;
            height: 26mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .header {
            text-align: center;
            border-bottom: 1px solid #000;
            padding-bottom: 1mm;
            margin-bottom: 1mm;
        }
        .company {
            font-size: 5pt;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .plate {
            font-size: 8pt;
            font-weight: 900;
            letter-spacing: 1px;
            margin-top: 0.5mm;
        }
        .barcode-wrap {
            text-align: center;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .barcode-wrap img {
            max-width: 42mm;
            height: 9mm;
            object-fit: contain;
        }
        .footer {
            display: flex;
            justify-content: space-between;
            font-size: 5pt;
            border-top: 1px solid #000;
            padding-top: 1mm;
            color: #333;
        }
        .model {
            font-weight: 700;
        }
        .location {
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">{{ $settings['company_name'] ?? 'BaskaDrive' }}</div>
        <div class="plate">{{ $item->license_plate }}</div>
    </div>
    <div class="barcode-wrap">
        <img src="data:image/png;base64,{{ $barcode }}" alt="Barcode {{ $item->license_plate }}">
    </div>
    <div class="footer">
        <span class="model">{{ $item->model->model_name ?? '' }} ({{ $item->model->brand->brand_name ?? '' }})</span>
        <span class="location">{{ $item->location->location_name ?? 'Tanpa Lokasi' }}</span>
    </div>
</body>
</html>

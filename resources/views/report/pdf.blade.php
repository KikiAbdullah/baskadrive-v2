<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $reportTitle }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #1e1f24; padding: 24px; }
        .doc-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #666cff; padding-bottom: 10px; margin-bottom: 14px; }
        .doc-header h2 { font-size: 15px; color: #666cff; }
        .doc-header .company { text-align: right; font-size: 10px; color: #5e5e5e; }
        .meta { font-size: 10px; color: #5e5e5e; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d3d3d6; padding: 5px 7px; text-align: left; }
        th { background: #f0f0f2; font-size: 10px; text-transform: uppercase; }
        tbody tr:nth-child(even) { background: #fafafa; }
        .footer { margin-top: 18px; font-size: 9px; color: #a8a8a8; text-align: center; }
    </style>
</head>
<body>
    <div class="doc-header">
        <div>
            <h2>{{ $settings['company_name'] ?? 'BaskaDrive' }}</h2>
            <div>{{ $reportTitle }}</div>
        </div>
        <div class="company">
            {{ $settings['company_address'] ?? '' }}<br>
            {{ $settings['company_phone'] ?? '' }} {{ $settings['company_email'] ?? '' }}
        </div>
    </div>

    <div class="meta">Periode: {{ $start }} s.d. {{ $end }} &mdash; Dicetak: {{ now()->format('d/m/Y H:i') }}</div>

    <table>
        @foreach($rows as $i => $row)
            <tr>
                @if($i === 0)
                    @foreach($row as $cell)<th>{{ $cell }}</th>@endforeach
                @else
                    @foreach($row as $column => $cell)<td>{{ \App\Support\ReportFormat::cell($cell, $columnTypes[$column] ?? 'text') }}</td>@endforeach
                @endif
            </tr>
        @endforeach
    </table>

    @if(!empty($note))
        <div class="meta">{{ $note }}</div>
    @endif

    <div class="footer">Dokumen dihasilkan otomatis oleh {{ $settings['company_name'] ?? 'BaskaDrive' }} &mdash; {{ $settings['company_tagline'] ?? '' }}</div>
</body>
</html>

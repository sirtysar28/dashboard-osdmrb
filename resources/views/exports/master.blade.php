<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Master Data</title>
    <style>
        body {
            font-family: "Segoe UI", DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1f2937;
            margin: 0;
            padding: 26px 30px;
        }
        .doc-head {
            border-bottom: 3px solid #163d4f;
            padding-bottom: 10px;
            margin-bottom: 8px;
            display: table;
            width: 100%;
        }
        .doc-head .logo {
            display: table-cell;
            width: 50px;
            vertical-align: middle;
            padding-right: 8px;
        }
        .doc-head h1 { margin: 0; font-size: 15px; color: #143647; }
        .doc-head p { margin: 2px 0 0; font-size: 10px; color: #6b7280; }
        .doc-title {
            margin: 14px 0 6px;
            font-size: 12.5px;
            font-weight: bold;
            color: #143647;
            border-left: 4px solid #163d4f;
            padding-left: 8px;
        }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th {
            background: #163d4f; color: #fff;
            font-size: 9px; text-align: left;
            padding: 6px 7px; border: 1px solid #163d4f;
        }
        td {
            font-size: 9px; padding: 5px 7px;
            border: 1px solid #d4dfe5; vertical-align: top;
        }
        tr:nth-child(even) td { background: #f6f8fd; }
        .doc-foot {
            margin-top: 14px; padding-top: 8px;
            border-top: 1px solid #d4dfe5;
            font-size: 8.5px; color: #9ca3af;
            text-align: right;
        }
    </style>
</head>
<body>

<div class="doc-head">
    <div class="logo">
        <img src="{{ public_path('images/logo-kementerian.png') }}" alt="Logo" style="width: 42px;">
    </div>
    <div style="display: table-cell; vertical-align: middle;">
        <h1>{{ config('app.name', 'Dashboard Biro OSDMRB') }} &mdash; Master Data</h1>
        <p>Organisasi, Sumber Daya Manusia &amp; Reformasi Birokrasi</p>
    </div>
</div>

@foreach ($sections as $section)
    <div class="doc-title">{{ $section['title'] }}</div>

    <table>
        <thead>
            <tr>
                <th style="width:26px; text-align:center">#</th>
                @foreach ($section['columns'] as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @php
                $sectionRows = collect($section['rows'])->map(
                    fn ($row) => array_map(fn ($v) => $v === null || $v === '' ? '-' : $v, (array) $row)
                );
            @endphp
            @forelse ($sectionRows as $row)
                <tr>
                    <td style="text-align:center">{{ $loop->iteration }}</td>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($section['columns']) + 1 }}" style="text-align:center; color:#9ca3af">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
@endforeach

<div class="doc-foot">
    Dicetak {{ now()->translatedFormat('d F Y H:i') }} WIB oleh {{ auth()->user()->name ?? '-' }}
</div>

</body>
</html>

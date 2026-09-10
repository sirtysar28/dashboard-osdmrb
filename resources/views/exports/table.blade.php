<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: "Segoe UI", DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1f2937;
            margin: 0;
            padding: 28px 30px;
        }
        .doc-head {
            border-bottom: 3px solid #163d4f;
            padding-bottom: 10px;
            margin-bottom: 14px;
            display: table;
            width: 100%;
        }
        .doc-head .logo {
            display: table-cell;
            width: 54px;
            vertical-align: middle;
        }
        .doc-head .logo img { width: 46px; }
        .doc-head .meta { display: table-cell; vertical-align: middle; }
        .doc-head h1 { margin: 0; font-size: 15px; color: #143647; }
        .doc-head p { margin: 2px 0 0; font-size: 10px; color: #6b7280; }
        .doc-title { margin: 12px 0 4px; font-size: 13px; font-weight: bold; color: #143647; }
        .doc-sub { font-size: 9.5px; color: #6b7280; margin: 0 0 12px; }
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #163d4f;
            color: #fff;
            font-size: 9px;
            text-align: left;
            padding: 6px 7px;
            border: 1px solid #163d4f;
        }
        td {
            font-size: 9px;
            padding: 5px 7px;
            border: 1px solid #d4dfe5;
            vertical-align: top;
        }
        tr:nth-child(even) td { background: #f6f8fd; }
        .doc-foot {
            margin-top: 16px;
            padding-top: 8px;
            border-top: 1px solid #d4dfe5;
            font-size: 8.5px;
            color: #9ca3af;
            display: table;
            width: 100%;
        }
        .doc-foot span { display: table-cell; }
        .doc-foot span:last-child { text-align: right; }
    </style>
</head>
<body>

<div class="doc-head">
    <div class="logo">
        <img src="{{ public_path('images/logo-kementerian.png') }}" alt="Logo">
    </div>
    <div class="meta">
        <h1>{{ config('app.name', 'Dashboard Biro OSDMRB') }}</h1>
        <p>Organisasi, Sumber Daya Manusia &amp; Reformasi Birokrasi</p>
    </div>
</div>

<div class="doc-title">{{ $title }}</div>
<p class="doc-sub">
    {{ $subtitle ?? '' }}
    @if (isset($filterInfo) && $filterInfo) &mdash; Filter: {{ $filterInfo }} @endif
</p>

<table>
    <thead>
        <tr>
            <th style="width:26px; text-align:center">#</th>
            @foreach ($columns as $column)
                <th>{{ $column }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td style="text-align:center">{{ $loop->iteration }}</td>
                @foreach ($row as $cell)
                    <td>{{ $cell }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($columns) + 1 }}" style="text-align:center; color:#9ca3af">Tidak ada data.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="doc-foot">
    <span>Dicetak melalui {{ config('app.name', 'Dashboard Biro OSDMRB') }}</span>
    <span>{{ now()->translatedFormat('d F Y H:i') }} WIB &mdash; {{ auth()->user()->name ?? '-' }}</span>
</div>

</body>
</html>

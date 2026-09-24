<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $letter->number ?: 'Surat' }}</title>
    <style>
        @page { margin: 2.2cm 2.2cm; }
        body { font-family: 'Times New Roman', serif; font-size: 12pt; color: #000; line-height: 1.6; }
        .kop { border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 6px; }
        .kop table { width: 100%; border-collapse: collapse; }
        .kop h2 { margin: 0; font-size: 13pt; letter-spacing: .5px; }
        .kop .unit { margin: 2px 0; font-size: 11pt; font-weight: bold; }
        .kop p { margin: 2px 0; font-size: 10.5pt; }
        .judul { text-align: center; margin: 26px 0 4px; }
        .judul h3 { margin: 0; font-size: 13.5pt; text-decoration: underline; letter-spacing: 1px; }
        .judul p { margin: 0; font-size: 12pt; }
        .nomor { text-align: center; margin-bottom: 22px; font-size: 12pt; }
        table.data { margin: 0 0 0 24px; }
        table.data td { padding: 1.5px 6px 1.5px 0; vertical-align: top; font-size: 12pt; }
        .isi { text-align: justify; margin-bottom: 18px; }
        .ttd { width: 100%; margin-top: 26px; }
        .ttd td { vertical-align: top; font-size: 12pt; }
        .tebal { font-weight: bold; }
        .miring { font-style: italic; }
    </style>
</head>
<body>

    {{-- ================== KOP SURAT ================== --}}
    <div class="kop">
        <table>
            <tr>
                <td style="width:96px; vertical-align:middle;">
                    <img src="{{ public_path('images/logo-kementerian.png') }}" style="width:86px;" alt="Logo">
                </td>
                <td style="vertical-align:middle; text-align:center;">
                    <h2>KEMENTERIAN TRANSMIGRASI REPUBLIK INDONESIA</h2>
                    <p class="unit">BIRO ORGANISASI, SUMBER DAYA MANUSIA DAN REFORMASI BIROKRASI</p>
                    <p>Jalan Ir. H. Juanda No. 00, Jakarta 10110 &bull; Telepon (021) 000000 &bull; Surel biro.osdmrb@kemen.go.id</p>
                </td>
                <td style="width:96px;"></td>{{-- sel pengimbang agar teks benar-benar di tengah --}}
            </tr>
        </table>
    </div>

    {{-- ================== JUDUL ================== --}}
    <div class="judul">
        <h3>{{ strtoupper($letter->letterType->name) }}</h3>
        <p>Nomor: {{ $letter->number ?? '-' }}</p>
    </div>

    {{-- ================== ISI ================== --}}
    <p class="isi">
        Yang bertanda tangan di bawah ini menerangkan bahwa:
    </p>

    <table class="data">
        <tr>
            <td>Nama</td><td>:</td>
            <td class="tebal">{{ $employee->name }}</td>
        </tr>
        <tr>
            <td>NIP</td><td>:</td>
            <td>{{ $employee->nip }}</td>
        </tr>
        <tr>
            <td>Pangkat / Gol. Ruang</td><td>:</td>
            <td>{{ $employee->rank?->name ?? '-' }} {{ $employee->rank ? '/ ' . $employee->rank->code : '' }}</td>
        </tr>
        <tr>
            <td>Jabatan</td><td>:</td>
            <td>{{ $employee->position_name ?? '-' }}</td>
        </tr>
        <tr>
            <td>Unit Kerja</td><td>:</td>
            <td>{{ $employee->unit?->name ?? '-' }}</td>
        </tr>
    </table>

    <p class="isi" style="margin-top:16px">
        Berdasarkan keperluan: <strong>{{ $letter->purpose }}</strong>
        @if ($letter->meta['place'] ?? null)
            , bertempat di <strong>{{ $letter->meta['place'] }}</strong>
        @endif
        @if ($letter->meta['start_date'] ?? null)
            , pada tanggal
            <strong>{{ \Illuminate\Support\Carbon::parse($letter->meta['start_date'])->translatedFormat('d F Y') }}</strong>
            @if ($letter->meta['end_date'] ?? null)
                s.d. <strong>{{ \Illuminate\Support\Carbon::parse($letter->meta['end_date'])->translatedFormat('d F Y') }}</strong>
            @endif
        @endif
        .
    </p>

    <p class="isi">
        Demikian surat ini dibuat untuk dapat dipergunakan sebagaimana mestinya.
    </p>

    {{-- ================== TANDA TANGAN ================== --}}
    <table class="ttd">
        <tr>
            <td style="width:55%"></td>
            <td>
                Jakarta, {{ $letter->letter_date?->translatedFormat('d F Y') ?? now()->translatedFormat('d F Y') }}<br>
                Kepala Biro Organisasi, Sumber Daya Manusia<br>dan Reformasi Birokrasi,
                <div style="height:70px"></div>
                <span class="tebal"><u>{{ config('app.ttd_nama', 'TUNGGAK SANTOSA, S.H., M.H.') }}</u></span><br>
                Pangkat Pembina Tk. I / IV.b<br>
                NIP {{ config('app.ttd_nip', '197001012005011001') }}
            </td>
        </tr>
    </table>

</body>
</html>

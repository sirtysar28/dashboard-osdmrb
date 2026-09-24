<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Formulir Permohonan Cuti — {{ $leave->employee?->name }}</title>
    <style>
        @page { margin: 1.8cm 1.8cm; }
        body { font-family: 'Times New Roman', serif; font-size: 11.5pt; color: #000; line-height: 1.5; }
        .kop { border-bottom: 3px double #000; padding-bottom: 8px; margin-bottom: 4px; }
        .kop table { width: 100%; border-collapse: collapse; }
        .kop h2 { margin: 0; font-size: 13pt; letter-spacing: .5px; }
        .kop .unit { margin: 2px 0; font-size: 11pt; font-weight: bold; }
        .kop p { margin: 2px 0; font-size: 10pt; }
        .judul { text-align: center; margin: 18px 0 2px; }
        .judul h3 { margin: 0; font-size: 13pt; text-decoration: underline; letter-spacing: 1px; }
        .judul p { margin: 0; font-size: 11pt; }
        h4.bagian { font-size: 11.5pt; margin: 12px 0 4px; }
        table.data { margin-left: 20px; }
        table.data td { padding: 1.5px 6px 1.5px 0; vertical-align: top; font-size: 11.5pt; }
        table.opt td { padding: 1.5px 0; vertical-align: top; font-size: 11.5pt; }
        .kotak { display: inline-block; width: 13px; height: 13px; border: 1.2px solid #000; margin-right: 6px; text-align: center; line-height: 12px; font-size: 10pt; font-weight: bold; }
        .tebal { font-weight: bold; }
        .catatan { font-size: 9.5pt; font-style: italic; }
        table.ttd { width: 100%; margin-top: 22px; }
        table.ttd td { vertical-align: top; font-size: 11.5pt; }
        .garis { border-bottom: 0.8pt solid #000; display: inline-block; min-width: 180px; }
    </style>
</head>
<body>

    {{-- ================== KOP ================== --}}
    <div class="kop">
        <table>
            <tr>
                <td style="width:90px; vertical-align:middle;">
                    <img src="{{ public_path('images/logo-kementerian.png') }}" style="width:80px;" alt="Logo">
                </td>
                <td style="vertical-align:middle; text-align:center;">
                    <h2>KEMENTERIAN TRANSMIGRASI REPUBLIK INDONESIA</h2>
                    <p class="unit">BIRO ORGANISASI, SUMBER DAYA MANUSIA DAN REFORMASI BIROKRASI</p>
                    <p>Jalan Ir. H. Juanda No. 00, Jakarta 10110 &bull; Telepon (021) 000000 &bull; Surel biro.osdmrb@kemen.go.id</p>
                </td>
                <td style="width:90px;"></td>
            </tr>
        </table>
    </div>

    <div class="judul">
        <h3>FORMULIR PERMOHONAN CUTI</h3>
        <p>ASN dan PPPK</p>
    </div>

    {{-- ================== BAGIAN I ================== --}}
    <h4 class="bagian tebal">I. DATA PEGAWAI</h4>
    <table class="data">
        <tr><td style="width:190px">Nama</td><td>: <span class="tebal">{{ $leave->employee?->name }}</span></td></tr>
        <tr><td>NIP</td><td>: {{ $leave->employee?->nip }}</td></tr>
        <tr><td>Jabatan</td><td>: {{ $leave->employee?->position_name ?? '-' }}</td></tr>
        <tr><td>Masa Kerja</td><td>: {{ $leave->employee?->masa_kerja ?? '-' }}</td></tr>
        <tr><td>Unit Kerja (Eselon II)</td><td>: {{ $leave->employee?->unit?->name ?? '-' }}</td></tr>
    </table>

    {{-- ================== BAGIAN II ================== --}}
    <h4 class="bagian tebal">II. JENIS CUTI YANG DIAMBIL</h4>
    <table class="opt">
        @foreach (\App\Models\LeaveRequest::typeOptions() as $value => $label)
            <tr>
                <td style="width:24px; text-align:center">
                    <span class="kotak">{{ $leave->type === $value ? 'V' : '' }}</span>
                </td>
                <td class="{{ $leave->type === $value ? 'tebal' : '' }}">{{ $label }}</td>
            </tr>
        @endforeach
    </table>

    {{-- ================== BAGIAN III ================== --}}
    <h4 class="bagian tebal">III. ALASAN CUTI</h4>
    <p style="margin-left:20px;">{{ $leave->reason }}</p>

    {{-- ================== BAGIAN IV ================== --}}
    <h4 class="bagian tebal">IV. LAMANYA CUTI</h4>
    <table class="data">
        <tr><td style="width:190px">Selama</td><td>: <span class="tebal">{{ $leave->total_days }} hari</span></td></tr>
        <tr><td>Mulai Tanggal</td><td>: {{ $leave->start_date?->translatedFormat('d F Y') ?? '-' }}</td></tr>
        <tr><td>s/d</td><td>: {{ $leave->end_date?->translatedFormat('d F Y') ?? '-' }}</td></tr>
    </table>

    {{-- ================== BAGIAN V ================== --}}
    <h4 class="bagian tebal">V. CATATAN CUTI</h4>
    <p class="catatan" style="margin-left:20px;">Diisi oleh pejabat yang menangani bidang kepegawaian (nominal otomatis dari sistem):</p>
    @php([$y2, $y1, $y0] = $leave->balance_years)
    <table style="margin-left:20px; border-collapse: collapse; width: 92%; font-size: 10pt;">
        <tr>
            <th rowspan="2" style="border: 0.8pt solid #000; padding: 3px 6px;">Jenis Cuti</th>
            <th colspan="3" style="border: 0.8pt solid #000; padding: 3px 6px;">Cuti Tahunan</th>
            <th rowspan="2" style="border: 0.8pt solid #000; padding: 3px 6px;">Keterangan</th>
        </tr>
        <tr>
            <th style="border: 0.8pt solid #000; padding: 3px 6px;">N&ndash;2 ({{ $y2 }})</th>
            <th style="border: 0.8pt solid #000; padding: 3px 6px;">N&ndash;1 ({{ $y1 }})</th>
            <th style="border: 0.8pt solid #000; padding: 3px 6px;">N ({{ $y0 }})</th>
        </tr>
        <tr>
            <td style="border: 0.8pt solid #000; padding: 3px 6px;">Cuti Tahunan (sisa hari)</td>
            <td class="tebal" style="border: 0.8pt solid #000; padding: 3px 6px; text-align: center;">{{ $leave->annual_n2 ?? '-' }}</td>
            <td class="tebal" style="border: 0.8pt solid #000; padding: 3px 6px; text-align: center;">{{ $leave->annual_n1 ?? '-' }}</td>
            <td class="tebal" style="border: 0.8pt solid #000; padding: 3px 6px; text-align: center;">{{ $leave->annual_n ?? '-' }}</td>
            <td rowspan="6" style="border: 0.8pt solid #000; padding: 3px 6px;">{{ $leave->leave_note ?: $leave->note ?: '-' }}</td>
        </tr>
        <tr><td style="border: 0.8pt solid #000; padding: 3px 6px;">Cuti Besar</td><td colspan="3" style="border: 0.8pt solid #000; padding: 3px 6px;">&nbsp;</td></tr>
        <tr><td style="border: 0.8pt solid #000; padding: 3px 6px;">Cuti Sakit</td><td colspan="3" style="border: 0.8pt solid #000; padding: 3px 6px;">&nbsp;</td></tr>
        <tr><td style="border: 0.8pt solid #000; padding: 3px 6px;">Cuti Melahirkan</td><td colspan="3" style="border: 0.8pt solid #000; padding: 3px 6px;">&nbsp;</td></tr>
        <tr><td style="border: 0.8pt solid #000; padding: 3px 6px;">Cuti Karena Alasan Penting</td><td colspan="3" style="border: 0.8pt solid #000; padding: 3px 6px;">&nbsp;</td></tr>
        <tr><td style="border: 0.8pt solid #000; padding: 3px 6px;">Cuti di Luar Tanggungan Negara</td><td colspan="3" style="border: 0.8pt solid #000; padding: 3px 6px;">&nbsp;</td></tr>
    </table>

    {{-- ================== BAGIAN VI ================== --}}
    <h4 class="bagian tebal">VI. ALAMAT SELAMA MENJALANKAN CUTI</h4>
    <table class="data">
        <tr><td style="width:190px">Alamat</td><td>: {{ $leave->address_during_leave ?? '-' }}</td></tr>
        <tr><td>Telepon</td><td>: {{ $leave->phone_during_leave ?? '-' }}</td></tr>
    </table>

    {{-- ================== TANDA TANGAN PEGAWAI ================== --}}
    <table class="ttd">
        <tr>
            <td style="width:55%"></td>
            <td>
                Hormat saya,<br>
                <div style="height:46px;"></div>
                <span class="tebal"><u>{{ $leave->employee?->name }}</u></span><br>
                NIP. {{ $leave->employee?->nip }}
            </td>
        </tr>
    </table>

    {{-- ================== BAGIAN VII ================== --}}
    <h4 class="bagian tebal">VII. PERTIMBANGAN ATASAN LANGSUNG</h4>
    @php($verifiedDecision = in_array($leave->status, [\App\Models\LeaveRequest::STATUS_VERIFIED, \App\Models\LeaveRequest::STATUS_APPROVED]) ? 'DISETUJUI' : ($leave->status === \App\Models\LeaveRequest::STATUS_REJECTED ? 'TIDAK DISETUJUI' : null))
    <table class="opt">
        @foreach (['DISETUJUI' => 'Disetujui', 'PERUBAHAN' => 'Perubahan', 'DITANGGUHKAN' => 'Ditangguhkan', 'TIDAK DISETUJUI' => 'Tidak Disetujui'] as $key => $label)
            <tr>
                <td style="width:24px; text-align:center"><span class="kotak">{{ $verifiedDecision === $key ? 'V' : '' }}</span></td>
                <td class="{{ $verifiedDecision === $key ? 'tebal' : '' }}">{{ $label }}</td>
            </tr>
        @endforeach
    </table>
    <table class="ttd">
        <tr>
            <td style="width:55%"></td>
            <td>
                {{ now()->translatedFormat('d F Y') }}<br>
                Kasubbag/Kepala Bagian/Direktur<br>
                <div style="height:46px;"></div>
                <span class="tebal"><u>{{ $leave->verifier?->name ?? '.................................' }}</u></span><br>
                NIP. ...............................
            </td>
        </tr>
    </table>

    {{-- ================== BAGIAN VIII ================== --}}
    <h4 class="bagian tebal">VIII. KEPUTUSAN PEJABAT YANG BERWENANG</h4>
    @php($approvedDecision = $leave->status === \App\Models\LeaveRequest::STATUS_APPROVED ? 'DISETUJUI' : ($leave->status === \App\Models\LeaveRequest::STATUS_REJECTED ? 'TIDAK DISETUJUI' : null))
    <table class="opt">
        @foreach (['DISETUJUI' => 'Disetujui', 'PERUBAHAN' => 'Perubahan', 'DITANGGUHKAN' => 'Ditangguhkan', 'TIDAK DISETUJUI' => 'Tidak Disetujui'] as $key => $label)
            <tr>
                <td style="width:24px; text-align:center"><span class="kotak">{{ $approvedDecision === $key ? 'V' : '' }}</span></td>
                <td class="{{ $approvedDecision === $key ? 'tebal' : '' }}">{{ $label }}</td>
            </tr>
        @endforeach
    </table>
    <table class="ttd">
        <tr>
            <td style="width:55%"></td>
            <td>
                Kepala Biro/Sesditjen/Sesitjen/Kapus<br>
                <div style="height:46px;"></div>
                <span class="tebal"><u>{{ $leave->approver?->name ?? '.................................' }}</u></span><br>
                NIP. ...............................
            </td>
        </tr>
    </table>

</body>
</html>

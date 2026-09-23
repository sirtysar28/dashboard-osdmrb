<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>CV — {{ $employee->name }}</title>
    <style>
        @page { margin: 1.6cm 1.8cm; }
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #1a1a1a; line-height: 1.55; }
        .kop { border-bottom: 3px solid #163d4f; padding-bottom: 10px; margin-bottom: 16px; }
        .kop h1 { margin: 0; font-size: 19px; color: #163d4f; letter-spacing: .5px; }
        .kop .unit { margin: 3px 0 0; font-size: 11px; font-weight: bold; color: #2b5f78; }
        .judul { text-align: center; margin: 4px 0 16px; }
        .judul h2 { margin: 0; font-size: 15px; letter-spacing: 3px; text-decoration: underline; }
        table.cv { width: 100%; border-collapse: collapse; }
        table.cv td { padding: 2.5px 4px; vertical-align: top; font-size: 11px; }
        table.cv td.label { width: 155px; color: #555; }
        .sec { margin-top: 14px; }
        .sec h3 { font-size: 12px; color: #163d4f; border-bottom: 1.5px solid #163d4f; padding-bottom: 3px; margin: 0 0 7px; letter-spacing: .5px; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th { background: #eef3f6; color: #163d4f; font-size: 10.5px; text-align: left; padding: 5px 6px; border: 0.6px solid #b9c8d2; }
        table.grid td { font-size: 10.5px; padding: 4.5px 6px; border: 0.6px solid #b9c8d2; }
        .identitas { width: 100%; }
        .identitas td.nama { font-size: 15px; font-weight: bold; color: #163d4f; }
        .identitas td.jabatan { font-size: 11.5px; font-style: italic; color: #2b5f78; }
        .ttd { margin-top: 26px; width: 100%; }
        .ttd td { vertical-align: top; font-size: 11px; }
        .footer-note { margin-top: 18px; font-size: 9px; color: #888; text-align: center; border-top: 0.6px solid #ccc; padding-top: 6px; }
    </style>
</head>
<body>

    {{-- ================== KOP ================== --}}
    <div class="kop">
        <h1>KEMENTERIAN TRANSMIGRASI REPUBLIK INDONESIA</h1>
        <p class="unit">BIRO ORGANISASI, SUMBER DAYA MANUSIA DAN REFORMASI BIROKRASI</p>
    </div>

    {{-- ================== JUDUL ================== --}}
    <div class="judul">
        <h2>CURRICULUM VITAE</h2>
    </div>

    {{-- ================== IDENTITAS ================== --}}
    <table class="identitas">
        <tr>
            <td>
                <table class="cv">
                    <tr><td class="nama" colspan="2">{{ $employee->name }}</td></tr>
                    <tr><td class="jabatan" colspan="2">{{ $employee->position_name ?? '-' }}</td></tr>
                    <tr><td class="label">NIP / ID Pegawai</td><td>: {{ $employee->nip }}</td></tr>
                    <tr><td class="label">Status Kepegawaian</td><td>: {{ $employee->employmentStatus?->name ?? '-' }}</td></tr>
                    <tr><td class="label">Golongan / Pangkat</td><td>: {{ ($employee->rank?->code ?? '-') . ($employee->rank?->name ? ' — ' . $employee->rank->name : '') }}</td></tr>
                    <tr><td class="label">Unit Kerja</td><td>: {{ $employee->unit?->name ?? '-' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ================== DATA PERSONAL ================== --}}
    <div class="sec">
        <h3>A. DATA PERSONAL</h3>
        <table class="cv">
            <tr>
                <td class="label">Tempat, Tanggal Lahir</td><td>: {{ $employee->birth_place ?: '-' }}, {{ $employee->birth_date?->translatedFormat('d F Y') ?? '-' }}</td>
                <td class="label">Jenis Kelamin</td><td>: {{ $employee->gender_label }}</td>
            </tr>
            <tr>
                <td class="label">Agama</td><td>: {{ $employee->religion ?? '-' }}</td>
                <td class="label">Usia</td><td>: {{ $employee->age ?? '-' }} tahun</td>
            </tr>
            <tr>
                <td class="label">Kemampuan Berenang</td><td>: {{ $employee->swimming_skill_label }}</td>
                <td class="label">Bahasa Inggris</td><td>: {{ $employee->english_skill_label }}</td>
            </tr>
            <tr>
                <td class="label">Email</td><td>: {{ $employee->email ?? '-' }}</td>
                <td class="label">Telepon</td><td>: {{ $employee->phone ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Alamat</td><td colspan="3">: {{ $employee->address ?? '-' }}</td>
            </tr>
        </table>
    </div>

    {{-- ================== DATA KEPEGAWAIAN ================== --}}
    <div class="sec">
        <h3>B. DATA KEPEGAWAIAN</h3>
        <table class="cv">
            <tr>
                <td class="label">TMT CPNS</td><td>: {{ $employee->tmt_cpns?->translatedFormat('d F Y') ?? '-' }}</td>
                <td class="label">TMT ASN</td><td>: {{ $employee->tmt_pns?->translatedFormat('d F Y') ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">TMT Golongan</td><td>: {{ $employee->tmt_golongan?->translatedFormat('d F Y') ?? '-' }}</td>
                <td class="label">TMT Jabatan</td><td>: {{ $employee->tmt_jabatan?->translatedFormat('d F Y') ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Eselon</td><td>: {{ $employee->eselon ? 'Eselon ' . $employee->eselon : '-' }}</td>
                <td class="label">Level Fungsional</td><td>: {{ $employee->functional_level ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">NPWP</td><td>: {{ $employee->npwp ?? '-' }}</td>
                <td class="label">No. Karpeg</td><td>: {{ $employee->karpeg ?? '-' }}</td>
            </tr>
        </table>
    </div>

    {{-- ================== PENDIDIKAN ================== --}}
    <div class="sec">
        <h3>C. R IWAYAT PENDIDIKAN</h3>
        <table class="grid">
            <thead><tr><th style="width:26px">No</th><th>Jenjang</th><th>Kampus / Institusi &amp; Jurusan</th></tr></thead>
            <tbody>
                @foreach ([
                    ['Tingkat Terakhir', $employee->education?->name ?? '-'],
                    ['Pendidikan 1', $employee->education_1],
                    ['Pendidikan 2', $employee->education_2],
                    ['Pendidikan 3', $employee->education_3],
                ] as $i => [$jenjang, $nilai])
                    @if ($nilai)
                        <tr><td>{{ $i + 1 }}</td><td>{{ $jenjang }}</td><td>{{ $nilai }}</td></tr>
                    @endif
                @endforeach
                @if (! $employee->education && ! $employee->education_1 && ! $employee->education_2 && ! $employee->education_3)
                    <tr><td colspan="3" style="text-align:center">Belum ada data pendidikan.</td></tr>
                @endif
            </tbody>
        </table>
    </div>

    {{-- ================== RIWAYAT KENAIKAN PANGKAT ================== --}}
    <div class="sec">
        <h3>D. RIWAYAT KENAIKAN PANGKAT</h3>
        <table class="grid">
            <thead><tr><th style="width:26px">No</th><th>Kenaikan Pangkat</th><th>TMT</th><th>No. SK</th></tr></thead>
            <tbody>
                @forelse ($employee->rankHistories as $i => $history)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $history->transition_label }}{{ $history->newRank?->name ? ' — ' . $history->newRank->name : '' }}</td>
                        <td>{{ $history->effective_date?->translatedFormat('d F Y') ?? '-' }}</td>
                        <td>{{ $history->sk_number ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="text-align:center">Belum ada riwayat kenaikan pangkat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ================== RIWAYAT JABATAN ================== --}}
    <div class="sec">
        <h3>E. RIWAYAT JABATAN</h3>
        <table class="grid">
            <thead><tr><th style="width:26px">No</th><th>Jabatan</th><th>Unit Kerja</th><th>Mulai</th><th>Selesai</th></tr></thead>
            <tbody>
                @forelse ($employee->positions as $i => $history)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $history->position?->name ?? '-' }}</td>
                        <td>{{ $history->unit?->name ?? '-' }}</td>
                        <td>{{ $history->start_date?->translatedFormat('d F Y') ?? '-' }}</td>
                        <td>{{ $history->end_date?->translatedFormat('d F Y') ?? ($history->is_current ? 'Sekarang' : '-') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center">Belum ada riwayat jabatan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ================== RIWAYAT DIKLAT / SEMINAR / PELATIHAN ================== --}}
    <div class="sec">
        <h3>F. RIWAYAT DIKLAT, SEMINAR &amp; PELATIHAN</h3>
        <table class="grid">
            <thead><tr><th style="width:26px">No</th><th>Nama</th><th>Jenis</th><th>Lingkup</th><th>Penyelenggara</th><th>Tahun</th></tr></thead>
            <tbody>
                @forelse ($employee->trainings as $i => $training)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $training->name }}</td>
                        <td>{{ $training->type_label }}</td>
                        <td>{{ $training->scope_label }}</td>
                        <td>{{ $training->organizer ?? '-' }}</td>
                        <td>{{ $training->year ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center">Belum ada riwayat diklat, seminar atau pelatihan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ================== TANDA TANGAN ================== --}}
    <table class="ttd">
        <tr>
            <td style="width:55%"></td>
            <td>
                Jakarta, {{ now()->translatedFormat('d F Y') }}<br>
                Yang bersangkutan,
                <div style="height:52px"></div>
                <strong><u>{{ $employee->name }}</u></strong><br>
                NIP. {{ $employee->nip }}
            </td>
        </tr>
    </table>

    <p class="footer-note">
        Dokumen CV dihasilkan otomatis oleh Dashboard Biro OSDMRB — Kementerian Transmigrasi RI
        pada {{ now()->translatedFormat('d F Y H:i') }} WIB.
    </p>

</body>
</html>

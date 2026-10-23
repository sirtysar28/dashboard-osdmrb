<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Formulir Permohonan Cuti — {{ $leave->employee?->name }}</title>
    <style>
        /* Format baku BKN — FORM CUTI PNS & PPPK (Catatan Masukan 21 Okt 2026) */
        @page { margin: 1.4cm 1.5cm; }
        body { font-family: 'Times New Roman', serif; font-size: 10.5pt; color: #000; line-height: 1.35; }
        .kop { border-bottom: 3px double #000; padding-bottom: 6px; margin-bottom: 6px; }
        .kop table { width: 100%; border-collapse: collapse; }
        .kop h2 { margin: 0; font-size: 13pt; font-weight: bold; letter-spacing: .5px; }
        .kop .unit { margin: 1px 0; font-size: 13pt; font-weight: bold; }
        .kop p { margin: 0; font-size: 8.5pt; }

        /* blok kanan: nomor, tanggal, kepada yth. */
        table.header { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
        table.header td { font-size: 10.5pt; vertical-align: top; }
        .judul { text-align: center; margin: 10px 0 8px; }
        .judul h3 { margin: 0; font-size: 12pt; font-weight: bold; text-decoration: underline; letter-spacing: 1px; }
        .judul p { margin: 0; font-size: 10.5pt; }

        /* tabel formulir bergaris penuh seperti form resmi */
        table.form { width: 100%; border-collapse: collapse; }
        table.form th, table.form td { border: 1pt solid #000; padding: 4px 6px; vertical-align: top; font-size: 10.5pt; }
        table.form th.roman { width: 34px; text-align: center; font-weight: bold; vertical-align: middle; }
        .lbl { width: 88px; }
        .num { color: #444; font-size: 8pt; vertical-align: super; }
        .tebal { font-weight: bold; }
        .isi { min-height: 16px; }

        table.opt { width: 100%; border-collapse: collapse; }
        table.opt td { padding: 2.5px 4px; font-size: 10.5pt; vertical-align: top; }
        .kotak { display: inline-block; width: 12px; height: 12px; border: 1.1pt solid #000; margin-right: 5px; text-align: center; line-height: 11px; font-size: 9.5pt; font-weight: bold; }

        /* Bagian V — catatan cuti */
        table.catatan { width: 100%; border-collapse: collapse; }
        table.catatan th, table.catatan td { border: 0.8pt solid #000; padding: 3px 5px; font-size: 9.5pt; vertical-align: top; }

        /* tabel keputusan (VII & VIII) */
        table.keputusan { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.keputusan td { border: 1pt solid #000; padding: 7px 6px; text-align: center; font-size: 10.5pt; width: 25%; }

        table.ttd { width: 100%; margin-top: 4px; }
        table.ttd td { font-size: 10.5pt; vertical-align: top; }
        .ttd-kanan { text-align: left; padding-left: 46%; }

        .catatan-kaki { font-size: 8.5pt; margin-top: 10px; line-height: 1.45; }
        .catatan-kaki table td { padding-right: 10px; vertical-align: top; }
    </style>
</head>
<body>

    {{-- ================== KOP (format resmi) ================== --}}
    <div class="kop">
        <table>
            <tr>
                <td style="width:88px; vertical-align:middle;">
                    <img src="{{ public_path('images/logo-kementerian.png') }}" style="width:74px;" alt="Logo">
                </td>
                <td style="vertical-align:middle; text-align:center;">
                    <h2>KEMENTERIAN TRANSMIGRASI RI</h2>
                    <p class="unit">SEKRETARIAT JENDERAL</p>
                    <p>Jalan TMP. Kalibata Nomor 17 Jakarta Selatan 12750 Telepon 021 - 7989927, PO BOX 70 JKS PM/KBY</p>
                    <p>www.kemendesa.go.id</p>
                </td>
                <td style="width:88px;"></td>
            </tr>
        </table>
    </div>

    {{-- ================== NOMOR / TANGGAL / KEPADA YTH. ================== --}}
    <table class="header">
        <tr>
            <td style="width:55%"></td>
            <td>
                Nomor&nbsp;&nbsp;&nbsp;: .................................. /KPG.05.02/..../{{ $leave->created_at?->format('Y') ?? now()->format('Y') }}<br>
                Jakarta, {{ now()->translatedFormat('d F Y') }}<br>
                Kepada Yth.<br>
                {{ $leave->employee?->unit?->name ?? '......................................................' }}
            </td>
        </tr>
    </table>

    <div class="judul">
        <h3>FORMULIR PERMOHONAN CUTI</h3>
        <p>{{ $scheme === 'PPPK' ? 'PEGAWAI PEMERINTAH DENGAN PERJANJIAN KERJA (PPPK)' : 'PEGAWAI NEGERI SIPIL (PNS)' }}</p>
    </div>

    <table class="form">
        {{-- ================== I. DATA PEGAWAI ================== --}}
        <tr>
            <th class="roman" rowspan="5">I</th>
            <th class="lbl">DATA PEGAWAI</th>
            <th colspan="3">&nbsp;</th>
        </tr>
        <tr>
            <td class="lbl">Nama <span class="num">(2)</span></td>
            <td class="tebal isi">{{ $leave->employee?->name }}</td>
            <td class="lbl" style="width:92px">NIP <span class="num">(5)</span></td>
            <td class="isi">{{ $leave->employee?->nip }}</td>
        </tr>
        <tr>
            <td class="lbl">Jabatan <span class="num">(3)</span></td>
            <td class="isi">{{ $leave->employee?->position_name ?? '-' }}</td>
            <td class="lbl">Masa Kerja <span class="num">(6)</span></td>
            <td class="isi">{{ $leave->employee?->masa_kerja ?? '-' }}</td>
        </tr>
        <tr>
            <td class="lbl">Unit Kerja <span class="num">(4)</span></td>
            <td colspan="3" class="isi">{{ $leave->employee?->unit?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="lbl">Status</td>
            <td colspan="3" class="isi">{{ $leave->employee?->leave_scheme_label ?? '-' }}</td>
        </tr>

        {{-- ================== II. JENIS CUTI YANG DIAMBIL ================== --}}
        <tr>
            <th class="roman">II</th>
            <td colspan="4">
                <strong>JENIS CUTI YANG DIAMBIL <span class="num">**</span></strong>
                <table class="opt">
                    @foreach (\App\Models\LeaveRequest::typeOptionsFor($leave->employee) as $value => $label)
                        @php($col = $loop->iteration % 2 === 1)
                        @if ($col)<tr>@endif
                            <td style="width:50%">
                                <span class="kotak">{{ $leave->type === $value ? 'V' : '' }}</span>
                                <span class="{{ $leave->type === $value ? 'tebal' : '' }}">{{ $label }} <span class="num">(7)</span></span>
                            </td>
                        @if (! $col)</tr>@endif
                    @endforeach
                </table>
            </td>
        </tr>

        {{-- ================== III. ALASAN CUTI ================== --}}
        <tr>
            <th class="roman">III</th>
            <td class="lbl"><strong>ALASAN CUTI</strong></td>
            <td colspan="3" class="isi" style="min-height:34px">{{ $leave->reason }}</td>
        </tr>

        {{-- ================== IV. LAMANYA CUTI ================== --}}
        <tr>
            <th class="roman">IV</th>
            <td colspan="4">
                <strong>LAMANYA CUTI</strong>
                <table class="opt">
                    <tr>
                        <td style="width:25%">Selama <span class="num">(9)</span></td>
                        <td class="tebal" style="width:20%">: {{ $leave->total_days }} hari</td>
                        <td style="width:22%">Mulai tanggal <span class="num">(10)</span></td>
                        <td>: {{ $leave->start_date?->translatedFormat('d F Y') ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td>s/d</td>
                        <td colspan="3">: {{ $leave->end_date?->translatedFormat('d F Y') ?? '-' }}</td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ================== V. CATATAN CUTI ================== --}}
        <tr>
            <th class="roman">V</th>
            <td colspan="4">
                <strong>CATATAN CUTI <span class="num">***</span></strong>
                @php([$y2, $y1, $y0] = $leave->balance_years)

                @if ($scheme === 'PPPK')
                    {{-- format PPPK: sisa cuti tahunan + ceklis sakit/melahirkan/bersama --}}
                    <table class="catatan" style="margin-top:3px">
                        <tr>
                            <td style="width:30%"><strong>CUTI TAHUNAN</strong></td>
                            <td style="width:20%">Sisa <span class="num">(11)</span></td>
                            <td class="tebal" style="text-align:center">{{ $leave->annual_n ?? '-' }} hari</td>
                            <td rowspan="4" style="width:38%">{{ $leave->leave_note ?: $leave->note ?: '&nbsp;' }}</td>
                        </tr>
                        <tr>
                            <td><strong>CUTI SAKIT</strong></td>
                            <td colspan="2" style="text-align:center">
                                <span class="kotak">{{ $leave->type === \App\Models\LeaveRequest::TYPE_SAKIT ? 'V' : '' }}</span> <span class="num">(12)</span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>CUTI MELAHIRKAN</strong></td>
                            <td colspan="2" style="text-align:center">
                                <span class="kotak">{{ $leave->type === \App\Models\LeaveRequest::TYPE_MELAHIRKAN ? 'V' : '' }}</span> <span class="num">(12)</span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>CUTI BERSAMA</strong></td>
                            <td colspan="2" style="text-align:center">
                                <span class="kotak">{{ $leave->type === \App\Models\LeaveRequest::TYPE_BERSAMA ? 'V' : '' }}</span> <span class="num">(12)</span>
                            </td>
                        </tr>
                    </table>
                @else
                    {{-- format PNS: tabel sisa cuti tahunan N-2/N-1/N + ceklis jenis lain --}}
                    <table class="catatan" style="margin-top:3px">
                        <tr>
                            <td colspan="3" style="width:62%"><strong>CUTI TAHUNAN</strong></td>
                            <td rowspan="7" style="width:38%">{{ $leave->leave_note ?: $leave->note ?: '&nbsp;' }}</td>
                        </tr>
                        <tr>
                            <td style="width:14%">Tahun</td>
                            <td style="width:18%">Sisa</td>
                            <td style="width:30%">Keterangan</td>
                        </tr>
                        <tr><td>N &ndash; 2 ({{ $y2 }})</td><td class="tebal" style="text-align:center">{{ $leave->annual_n2 ?? '-' }}</td><td rowspan="3">&nbsp;</td></tr>
                        <tr><td>N &ndash; 1 ({{ $y1 }})</td><td class="tebal" style="text-align:center">{{ $leave->annual_n1 ?? '-' }} <span class="num">(11)</span></td></tr>
                        <tr><td>N ({{ $y0 }})</td><td class="tebal" style="text-align:center">{{ $leave->annual_n ?? '-' }} <span class="num">(12)</span></td></tr>
                        <tr>
                            <td colspan="3"><strong>CUTI BESAR</strong> <span class="kotak" style="float:right">{{ $leave->type === \App\Models\LeaveRequest::TYPE_BESAR ? 'V' : '' }}</span> <span class="num">(13)</span></td>
                        </tr>
                        <tr>
                            <td colspan="3"><strong>CUTI SAKIT</strong> <span class="kotak" style="float:right">{{ $leave->type === \App\Models\LeaveRequest::TYPE_SAKIT ? 'V' : '' }}</span> <span class="num">(13)</span></td>
                        </tr>
                        <tr>
                            <td colspan="3"><strong>CUTI MELAHIRKAN</strong> <span class="kotak" style="float:right">{{ $leave->type === \App\Models\LeaveRequest::TYPE_MELAHIRKAN ? 'V' : '' }}</span> <span class="num">(13)</span></td>
                        </tr>
                        <tr>
                            <td colspan="3"><strong>CUTI KARENA ALASAN PENTING</strong> <span class="kotak" style="float:right">{{ $leave->type === \App\Models\LeaveRequest::TYPE_ALASAN_PENTING ? 'V' : '' }}</span> <span class="num">(13)</span></td>
                        </tr>
                        <tr>
                            <td colspan="3"><strong>CUTI BERSAMA</strong> <span class="kotak" style="float:right">{{ $leave->type === \App\Models\LeaveRequest::TYPE_BERSAMA ? 'V' : '' }}</span> <span class="num">(13)</span></td>
                        </tr>
                        <tr>
                            <td colspan="3"><strong>CUTI DILUAR TANGGUNGAN NEGARA</strong> <span class="kotak" style="float:right">{{ $leave->type === \App\Models\LeaveRequest::TYPE_CLTN ? 'V' : '' }}</span> <span class="num">(13)</span></td>
                        </tr>
                    </table>
                @endif
            </td>
        </tr>

        {{-- ================== VI. ALAMAT SELAMA MENJALANKAN CUTI ================== --}}
        <tr>
            <th class="roman">VI</th>
            <td colspan="4">
                <strong>ALAMAT SELAMA MENJALANKAN CUTI</strong>
                <table class="opt">
                    <tr>
                        <td colspan="3" class="isi" style="min-height:24px">{{ $leave->address_during_leave ?? '&nbsp;' }} <span class="num">(14)</span></td>
                    </tr>
                    <tr>
                        <td style="width:12%">TELEPON</td>
                        <td style="width:30%">{{ $leave->phone_during_leave ?? '-' }} <span class="num">(15)</span></td>
                        <td class="ttd-kanan" rowspan="2">
                            Hormat saya, <span class="num">(16)</span><br>
                            <br><br>
                            <span class="tebal"><u>{{ $leave->employee?->name }}</u></span><br>
                            NIP. {{ $leave->employee?->nip }}
                        </td>
                    </tr>
                    <tr><td colspan="2"></td></tr>
                </table>
            </td>
        </tr>

        {{-- ================== VII. PERTIMBANGAN ATASAN LANGSUNG ================== --}}
        <tr>
            <th class="roman">VII</th>
            <td colspan="4">
                <strong>PERTIMBANGAN ATASAN LANGSUNG <span class="num">**</span></strong>
                @php($verifiedDecision = in_array($leave->status, [\App\Models\LeaveRequest::STATUS_VERIFIED, \App\Models\LeaveRequest::STATUS_APPROVED]) ? 'DISETUJUI' : ($leave->status === \App\Models\LeaveRequest::STATUS_REJECTED ? 'TIDAK DISETUJUI' : null))
                <table class="keputusan">
                    <tr>
                        <td class="{{ $verifiedDecision === 'DISETUJUI' ? 'tebal' : '' }}">DISETUJUI</td>
                        <td class="{{ $verifiedDecision === 'PERUBAHAN' ? 'tebal' : '' }}">PERUBAHAN <span class="num">****</span></td>
                        <td class="{{ $verifiedDecision === 'DITANGGUHKAN' ? 'tebal' : '' }}">DITANGGUHKAN <span class="num">****</span></td>
                        <td class="{{ $verifiedDecision === 'TIDAK DISETUJUI' ? 'tebal' : '' }}">TIDAK DISETUJUI <span class="num">****</span></td>
                    </tr>
                </table>
                <table class="ttd">
                    <tr>
                        <td class="ttd-kanan">
                            Kasubbag/Kepala Bagian/Direktur <span class="num">(17)</span><br>
                            <br><br>
                            <span class="tebal"><u>{{ $leave->verifier?->name ?? '.................................' }}</u></span><br>
                            NIP. ................................
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ================== VIII. KEPUTUSAN PEJABAT YANG BERWENANG ================== --}}
        <tr>
            <th class="roman">VIII</th>
            <td colspan="4">
                <strong>KEPUTUSAN PEJABAT YANG BERWENANG MEMBERIKAN CUTI <span class="num">**</span></strong>
                @php($approvedDecision = $leave->status === \App\Models\LeaveRequest::STATUS_APPROVED ? 'DISETUJUI' : ($leave->status === \App\Models\LeaveRequest::STATUS_REJECTED ? 'TIDAK DISETUJUI' : null))
                <table class="keputusan">
                    <tr>
                        <td class="{{ $approvedDecision === 'DISETUJUI' ? 'tebal' : '' }}">DISETUJUI</td>
                        <td class="{{ $approvedDecision === 'PERUBAHAN' ? 'tebal' : '' }}">PERUBAHAN <span class="num">****</span></td>
                        <td class="{{ $approvedDecision === 'DITANGGUHKAN' ? 'tebal' : '' }}">DITANGGUHKAN <span class="num">****</span></td>
                        <td class="{{ $approvedDecision === 'TIDAK DISETUJUI' ? 'tebal' : '' }}">TIDAK DISETUJUI <span class="num">****</span></td>
                    </tr>
                </table>
                <table class="ttd">
                    <tr>
                        <td class="ttd-kanan">
                            Kepala Biro/Sesditjen/Sesitjen/Kapus <span class="num">(18)</span><br>
                            <br><br>
                            <span class="tebal"><u>{{ $leave->approver?->name ?? '.................................' }}</u></span><br>
                            NIP. ................................
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ================== CATATAN KAKI (sesuai form baku) ================== --}}
    <div class="catatan-kaki">
        <table>
            <tr>
                <td colspan="2"><strong>Catatan :</strong></td>
            </tr>
            <tr>
                <td style="width:52%">
                    *&nbsp;&nbsp;&nbsp; Coret yang tidak perlu<br>
                    **&nbsp;&nbsp; Pilih salah satu dengan memberi tanda centang (V)<br>
                    ***&nbsp; Diisi oleh pejabat yang menangani bidang kepegawaian sebelum mengajukan cuti<br>
                    **** Diberi tanda centang dan alasannya
                </td>
                <td style="width:48%">
                    N&nbsp;&nbsp;&nbsp;&nbsp;: Cuti tahun berjalan<br>
                    N-1&nbsp;: Sisa cuti 1 tahun sebelumnya<br>
                    N-2&nbsp;: Sisa cuti 2 tahun sebelumnya
                </td>
            </tr>
        </table>
    </div>

</body>
</html>

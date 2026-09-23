@extends('layouts.app')

@section('page_title', 'Detail Pengajuan Cuti')
@section('page_subtitle', $leave->employee?->name)

@section('content')

<div class="row g-3">

    <div class="col-lg-8">

        <div class="chart-card mb-3">
            <div class="card-header-custom">
                <h5 class="mb-0"><i class="bi bi-calendar2-week me-2"></i>Formulir Permohonan Cuti</h5>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('leaves.print', $leave) }}" class="btn btn-sm btn-outline-danger" data-no-loader target="_blank">
                        <i class="bi bi-printer"></i> Cetak / Unduh PDF
                    </a>
                    <span class="badge bg-{{ $leave->status_badge }} fs-6">{{ $leave->status_label }}</span>
                </div>
            </div>

            {{-- ================= BAGIAN I : DATA PEGAWAI ================= --}}
            <h6 class="fw-bold mt-2" style="color: var(--osdmrb-primary)">I. DATA PEGAWAI</h6>
            <table class="table table-sm small mb-3">
                <tr><td class="text-muted" style="width:200px">Nama</td><td class="fw-semibold">{{ $leave->employee?->name }}</td></tr>
                <tr><td class="text-muted">NIP</td><td>{{ $leave->employee?->nip }}</td></tr>
                <tr><td class="text-muted">Jabatan</td><td>{{ $leave->employee?->position_name ?? '-' }}</td></tr>
                <tr><td class="text-muted">Masa Kerja</td><td>{{ $leave->employee?->masa_kerja ?? '-' }}</td></tr>
                <tr><td class="text-muted">Unit Kerja (Eselon II)</td><td>{{ $leave->employee?->unit?->name ?? '-' }}</td></tr>
            </table>

            {{-- ================= BAGIAN II : JENIS CUTI YANG DIAMBIL ================= --}}
            <h6 class="fw-bold" style="color: var(--osdmrb-primary)">II. JENIS CUTI YANG DIAMBIL</h6>
            <table class="table table-sm small mb-3">
                @foreach (\App\Models\LeaveRequest::typeOptions() as $value => $label)
                    <tr>
                        <td style="width:34px" class="text-center">
                            @if ($leave->type === $value)
                                <span class="badge bg-success"><i class="bi bi-check-lg"></i></span>
                            @else
                                <span class="text-muted">&ndash;</span>
                            @endif
                        </td>
                        <td class="{{ $leave->type === $value ? 'fw-semibold' : 'text-muted' }}">{{ $label }}</td>
                    </tr>
                @endforeach
            </table>

            {{-- ================= BAGIAN III : ALASAN CUTI ================= --}}
            <h6 class="fw-bold" style="color: var(--osdmrb-primary)">III. ALASAN CUTI</h6>
            <p class="small mb-3">{{ $leave->reason }}</p>

            {{-- ================= BAGIAN IV : LAMANYA CUTI ================= --}}
            <h6 class="fw-bold" style="color: var(--osdmrb-primary)">IV. LAMANYA CUTI</h6>
            <table class="table table-sm small mb-3">
                <tr><td class="text-muted" style="width:200px">Selama</td><td class="fw-semibold">{{ $leave->total_days }} hari</td></tr>
                <tr><td class="text-muted">Mulai Tanggal</td><td>{{ $leave->start_date?->translatedFormat('d F Y') ?? '-' }}</td></tr>
                <tr><td class="text-muted">s/d</td><td>{{ $leave->end_date?->translatedFormat('d F Y') ?? '-' }}</td></tr>
            </table>

            {{-- ================= BAGIAN V : CATATAN CUTI ================= --}}
            <h6 class="fw-bold" style="color: var(--osdmrb-primary)">V. CATATAN CUTI</h6>
            <p class="small text-muted mb-1">Diisi oleh pejabat yang menangani bidang kepegawaian (nominal otomatis dari sistem):</p>
            @php([$y2, $y1, $y0] = $leave->balance_years)
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered small text-center mb-0">
                    <thead>
                        <tr class="table-light">
                            <th rowspan="2" class="align-middle">Jenis Cuti</th>
                            <th colspan="3">Cuti Tahunan</th>
                            <th rowspan="2" class="align-middle">Keterangan</th>
                        </tr>
                        <tr class="table-light">
                            <th>N&ndash;2 ({{ $y2 }})</th><th>N&ndash;1 ({{ $y1 }})</th><th>N ({{ $y0 }})</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-start">Cuti Tahunan (sisa hari)</td>
                            <td class="fw-semibold">{{ $leave->annual_n2 ?? '-' }}</td>
                            <td class="fw-semibold">{{ $leave->annual_n1 ?? '-' }}</td>
                            <td class="fw-semibold">{{ $leave->annual_n ?? '-' }}</td>
                            <td rowspan="6" class="align-middle small text-start">{{ $leave->leave_note ?: $leave->note ?: '-' }}</td>
                        </tr>
                        <tr><td class="text-start">Cuti Besar</td><td colspan="3" class="text-muted">&ndash;</td></tr>
                        <tr><td class="text-start">Cuti Sakit</td><td colspan="3" class="text-muted">&ndash;</td></tr>
                        <tr><td class="text-start">Cuti Melahirkan</td><td colspan="3" class="text-muted">&ndash;</td></tr>
                        <tr><td class="text-start">Cuti Karena Alasan Penting</td><td colspan="3" class="text-muted">&ndash;</td></tr>
                        <tr><td class="text-start">Cuti di Luar Tanggungan Negara</td><td colspan="3" class="text-muted">&ndash;</td></tr>
                    </tbody>
                </table>
            </div>

            {{-- ================= BAGIAN VI : ALAMAT SELAMA MENJALANKAN CUTI ================= --}}
            <h6 class="fw-bold" style="color: var(--osdmrb-primary)">VI. ALAMAT SELAMA MENJALANKAN CUTI</h6>
            <table class="table table-sm small mb-3">
                <tr><td class="text-muted" style="width:200px">Alamat</td><td>{{ $leave->address_during_leave ?? '-' }}</td></tr>
                <tr><td class="text-muted">Telepon</td><td>{{ $leave->phone_during_leave ?? '-' }}</td></tr>
            </table>
            <div class="text-end">
                <p class="mb-0 text-muted small">Hormat saya,</p>
                <p class="mb-0 fw-bold">{{ $leave->employee?->name }}</p>
                <p class="mb-0 small text-muted">NIP. {{ $leave->employee?->nip }}</p>
            </div>
        </div>
    </div>

    <div class="col-lg-4">

        {{-- ================= BAGIAN VII : PERTIMBANGAN ATASAN LANGSUNG ================= --}}
        <div class="chart-card mb-3">
            <h5><i class="bi bi-7-circle-fill me-2"></i>VII. Pertimbangan Atasan Langsung</h5>

            @php($decisions = ['DISETUJUI' => 'Disetujui', 'PERUBAHAN' => 'Perubahan', 'DITANGGUHKAN' => 'Ditangguhkan', 'TIDAK DISETUJUI' => 'Tidak Disetujui'])
            @php($verifiedDecision = $leave->status === \App\Models\LeaveRequest::STATUS_VERIFIED || $leave->status === \App\Models\LeaveRequest::STATUS_APPROVED ? 'DISETUJUI' : ($leave->status === \App\Models\LeaveRequest::STATUS_REJECTED ? 'TIDAK DISETUJUI' : null))

            <table class="table table-sm small mb-0">
                @foreach ($decisions as $key => $label)
                    <tr>
                        <td style="width:34px" class="text-center">
                            @if ($verifiedDecision === $key)
                                <span class="badge bg-success"><i class="bi bi-check-lg"></i></span>
                            @else
                                <span class="text-muted">&ndash;</span>
                            @endif
                        </td>
                        <td class="{{ $verifiedDecision === $key ? 'fw-semibold' : 'text-muted' }}">{{ $label }}</td>
                    </tr>
                @endforeach
            </table>

            <hr class="my-2">
            <p class="small text-muted mb-0">Kasubbag/Kepala Bagian/Direktur</p>
            @if ($leave->verified_at)
                <p class="small mb-0 fw-bold">{{ $leave->verifier?->name ?? '-' }}</p>
                <small class="text-muted">{{ $leave->verified_at->setTimezone(config('app.timezone'))->translatedFormat('d M Y H:i') }}</small>
            @else
                <p class="small text-muted mb-0">Belum diverifikasi</p>
            @endif
        </div>

        {{-- ================= BAGIAN VIII : KEPUTUSAN PEJABAT YANG BERWENANG ================= --}}
        <div class="chart-card mb-3">
            <h5><i class="bi bi-8-circle-fill me-2"></i>VIII. Keputusan Pejabat yang Berwenang</h5>

            @php($approvedDecision = $leave->status === \App\Models\LeaveRequest::STATUS_APPROVED ? 'DISETUJUI' : ($leave->status === \App\Models\LeaveRequest::STATUS_REJECTED ? 'TIDAK DISETUJUI' : null))

            <table class="table table-sm small mb-0">
                @foreach ($decisions as $key => $label)
                    <tr>
                        <td style="width:34px" class="text-center">
                            @if ($approvedDecision === $key)
                                <span class="badge bg-success"><i class="bi bi-check-lg"></i></span>
                            @else
                                <span class="text-muted">&ndash;</span>
                            @endif
                        </td>
                        <td class="{{ $approvedDecision === $key ? 'fw-semibold' : 'text-muted' }}">{{ $label }}</td>
                    </tr>
                @endforeach
            </table>

            <hr class="my-2">
            <p class="small text-muted mb-0">Kepala Biro/Sesditjen/Sesitjen/Kapus</p>
            @if ($leave->approved_at)
                <p class="small mb-0 fw-bold">{{ $leave->approver?->name ?? '-' }}</p>
                <small class="text-muted">{{ $leave->approved_at->setTimezone(config('app.timezone'))->translatedFormat('d M Y H:i') }}</small>
            @else
                <p class="small text-muted mb-0">Menunggu persetujuan</p>
            @endif
        </div>

        {{-- ================= AKSI WORKFLOW ================= --}}
        <div class="chart-card mb-3">
            <h5><i class="bi bi-gear me-2"></i>Aksi</h5>

            <div class="d-grid gap-2">
                @can('verify letters')
                    @if ($leave->status === \App\Models\LeaveRequest::STATUS_PENDING)
                        <form method="POST" action="{{ route('leaves.verify', $leave) }}">
                            @csrf
                            <textarea name="note" rows="2" class="form-control mb-2" placeholder="Catatan kepegawaian / Bagian V (opsional)"></textarea>
                            <button class="btn btn-osdmrb w-100"><i class="bi bi-check2-circle"></i> Verifikasi Pengajuan</button>
                        </form>
                    @endif
                @endcan

                @can('approve letters')
                    @if ($leave->status === \App\Models\LeaveRequest::STATUS_VERIFIED)
                        <form method="POST" action="{{ route('leaves.approve', $leave) }}">
                            @csrf
                            <textarea name="note" rows="2" class="form-control mb-2" placeholder="Catatan persetujuan (opsional)"></textarea>
                            <button class="btn btn-success w-100"><i class="bi bi-patch-check"></i> Setujui Cuti</button>
                        </form>
                    @endif
                @endcan

                @can('verify letters')
                    @if (in_array($leave->status, [\App\Models\LeaveRequest::STATUS_PENDING, \App\Models\LeaveRequest::STATUS_VERIFIED]))
                        <form method="POST" action="{{ route('leaves.reject', $leave) }}"
                              onsubmit="return confirm('Tolak pengajuan cuti ini?')">
                            @csrf
                            <textarea name="note" rows="2" class="form-control mb-2" required
                                      placeholder="Alasan penolakan (wajib)"></textarea>
                            <button class="btn btn-outline-danger w-100"><i class="bi bi-x-circle"></i> Tolak Pengajuan</button>
                        </form>
                    @endif
                @endcan

                @if ($leave->status === \App\Models\LeaveRequest::STATUS_PENDING && (auth()->user()->employee_id === $leave->employee_id || auth()->user()->isPrivileged()))
                    <form method="POST" action="{{ route('leaves.destroy', $leave) }}"
                          onsubmit="return confirm('Batalkan pengajuan cuti ini?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-secondary w-100"><i class="bi bi-x-lg"></i> Batalkan Pengajuan</button>
                    </form>
                @endif

                <a href="{{ route('leaves.index') }}" class="btn btn-outline-osdmrb">
                    <i class="bi bi-arrow-left"></i> Kembali ke Daftar
                </a>
            </div>
        </div>
    </div>
</div>

@endsection

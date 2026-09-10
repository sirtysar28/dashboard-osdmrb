@extends('layouts.app')

@section('page_title', ($isOwnProfile ?? false) ? 'Profil Saya' : 'Detail Pegawai')
@section('page_subtitle', $employee->name)

@section('content')

<div class="row g-3">

    <div class="col-lg-4">

        <div class="detail-card text-center mb-3">
            <div class="avatar mx-auto mb-3"
                 style="width:86px;height:86px;border-radius:50%;background:linear-gradient(135deg,#163d4f,#2b5f78);color:#fff;display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:700;">
                {{ collect(explode(' ', trim($employee->name)))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') }}
            </div>
            <h5 class="mb-1 fw-bold">{{ $employee->name }}</h5>
            <p class="text-muted small mb-2">{{ $employee->position_name ?? '-' }}</p>
            <span class="badge {{ $employee->employmentStatus?->code === 'PNS' ? 'bg-primary' : 'bg-info' }} mb-2">
                {{ $employee->employmentStatus?->name ?? '-' }}
            </span>
            <p class="small text-muted mb-0">NIP: {{ $employee->nip }}</p>
        </div>

        <div class="detail-card mb-3">
            <h5><i class="bi bi-info-circle me-2"></i>Informasi Personal</h5>
            <table class="table table-sm small mb-0">
                <tr><td class="text-muted w-40">Jenis Kelamin</td><td>{{ $employee->gender_label }}</td></tr>
                <tr><td class="text-muted">Tempat, Tgl Lahir</td><td>{{ $employee->birth_place ?: '-' }}, {{ $employee->birth_date?->translatedFormat('d F Y') ?? '-' }}</td></tr>
                <tr><td class="text-muted">Usia</td><td>{{ $employee->age ?? '-' }} tahun</td></tr>
                <tr><td class="text-muted">Agama</td><td>{{ $employee->religion ?? '-' }}</td></tr>
                <tr><td class="text-muted">Email</td><td>{{ $employee->email ?? '-' }}</td></tr>
                <tr><td class="text-muted">Telepon</td><td>{{ $employee->phone ?? '-' }}</td></tr>
                <tr><td class="text-muted">Alamat</td><td>{{ $employee->address ?? '-' }}</td></tr>
            </table>
        </div>

        <div class="detail-card">
            <h5><i class="bi bi-mortarboard me-2"></i>Pendidikan</h5>
            <table class="table table-sm small mb-0">
                <tr><td class="text-muted w-40">Tingkat</td><td>{{ $employee->education?->name ?? '-' }}</td></tr>
                <tr><td class="text-muted">Pendidikan 1</td><td>{{ $employee->education_1 ?? '-' }}</td></tr>
                <tr><td class="text-muted">Pendidikan 2</td><td>{{ $employee->education_2 ?? '-' }}</td></tr>
                <tr><td class="text-muted">Pendidikan 3</td><td>{{ $employee->education_3 ?? '-' }}</td></tr>
            </table>
        </div>
    </div>

    <div class="col-lg-8">

        <div class="detail-card mb-3">
            <div class="card-header-custom">
                <h5 class="mb-0"><i class="bi bi-briefcase me-2"></i>Data Kepegawaian</h5>
                @if (auth()->user()->isPrivileged())
                    <a href="{{ route('employees.edit', $employee) }}" class="btn btn-sm btn-outline-osdmrb">
                        <i class="bi bi-pencil"></i> Ubah
                    </a>
                @endif
            </div>
            <div class="row g-3">
                @foreach ([
                    'Jenis Pegawai' => $employee->employee_type_label . ($employee->category ? ' — ' . $employee->category : ''),
                    'Golongan / Pangkat' => ($employee->rank?->code ?? '-') . ($employee->rank?->name ? ' — ' . $employee->rank->name : ''),
                    'Unit Kerja' => $employee->unit?->name ?? '-',
                    'Eselon' => $employee->eselon ? 'Eselon ' . $employee->eselon : 'Bukan Pejabat Struktural',
                    'Level Fungsional' => $employee->functional_level ?? '-',
                    'TMT Jabatan' => $employee->tmt_jabatan?->translatedFormat('d F Y') ?? '-',
                    'TMT Golongan' => $employee->tmt_golongan?->translatedFormat('d F Y') ?? '-',
                    'Kenaikan Pangkat / Jabatan Berikutnya' => $employee->next_promotion_estimated?->translatedFormat('d F Y') ?? '-',
                    'TMT CPNS' => $employee->tmt_cpns?->translatedFormat('d F Y') ?? '-',
                    'TMT PNS' => $employee->tmt_pns?->translatedFormat('d F Y') ?? '-',
                    'Batas Usia Pensiun (BUP)' => ($employee->effective_retirement_date?->translatedFormat('d F Y') ?? '-')
                        . ($employee->bup ? ' (' . $employee->bup . ' th)' : ''),
                    'NPWP' => $employee->npwp ?? '-',
                    'No. Karpeg' => $employee->karpeg ?? '-',
                ] as $label => $value)
                    <div class="col-md-6">
                        <div class="border rounded p-2 h-100">
                            <div class="text-muted" style="font-size:11px">{{ $label }}</div>
                            <div class="fw-semibold" style="font-size:13.5px">{{ $value }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="detail-card mb-3">
            <h5><i class="bi bi-diagram-3 me-2"></i>Riwayat Jabatan</h5>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr><th>Jabatan</th><th>Unit</th><th>Mulai</th><th>Selesai</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($employee->positions as $history)
                            <tr>
                                <td>{{ $history->position?->name ?? '-' }}</td>
                                <td>{{ $history->unit?->name ?? '-' }}</td>
                                <td>{{ $history->start_date?->translatedFormat('d/m/Y') }}</td>
                                <td>{{ $history->end_date?->translatedFormat('d/m/Y') ?? '-' }}</td>
                                <td>
                                    @if ($history->is_current)
                                        <span class="badge bg-success">Jabatan Saat Ini</span>
                                    @else
                                        <span class="badge bg-secondary">Selesai</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">Belum ada riwayat jabatan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="detail-card">
            <h5><i class="bi bi-envelope-paper me-2"></i>Riwayat Pengajuan Surat</h5>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr><th>Nomor</th><th>Jenis</th><th>Perihal</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($employee->letters as $letter)
                            <tr>
                                <td>{{ $letter->number ?? '-' }}</td>
                                <td>{{ $letter->letterType?->name }}</td>
                                <td><a href="{{ route('letters.show', $letter) }}" class="text-decoration-none">{{ $letter->subject }}</a></td>
                                <td><span class="badge bg-{{ $letter->status_badge }}">{{ $letter->status_label }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">Belum ada pengajuan surat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

@endsection

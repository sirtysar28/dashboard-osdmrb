@extends('layouts.app')

@section('page_title', ($isOwnProfile ?? false) ? 'Profil Saya' : 'Detail Pegawai')
@section('page_subtitle', $employee->name)

@php($canManage = auth()->user()->isPrivileged() || ($isOwnProfile ?? false))

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
            <span class="badge {{ in_array($employee->employmentStatus?->code, ['ASN', 'PNS']) ? 'bg-primary' : 'bg-info' }} mb-2">
                {{ $employee->employmentStatus?->name ?? '-' }}
            </span>
            <p class="small text-muted mb-2">NIP: {{ $employee->nip }}</p>
            <a href="{{ route('employees.cv', $employee) }}" class="btn btn-sm btn-outline-osdmrb" data-no-loader>
                <i class="bi bi-file-earmark-person"></i> Unduh CV (PDF)
            </a>
        </div>

        <div class="detail-card mb-3">
            <h5><i class="bi bi-info-circle me-2"></i>Informasi Personal</h5>
            <table class="table table-sm small mb-0">
                <tr><td class="text-muted w-40">Jenis Kelamin</td><td>{{ $employee->gender_label }}</td></tr>
                <tr><td class="text-muted">Tempat, Tgl Lahir</td><td>{{ $employee->birth_place ?: '-' }}, {{ $employee->birth_date?->translatedFormat('d F Y') ?? '-' }}</td></tr>
                <tr><td class="text-muted">Usia</td><td>{{ $employee->age ?? '-' }} tahun</td></tr>
                <tr><td class="text-muted">Agama</td><td>{{ $employee->religion ?? '-' }}</td></tr>
                <tr><td class="text-muted">Kemampuan Berenang</td><td>{{ $employee->swimming_skill_label }}</td></tr>
                <tr><td class="text-muted">Kemampuan Bahasa Inggris</td><td>{{ $employee->english_skill_label }}</td></tr>
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
                    'Kenaikan Gaji Berkala (KGB) Berikutnya' => $employee->next_salary_raise?->translatedFormat('d F Y') ?? '-',
                    'TMT CPNS' => $employee->tmt_cpns?->translatedFormat('d F Y') ?? '-',
                    'TMT ASN' => $employee->tmt_pns?->translatedFormat('d F Y') ?? '-',
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

        {{-- ============ RIWAYAT KENAIKAN PANGKAT (IIIA -> IIIB dst.) ============ --}}
        <div class="detail-card mb-3">
            <div class="card-header-custom">
                <h5 class="mb-0"><i class="bi bi-arrow-up-right-circle me-2"></i>Riwayat Kenaikan Pangkat</h5>
                @if ($canManage)
                    <button class="btn btn-sm btn-outline-osdmrb" type="button" data-bs-toggle="collapse"
                            data-bs-target="#formRankHistory" aria-expanded="false">
                        <i class="bi bi-plus-lg"></i> Tambah
                    </button>
                @endif
            </div>

            @if ($canManage)
                <div class="collapse mb-3" id="formRankHistory">
                    <form method="POST" action="{{ route('employees.rank-histories.store', $employee) }}"
                          class="border rounded p-3 bg-light">
                        @csrf
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label small">Dari Pangkat</label>
                                <select name="old_rank_id" class="form-select form-select-sm">
                                    <option value="">- Pilih -</option>
                                    @foreach ($rankList as $rank)
                                        <option value="{{ $rank->id }}" {{ $employee->rank_id === $rank->id ? 'selected' : '' }}>{{ $rank->code }} {{ $rank->name ? '- ' . $rank->name : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Ke Pangkat <span class="text-danger">*</span></label>
                                <select name="new_rank_id" class="form-select form-select-sm" required>
                                    <option value="">- Pilih -</option>
                                    @foreach ($rankList as $rank)
                                        <option value="{{ $rank->id }}">{{ $rank->code }} {{ $rank->name ? '- ' . $rank->name : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">TMT / Tanggal Efektif</label>
                                <input type="date" name="effective_date" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Nomor SK</label>
                                <input type="text" name="sk_number" class="form-control form-control-sm" placeholder="mis. 888/2026">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Catatan</label>
                                <input type="text" name="notes" class="form-control form-control-sm">
                            </div>
                            <div class="col-12 text-end">
                                <button class="btn btn-sm btn-osdmrb"><i class="bi bi-save"></i> Simpan Riwayat</button>
                            </div>
                        </div>
                    </form>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr><th>Kenaikan Pangkat</th><th>TMT</th><th>No. SK</th><th>Catatan</th><th class="text-center">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($employee->rankHistories as $history)
                            <tr>
                                <td><span class="badge bg-primary-subtle text-primary">{{ $history->transition_label }}</span>
                                    {{ $history->newRank?->name }}
                                </td>
                                <td>{{ $history->effective_date?->translatedFormat('d/m/Y') ?? '-' }}</td>
                                <td>{{ $history->sk_number ?? '-' }}</td>
                                <td>{{ $history->notes ?? '-' }}</td>
                                <td class="text-center">
                                    @if ($canManage)
                                        <form action="{{ route('employees.rank-histories.destroy', $history) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Hapus riwayat kenaikan pangkat ini?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">Belum ada riwayat kenaikan pangkat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ============ RIWAYAT DIKLAT, SEMINAR & PELATIHAN ============ --}}
        <div class="detail-card mb-3">
            <div class="card-header-custom">
                <h5 class="mb-0"><i class="bi bi-mortarboard-fill me-2"></i>Riwayat Diklat, Seminar &amp; Pelatihan</h5>
                @if ($canManage)
                    <button class="btn btn-sm btn-outline-osdmrb" type="button" data-bs-toggle="collapse"
                            data-bs-target="#formTraining" aria-expanded="false">
                        <i class="bi bi-plus-lg"></i> Tambah
                    </button>
                @endif
            </div>

            @if ($canManage)
                <div class="collapse mb-3" id="formTraining">
                    <form method="POST" action="{{ route('modules.diklat.store') }}"
                          enctype="multipart/form-data" class="border rounded p-3 bg-light">
                        @csrf
                        <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                        <input type="hidden" name="from" value="profile">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small">Nama Diklat / Seminar / Pelatihan <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Jenis</label>
                                <select name="type" class="form-select form-select-sm">
                                    @foreach (\App\Models\EmployeeTraining::TYPES as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Lingkup</label>
                                <select name="scope" class="form-select form-select-sm">
                                    @foreach (\App\Models\EmployeeTraining::scopeOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Penyelenggara</label>
                                <input type="text" name="organizer" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Tahun</label>
                                <input type="number" name="year" class="form-control form-control-sm" min="1900" max="2100" value="{{ now()->year }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">JP</label>
                                <input type="number" name="hours" class="form-control form-control-sm" min="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Sertifikat (PDF/JPG maks 10MB)</label>
                                <input type="file" name="file" class="form-control form-control-sm">
                            </div>
                            <div class="col-12 text-end">
                                <button class="btn btn-sm btn-osdmrb"><i class="bi bi-save"></i> Simpan Riwayat</button>
                            </div>
                        </div>
                    </form>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr><th>Nama</th><th>Jenis</th><th>Lingkup</th><th>Penyelenggara</th><th>Tahun</th><th class="text-center">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($employee->trainings as $training)
                            <tr>
                                <td class="fw-semibold">{{ $training->name }}
                                    @if ($training->certificate_number)
                                        <small class="text-muted d-block">No. Sertifikat: {{ $training->certificate_number }}</small>
                                    @endif
                                </td>
                                <td><span class="badge bg-{{ $training->type_badge }}">{{ $training->type_label }}</span></td>
                                <td>
                                    <span class="badge {{ $training->scope === \App\Models\EmployeeTraining::SCOPE_ABROAD ? 'bg-danger' : 'bg-secondary bg-opacity-50' }}">
                                        {{ $training->scope_label }}
                                    </span>
                                </td>
                                <td>{{ $training->organizer ?? '-' }}</td>
                                <td>{{ $training->year ?? '-' }}</td>
                                <td class="text-center text-nowrap">
                                    @if ($training->has_file)
                                        <a href="{{ route('modules.diklat.download', $training) }}" class="btn btn-sm btn-outline-secondary" title="Unduh Sertifikat" data-no-loader>
                                            <i class="bi bi-download"></i>
                                        </a>
                                    @endif
                                    @if ($canManage)
                                        <form action="{{ route('modules.diklat.destroy', $training) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Hapus riwayat ini?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">Belum ada riwayat diklat, seminar atau pelatihan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
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

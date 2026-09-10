@extends('layouts.app')

@section('page_title', 'Master Data')
@section('page_subtitle', 'Kelola unit kerja, pendidikan, golongan, status & jabatan')

@section('content')

@php($canManage = auth()->user()->isAdmin())

@unless ($canManage)
    <div class="alert alert-info d-flex align-items-center gap-2 mb-3" role="alert">
        <i class="bi bi-eye fs-5"></i>
        <div>
            <strong>Mode Biro SDM (hanya lihat).</strong>
            Master data dapat dilihat &amp; diexport, namun perubahan hanya dilakukan oleh Admin Instansi.
        </div>
    </div>
@endunless

<div class="d-flex justify-content-end mb-3 export-btn">
    <div class="dropdown">
        <button class="btn btn-sm btn-outline-osdmrb dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-download"></i> Export Master Data
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li>
                <a class="dropdown-item text-success" data-no-loader href="{{ route('master.export', ['format' => 'xlsx']) }}">
                    <i class="bi bi-file-earmark-excel"></i> Excel (.xlsx)
                </a>
            </li>
            <li>
                <a class="dropdown-item text-danger" data-no-loader href="{{ route('master.export', ['format' => 'pdf']) }}">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
            </li>
        </ul>
    </div>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><a class="nav-link {{ !request('tab') || request('tab') === 'units' ? 'active' : '' }} fw-semibold"
        data-bs-toggle="tab" href="#units" role="tab"><i class="bi bi-diagram-3 me-1"></i>Unit Kerja</a></li>
    <li class="nav-item"><a class="nav-link {{ request('tab') === 'education' ? 'active' : '' }} fw-semibold"
        data-bs-toggle="tab" href="#education" role="tab"><i class="bi bi-mortarboard me-1"></i>Pendidikan</a></li>
    <li class="nav-item"><a class="nav-link {{ request('tab') === 'campuses' ? 'active' : '' }} fw-semibold"
        data-bs-toggle="tab" href="#campuses" role="tab"><i class="bi bi-bank me-1"></i>Kampus</a></li>
    <li class="nav-item"><a class="nav-link {{ request('tab') === 'ranks' ? 'active' : '' }} fw-semibold"
        data-bs-toggle="tab" href="#ranks" role="tab"><i class="bi bi-award me-1"></i>Golongan</a></li>
    <li class="nav-item"><a class="nav-link {{ request('tab') === 'statuses' ? 'active' : '' }} fw-semibold"
        data-bs-toggle="tab" href="#statuses" role="tab"><i class="bi bi-person-vcard me-1"></i>Status ASN</a></li>
    <li class="nav-item"><a class="nav-link {{ request('tab') === 'joblevels' ? 'active' : '' }} fw-semibold"
        data-bs-toggle="tab" href="#joblevels" role="tab"><i class="bi bi-layers me-1"></i>Level Jabatan</a></li>
    <li class="nav-item"><a class="nav-link {{ request('tab') === 'positiontypes' ? 'active' : '' }} fw-semibold"
        data-bs-toggle="tab" href="#positiontypes" role="tab"><i class="bi bi-tags me-1"></i>Jenis Jabatan</a></li>
    <li class="nav-item"><a class="nav-link {{ request('tab') === 'positions' ? 'active' : '' }} fw-semibold"
        data-bs-toggle="tab" href="#positions" role="tab"><i class="bi bi-person-workspace me-1"></i>Jabatan</a></li>
    <li class="nav-item"><a class="nav-link {{ request('tab') === 'arsip' ? 'active' : '' }} fw-semibold"
        data-bs-toggle="tab" href="#arsip" role="tab"><i class="bi bi-archive me-1"></i>Klasifikasi Arsip</a></li>
</ul>

<div class="tab-content">

    <!-- ================= UNITS ================= -->
    <div class="tab-pane fade {{ !request('tab') || request('tab') === 'units' ? 'show active' : '' }}" id="units">
        <div class="row g-3">
            <div class="col-lg-4 form-col-start{{ $canManage ? '' : ' d-none' }}">
                <div class="chart-card">
                    <h5><i class="bi bi-plus-circle me-2"></i>Tambah Unit Kerja</h5>
                    <form method="POST" action="{{ route('master.units.store') }}">
                        @csrf
                        <div class="mb-3"><label class="form-label">Induk Unit</label>
                            <select name="parent_id" class="form-select">
                                <option value="">- Tidak ada (teratas) -</option>
                                @foreach ($allUnits as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->level_label }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3"><label class="form-label">Kode</label>
                            <input type="text" name="code" class="form-control" required placeholder="mis. ES2-02"></div>
                        <div class="mb-3"><label class="form-label">Nama Unit</label>
                            <input type="text" name="name" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label">Level</label>
                            <select name="level" class="form-select">
                                @foreach (['KEMENTERIAN' => 'Kementerian', 'ES_I' => 'Eselon I', 'ES_II' => 'Eselon II', 'ES_III' => 'Eselon III', 'BALAI' => 'Balai', 'LAINNYA' => 'Lainnya'] as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3"><label class="form-label">Alamat</label>
                            <input type="text" name="address" class="form-control"></div>
                        <button class="btn btn-osdmrb w-100"><i class="bi bi-save"></i> Simpan</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="table-card">
                    <h5>Struktur Unit Kerja ({{ $units->total() }})</h5>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Kode</th><th>Nama</th><th>Level</th><th>Induk</th><th class="text-center">Aksi</th></tr></thead>
                            <tbody>
                            @foreach ($units as $unit)
                                <tr>
                                    <td><code>{{ $unit->code }}</code></td>
                                    <td>{{ $unit->name }}</td>
                                    <td><span class="badge bg-osdmrb" style="background:#163d4f">{{ $unit->level_label }}</span></td>
                                    <td>{{ $unit->parent?->name ?? '-' }}</td>
                                    <td class="text-center text-nowrap">
                                        <button class="btn btn-sm btn-outline-osdmrb {{ $canManage ? '' : 'd-none' }}" title="Ubah"
                                                data-bs-toggle="modal" data-bs-target="#editUnit{{ $unit->id }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="{{ route('master.units.destroy', $unit) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Hapus unit kerja ini?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger {{ $canManage ? '' : 'd-none' }}" title="Hapus"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($units->hasPages())
                        <div class="mt-3 d-flex justify-content-center">{{ $units->appends(['tab' => 'units'])->links() }}</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Modals ubah unit --}}
        @foreach ($units as $unit)
            @include('master.partials.edit-modal', [
                'modalClass' => $canManage ? '' : 'd-none',
                'id' => 'editUnit'.$unit->id,
                'title' => 'Ubah Unit Kerja',
                'action' => route('master.units.update', $unit),
                'fields' => [
                    ['name' => 'parent_id', 'label' => 'Induk Unit', 'type' => 'select', 'value' => $unit->parent_id,
                     'options' => $allUnits->filter(fn ($u) => $u->id !== $unit->id)->map(fn ($u) => ['value' => $u->id, 'label' => $u->name.' ('.$u->level_label.')'])->all(),
                     'placeholder' => '- Tidak ada (teratas) -'],
                    ['name' => 'code', 'label' => 'Kode', 'value' => $unit->code, 'required' => true],
                    ['name' => 'name', 'label' => 'Nama Unit', 'value' => $unit->name, 'required' => true],
                    ['name' => 'level', 'label' => 'Level', 'type' => 'select', 'value' => $unit->level,
                     'options' => collect(['KEMENTERIAN' => 'Kementerian', 'ES_I' => 'Eselon I', 'ES_II' => 'Eselon II', 'ES_III' => 'Eselon III', 'BALAI' => 'Balai', 'LAINNYA' => 'Lainnya'])
                         ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all()],
                    ['name' => 'address', 'label' => 'Alamat', 'value' => $unit->address],
                ],
            ])
        @endforeach
    </div>

    <!-- ================= EDUCATION ================= -->
    <div class="tab-pane fade {{ request('tab') === 'education' ? 'show active' : '' }}" id="education">
        <div class="row g-3">
            <div class="col-lg-4 form-col-start{{ $canManage ? '' : ' d-none' }}">
                <div class="chart-card">
                    <h5>Tambah Tingkat Pendidikan</h5>
                    <form method="POST" action="{{ route('master.education.store') }}">
                        @csrf
                        <div class="mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" required placeholder="S3"></div>
                        <div class="mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" required placeholder="S3 / Doktor"></div>
                        <div class="mb-3"><label class="form-label">Urutan</label><input type="number" name="sort_order" class="form-control" value="0"></div>
                        <button class="btn btn-osdmrb w-100">Simpan</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="table-card">
                    <h5>Daftar Tingkat Pendidikan</h5>
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Kode</th><th>Nama</th><th>Urutan</th><th class="text-center">Aksi</th></tr></thead>
                        <tbody>
                        @foreach ($educationLevels as $level)
                            <tr>
                                <td><code>{{ $level->code }}</code></td>
                                <td>{{ $level->name }}</td>
                                <td>{{ $level->sort_order }}</td>
                                <td class="text-center text-nowrap">
                                    <button class="btn btn-sm btn-outline-osdmrb {{ $canManage ? '' : 'd-none' }}" title="Ubah"
                                            data-bs-toggle="modal" data-bs-target="#editEducation{{ $level->id }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ route('master.education.destroy', $level) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus data ini?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger {{ $canManage ? '' : 'd-none' }}" title="Hapus"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    @if ($educationLevels->hasPages())
                        <div class="mt-3 d-flex justify-content-center">{{ $educationLevels->appends(['tab' => 'education'])->links() }}</div>
                    @endif
                </div>
            </div>
        </div>

        @foreach ($educationLevels as $level)
            @include('master.partials.edit-modal', [
                'modalClass' => $canManage ? '' : 'd-none',
                'id' => 'editEducation'.$level->id,
                'title' => 'Ubah Tingkat Pendidikan',
                'action' => route('master.education.update', $level),
                'fields' => [
                    ['name' => 'code', 'label' => 'Kode', 'value' => $level->code, 'required' => true],
                    ['name' => 'name', 'label' => 'Nama', 'value' => $level->name, 'required' => true],
                    ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number', 'value' => $level->sort_order],
                ],
            ])
        @endforeach
    </div>

    <!-- ================= CAMPUSES (MASTER KAMPUS) ================= -->
    <div class="tab-pane fade {{ request('tab') === 'campuses' ? 'show active' : '' }}" id="campuses">
        <div class="row g-3">
            <div class="col-lg-4 form-col-start{{ $canManage ? '' : ' d-none' }}">
                <div class="chart-card">
                    <h5>Tambah Kampus / Perguruan Tinggi</h5>
                    <form method="POST" action="{{ route('master.campuses.store') }}">
                        @csrf
                        <div class="mb-3"><label class="form-label">Nama Kampus</label>
                            <input type="text" name="name" class="form-control" required placeholder="mis. Universitas Diponegoro"></div>
                        <div class="mb-3"><label class="form-label">Kota</label>
                            <input type="text" name="city" class="form-control" placeholder="mis. Semarang"></div>
                        <div class="mb-3"><label class="form-label">Jenis</label>
                            <select name="type" class="form-select">
                                <option value="negeri">Negeri</option>
                                <option value="swasta">Swasta</option>
                                <option value="luar_negeri">Luar Negeri</option>
                            </select>
                        </div>
                        <div class="mb-3"><label class="form-label">Urutan</label><input type="number" name="sort_order" class="form-control" value="0"></div>
                        <button class="btn btn-osdmrb w-100">Simpan</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="table-card">
                    <h5>Daftar Kampus / Perguruan Tinggi ({{ $campuses->total() }})</h5>
                    <p class="text-muted small mb-2">Digunakan sebagai pilihan dropdown pendidikan terakhir (S1/S2/S3) pada form pegawai.</p>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Nama Kampus</th><th>Kota</th><th>Jenis</th><th class="text-center">Aksi</th></tr></thead>
                            <tbody>
                            @forelse ($campuses as $campus)
                                <tr>
                                    <td>{{ $campus->name }}</td>
                                    <td>{{ $campus->city ?? '-' }}</td>
                                    <td><span class="badge {{ $campus->type_badge }}">{{ $campus->type_label }}</span></td>
                                    <td class="text-center text-nowrap">
                                        <button class="btn btn-sm btn-outline-osdmrb {{ $canManage ? '' : 'd-none' }}" title="Ubah"
                                                data-bs-toggle="modal" data-bs-target="#editCampus{{ $campus->id }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="{{ route('master.campuses.destroy', $campus) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Hapus kampus ini?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger {{ $canManage ? '' : 'd-none' }}" title="Hapus"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">Belum ada data kampus.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($campuses->hasPages())
                        <div class="mt-3 d-flex justify-content-center">{{ $campuses->appends(['tab' => 'campuses'])->links() }}</div>
                    @endif
                </div>
            </div>
        </div>

        @foreach ($campuses as $campus)
            @include('master.partials.edit-modal', [
                'modalClass' => $canManage ? '' : 'd-none',
                'id' => 'editCampus'.$campus->id,
                'title' => 'Ubah Kampus',
                'action' => route('master.campuses.update', $campus),
                'fields' => [
                    ['name' => 'name', 'label' => 'Nama Kampus', 'value' => $campus->name, 'required' => true],
                    ['name' => 'city', 'label' => 'Kota', 'value' => $campus->city],
                    ['name' => 'type', 'label' => 'Jenis', 'type' => 'select', 'value' => $campus->type,
                     'options' => collect(['negeri' => 'Negeri', 'swasta' => 'Swasta', 'luar_negeri' => 'Luar Negeri'])
                         ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all()],
                    ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number', 'value' => $campus->sort_order],
                ],
            ])
        @endforeach
    </div>

    <!-- ================= RANKS ================= -->
    <div class="tab-pane fade {{ request('tab') === 'ranks' ? 'show active' : '' }}" id="ranks">
        <div class="row g-3">
            <div class="col-lg-4 form-col-start{{ $canManage ? '' : ' d-none' }}">
                <div class="chart-card">
                    <h5>Tambah Golongan</h5>
                    <form method="POST" action="{{ route('master.ranks.store') }}">
                        @csrf
                        <div class="mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" required placeholder="III/a"></div>
                        <div class="mb-3"><label class="form-label">Nama Pangkat</label><input type="text" name="name" class="form-control" placeholder="Penata Muda"></div>
                        <div class="mb-3"><label class="form-label">Golongan</label><input type="text" name="group_name" class="form-control" placeholder="III/a"></div>
                        <div class="mb-3"><label class="form-label">Urutan</label><input type="number" name="sort_order" class="form-control" value="0"></div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_pppk" value="1" id="is_pppk">
                            <label class="form-check-label" for="is_pppk">Golongan PPPK</label>
                        </div>
                        <button class="btn btn-osdmrb w-100">Simpan</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="table-card">
                    <h5>Daftar Golongan / Pangkat</h5>
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Kode</th><th>Nama Pangkat</th><th>Golongan</th><th>Jenis</th><th class="text-center">Aksi</th></tr></thead>
                        <tbody>
                        @foreach ($ranks as $rank)
                            <tr>
                                <td><code>{{ $rank->code }}</code></td>
                                <td>{{ $rank->name ?? '-' }}</td>
                                <td>{{ $rank->group_name ?? '-' }}</td>
                                <td><span class="badge {{ $rank->is_pppk ? 'bg-info' : 'bg-primary' }}">{{ $rank->is_pppk ? 'PPPK' : 'PNS' }}</span></td>
                                <td class="text-center text-nowrap">
                                    <button class="btn btn-sm btn-outline-osdmrb {{ $canManage ? '' : 'd-none' }}" title="Ubah"
                                            data-bs-toggle="modal" data-bs-target="#editRank{{ $rank->id }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ route('master.ranks.destroy', $rank) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus data ini?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger {{ $canManage ? '' : 'd-none' }}" title="Hapus"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    @if ($ranks->hasPages())
                        <div class="mt-3 d-flex justify-content-center">{{ $ranks->appends(['tab' => 'ranks'])->links() }}</div>
                    @endif
                </div>
            </div>
        </div>

        @foreach ($ranks as $rank)
            @include('master.partials.edit-modal', [
                'modalClass' => $canManage ? '' : 'd-none',
                'id' => 'editRank'.$rank->id,
                'title' => 'Ubah Golongan',
                'action' => route('master.ranks.update', $rank),
                'fields' => [
                    ['name' => 'code', 'label' => 'Kode', 'value' => $rank->code, 'required' => true],
                    ['name' => 'name', 'label' => 'Nama Pangkat', 'value' => $rank->name],
                    ['name' => 'group_name', 'label' => 'Golongan', 'value' => $rank->group_name],
                    ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number', 'value' => $rank->sort_order],
                    ['name' => 'is_pppk', 'label' => 'Golongan PPPK', 'type' => 'checkbox', 'checked' => $rank->is_pppk],
                ],
            ])
        @endforeach
    </div>

    <!-- ================= STATUSES ================= -->
    <div class="tab-pane fade {{ request('tab') === 'statuses' ? 'show active' : '' }}" id="statuses">
        <div class="row g-3">
            <div class="col-lg-4 form-col-start{{ $canManage ? '' : ' d-none' }}">
                <div class="chart-card">
                    <h5>Tambah Status Kepegawaian</h5>
                    <form method="POST" action="{{ route('master.statuses.store') }}">
                        @csrf
                        <div class="mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" required placeholder="CPNS"></div>
                        <div class="mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" required placeholder="Calon PNS"></div>
                        <button class="btn btn-osdmrb w-100">Simpan</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="table-card">
                    <h5>Daftar Status Kepegawaian</h5>
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Kode</th><th>Nama</th><th class="text-center">Aksi</th></tr></thead>
                        <tbody>
                        @foreach ($employmentStatuses as $status)
                            <tr>
                                <td><code>{{ $status->code }}</code></td>
                                <td>{{ $status->name }}</td>
                                <td class="text-center text-nowrap">
                                    <button class="btn btn-sm btn-outline-osdmrb {{ $canManage ? '' : 'd-none' }}" title="Ubah"
                                            data-bs-toggle="modal" data-bs-target="#editStatus{{ $status->id }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ route('master.statuses.destroy', $status) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus data ini?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger {{ $canManage ? '' : 'd-none' }}" title="Hapus"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    @if ($employmentStatuses->hasPages())
                        <div class="mt-3 d-flex justify-content-center">{{ $employmentStatuses->appends(['tab' => 'statuses'])->links() }}</div>
                    @endif
                </div>
            </div>
        </div>

        @foreach ($employmentStatuses as $status)
            @include('master.partials.edit-modal', [
                'modalClass' => $canManage ? '' : 'd-none',
                'id' => 'editStatus'.$status->id,
                'title' => 'Ubah Status Kepegawaian',
                'action' => route('master.statuses.update', $status),
                'fields' => [
                    ['name' => 'code', 'label' => 'Kode', 'value' => $status->code, 'required' => true],
                    ['name' => 'name', 'label' => 'Nama', 'value' => $status->name, 'required' => true],
                ],
            ])
        @endforeach
    </div>

    <!-- ================= JOB LEVELS ================= -->
    <div class="tab-pane fade {{ request('tab') === 'joblevels' ? 'show active' : '' }}" id="joblevels">
        <div class="row g-3">
            <div class="col-lg-4 form-col-start{{ $canManage ? '' : ' d-none' }}">
                <div class="chart-card">
                    <h5>Tambah Level Jabatan</h5>
                    <form method="POST" action="{{ route('master.job-levels.store') }}">
                        @csrf
                        <div class="mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" required placeholder="ADMINISTRATOR"></div>
                        <div class="mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" required placeholder="Administrator"></div>
                        <div class="mb-3"><label class="form-label">Urutan</label><input type="number" name="sort_order" class="form-control" value="0"></div>
                        <button class="btn btn-osdmrb w-100">Simpan</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="table-card">
                    <h5>Daftar Level Jabatan</h5>
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Kode</th><th>Nama</th><th class="text-center">Aksi</th></tr></thead>
                        <tbody>
                        @foreach ($jobLevels as $level)
                            <tr>
                                <td><code>{{ $level->code }}</code></td>
                                <td>{{ $level->name }}</td>
                                <td class="text-center text-nowrap">
                                    <button class="btn btn-sm btn-outline-osdmrb {{ $canManage ? '' : 'd-none' }}" title="Ubah"
                                            data-bs-toggle="modal" data-bs-target="#editJobLevel{{ $level->id }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ route('master.job-levels.destroy', $level) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus data ini?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger {{ $canManage ? '' : 'd-none' }}" title="Hapus"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    @if ($jobLevels->hasPages())
                        <div class="mt-3 d-flex justify-content-center">{{ $jobLevels->appends(['tab' => 'joblevels'])->links() }}</div>
                    @endif
                </div>
            </div>
        </div>

        @foreach ($jobLevels as $level)
            @include('master.partials.edit-modal', [
                'modalClass' => $canManage ? '' : 'd-none',
                'id' => 'editJobLevel'.$level->id,
                'title' => 'Ubah Level Jabatan',
                'action' => route('master.job-levels.update', $level),
                'fields' => [
                    ['name' => 'code', 'label' => 'Kode', 'value' => $level->code, 'required' => true],
                    ['name' => 'name', 'label' => 'Nama', 'value' => $level->name, 'required' => true],
                    ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number', 'value' => $level->sort_order],
                ],
            ])
        @endforeach
    </div>

    <!-- ================= POSITION TYPES ================= -->
    <div class="tab-pane fade {{ request('tab') === 'positiontypes' ? 'show active' : '' }}" id="positiontypes">
        <div class="row g-3">
            <div class="col-lg-4 form-col-start{{ $canManage ? '' : ' d-none' }}">
                <div class="chart-card">
                    <h5>Tambah Jenis Jabatan</h5>
                    <form method="POST" action="{{ route('master.position-types.store') }}">
                        @csrf
                        <div class="mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" required placeholder="STRUKTURAL"></div>
                        <div class="mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" required placeholder="Jabatan Struktural"></div>
                        <button class="btn btn-osdmrb w-100">Simpan</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="table-card">
                    <h5>Daftar Jenis Jabatan</h5>
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Kode</th><th>Nama</th><th class="text-center">Aksi</th></tr></thead>
                        <tbody>
                        @forelse ($positionTypes as $type)
                            <tr>
                                <td><code>{{ $type->code }}</code></td>
                                <td>{{ $type->name }}</td>
                                <td class="text-center text-nowrap">
                                    <button class="btn btn-sm btn-outline-osdmrb {{ $canManage ? '' : 'd-none' }}" title="Ubah"
                                            data-bs-toggle="modal" data-bs-target="#editPositionType{{ $type->id }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ route('master.position-types.destroy', $type) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus data ini?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger {{ $canManage ? '' : 'd-none' }}" title="Hapus"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">Belum ada jenis jabatan.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                    @if ($positionTypes->hasPages())
                        <div class="mt-3 d-flex justify-content-center">{{ $positionTypes->appends(['tab' => 'positiontypes'])->links() }}</div>
                    @endif
                </div>
            </div>
        </div>

        @foreach ($positionTypes as $type)
            @include('master.partials.edit-modal', [
                'modalClass' => $canManage ? '' : 'd-none',
                'id' => 'editPositionType'.$type->id,
                'title' => 'Ubah Jenis Jabatan',
                'action' => route('master.position-types.update', $type),
                'fields' => [
                    ['name' => 'code', 'label' => 'Kode', 'value' => $type->code, 'required' => true],
                    ['name' => 'name', 'label' => 'Nama', 'value' => $type->name, 'required' => true],
                ],
            ])
        @endforeach
    </div>

    <!-- ================= POSITIONS ================= -->
    <div class="tab-pane fade {{ request('tab') === 'positions' ? 'show active' : '' }}" id="positions">
        <div class="row g-3">
            <div class="col-lg-4 form-col-start{{ $canManage ? '' : ' d-none' }}">
                <div class="chart-card">
                    <h5>Tambah Jabatan</h5>
                    <form method="POST" action="{{ route('master.positions.store') }}">
                        @csrf
                        <div class="mb-3"><label class="form-label">Jenis Jabatan</label>
                            <select name="position_type_id" class="form-select" required>
                                @foreach ($allPositionTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3"><label class="form-label">Level Jabatan</label>
                            <select name="job_level_id" class="form-select">
                                <option value="">- Pilih -</option>
                                @foreach ($allJobLevels as $level)
                                    <option value="{{ $level->id }}">{{ $level->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" required placeholder="ANALIS-MADYA"></div>
                        <div class="mb-3"><label class="form-label">Nama Jabatan</label><input type="text" name="name" class="form-control" required placeholder="Analis Kepegawaian Ahli Madya"></div>
                        <div class="mb-3"><label class="form-label">Deskripsi</label><textarea name="description" rows="2" class="form-control"></textarea></div>
                        <button class="btn btn-osdmrb w-100">Simpan</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="table-card">
                    <h5>Daftar Jabatan</h5>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Nama Jabatan</th><th>Jenis</th><th>Level</th><th class="text-center">Aksi</th></tr></thead>
                            <tbody>
                            @foreach ($positions as $position)
                                <tr>
                                    <td>{{ $position->name }}</td>
                                    <td>{{ $position->positionType?->name }}</td>
                                    <td>{{ $position->jobLevel?->name ?? '-' }}</td>
                                    <td class="text-center text-nowrap">
                                        <button class="btn btn-sm btn-outline-osdmrb {{ $canManage ? '' : 'd-none' }}" title="Ubah"
                                                data-bs-toggle="modal" data-bs-target="#editPosition{{ $position->id }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="{{ route('master.positions.destroy', $position) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus data ini?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger {{ $canManage ? '' : 'd-none' }}" title="Hapus"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($positions->hasPages())
                        <div class="mt-3 d-flex justify-content-center">{{ $positions->appends(['tab' => 'positions'])->links() }}</div>
                    @endif
                </div>
            </div>
        </div>

        @foreach ($positions as $position)
            @include('master.partials.edit-modal', [
                'modalClass' => $canManage ? '' : 'd-none',
                'id' => 'editPosition'.$position->id,
                'title' => 'Ubah Jabatan',
                'action' => route('master.positions.update', $position),
                'fields' => [
                    ['name' => 'position_type_id', 'label' => 'Jenis Jabatan', 'type' => 'select', 'value' => $position->position_type_id, 'required' => true,
                     'options' => $allPositionTypes->map(fn ($t) => ['value' => $t->id, 'label' => $t->name])->all()],
                    ['name' => 'job_level_id', 'label' => 'Level Jabatan', 'type' => 'select', 'value' => $position->job_level_id,
                     'options' => $allJobLevels->map(fn ($l) => ['value' => $l->id, 'label' => $l->name])->all(), 'placeholder' => '- Pilih -'],
                    ['name' => 'code', 'label' => 'Kode', 'value' => $position->code, 'required' => true],
                    ['name' => 'name', 'label' => 'Nama Jabatan', 'value' => $position->name, 'required' => true],
                    ['name' => 'description', 'label' => 'Deskripsi', 'type' => 'textarea', 'value' => $position->description],
                ],
            ])
        @endforeach
    </div>

    <!-- ================= ARCHIVE CATEGORIES ================= -->
    <div class="tab-pane fade {{ request('tab') === 'arsip' ? 'show active' : '' }}" id="arsip">
        <div class="row g-3">
            <div class="col-lg-4 form-col-start{{ $canManage ? '' : ' d-none' }}">
                <div class="chart-card">
                    <h5>Tambah Klasifikasi Arsip</h5>
                    <form method="POST" action="{{ route('master.archive-categories.store') }}">
                        @csrf
                        <div class="mb-3"><label class="form-label">Kode Klasifikasi</label>
                            <input type="text" name="code" class="form-control" required placeholder="mis. 830"></div>
                        <div class="mb-3"><label class="form-label">Nama Klasifikasi</label>
                            <input type="text" name="name" class="form-control" required placeholder="mis. Kepegawaian"></div>
                        <div class="mb-3"><label class="form-label">Deskripsi</label>
                            <textarea name="description" rows="2" class="form-control"></textarea></div>
                        <button class="btn btn-osdmrb w-100">Simpan</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="table-card">
                    <h5>Daftar Klasifikasi Arsip</h5>
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Kode</th><th>Nama</th><th>Arsip</th><th class="text-center">Aksi</th></tr></thead>
                        <tbody>
                        @foreach ($archiveCategories as $category)
                            <tr>
                                <td><code>{{ $category->code }}</code></td>
                                <td>{{ $category->name }}
                                    @if ($category->description)<br><small class="text-muted">{{ $category->description }}</small>@endif
                                </td>
                                <td>{{ $category->archives_count }}</td>
                                <td class="text-center text-nowrap">
                                    <button class="btn btn-sm btn-outline-osdmrb {{ $canManage ? '' : 'd-none' }}" title="Ubah"
                                            data-bs-toggle="modal" data-bs-target="#editArchiveCategory{{ $category->id }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ route('master.archive-categories.destroy', $category) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus klasifikasi ini?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger {{ $canManage ? '' : 'd-none' }}" title="Hapus"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    @if ($archiveCategories->hasPages())
                        <div class="mt-3 d-flex justify-content-center">{{ $archiveCategories->appends(['tab' => 'arsip'])->links() }}</div>
                    @endif
                </div>
            </div>
        </div>

        @foreach ($archiveCategories as $category)
            @include('master.partials.edit-modal', [
                'modalClass' => $canManage ? '' : 'd-none',
                'id' => 'editArchiveCategory'.$category->id,
                'title' => 'Ubah Klasifikasi Arsip',
                'action' => route('master.archive-categories.update', $category),
                'fields' => [
                    ['name' => 'code', 'label' => 'Kode Klasifikasi', 'value' => $category->code, 'required' => true],
                    ['name' => 'name', 'label' => 'Nama Klasifikasi', 'value' => $category->name, 'required' => true],
                    ['name' => 'description', 'label' => 'Deskripsi', 'type' => 'textarea', 'value' => $category->description],
                ],
            ])
        @endforeach
    </div>

</div>

@endsection

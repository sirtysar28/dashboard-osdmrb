@extends('layouts.app')

@section('page_title', 'Data Pegawai ASN')
@section('page_subtitle', 'Manajemen data pegawai ASN instansi — PNS, CPNS & PPPK')

@section('content')

{{-- info filter aktif dari stat-card dashboard --}}
@if (($filters['jenis'] ?? '') || ($filters['pensiun'] ?? '') || (($filters['status'] ?? '') === 'pppk') || in_array(($filters['naik'] ?? ''), ['tahun_ini', '1', '4'], true))
    <div class="alert alert-info d-flex align-items-center justify-content-between flex-wrap gap-2 py-2 small">
        <div>
            <i class="bi bi-funnel me-1"></i> Filter aktif:
            <strong>
                @php
                    echo implode(', ', array_filter([
                        ($filters['jenis'] ?? '') === 'struktural' ? 'Jabatan Struktural' : null,
                        ($filters['jenis'] ?? '') === 'fungsional' ? 'Jabatan Fungsional' : null,
                        ($filters['status'] ?? '') === 'pppk' ? 'Pegawai PPPK' : null,
                        ($filters['pensiun'] ?? '') === '1' ? 'Akan pensiun tahun ini' : null,
                        ($filters['pensiun'] ?? '') === '2' ? 'Pensiun ≤ 2 tahun' : null,
                        ($filters['naik'] ?? '') === 'tahun_ini' ? 'Kenaikan jabatan/pangkat tahun ini' : null,
                        ($filters['naik'] ?? '') === '1' ? 'Kenaikan jabatan/pangkat ≤ 1 tahun' : null,
                        ($filters['naik'] ?? '') === '4' ? 'Kenaikan jabatan/pangkat ≤ 4 tahun' : null,
                    ]));
                @endphp
            </strong>
        </div>
        <a href="{{ route('employees.index') }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-x-lg"></i> Hapus Filter
        </a>
    </div>
@endif

<div class="filter-card mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-lg-3 col-md-6">
            <label>Pencarian</label>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Nama / NIP..."
                   value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <label>Status Kepegawaian</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">Semua Status</option>
                <option value="pppk" {{ ($filters['status'] ?? '') === 'pppk' ? 'selected' : '' }}>Semua PPPK</option>
                @foreach ($statusList as $status)
                    <option value="{{ $status->id }}" {{ ($filters['status'] ?? '') == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-3 col-md-4 col-6">
            <label>Unit Kerja</label>
            <select name="unit" class="form-select form-select-sm">
                <option value="">Semua Unit</option>
                @foreach ($unitList as $unit)
                    <option value="{{ $unit->id }}" {{ ($filters['unit'] ?? '') == $unit->id ? 'selected' : '' }}>{{ $unit->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <label>Jenis Jabatan</label>
            <select name="jenis" class="form-select form-select-sm">
                <option value="">Semua</option>
                <option value="struktural" {{ ($filters['jenis'] ?? '') === 'struktural' ? 'selected' : '' }}>Struktural</option>
                <option value="fungsional" {{ ($filters['jenis'] ?? '') === 'fungsional' ? 'selected' : '' }}>Fungsional</option>
            </select>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <label>Masa Pensiun</label>
            <select name="pensiun" class="form-select form-select-sm">
                <option value="">Semua</option>
                <option value="1" {{ ($filters['pensiun'] ?? '') === '1' ? 'selected' : '' }}>Tahun ini</option>
                <option value="2" {{ ($filters['pensiun'] ?? '') === '2' ? 'selected' : '' }}>&le; 2 tahun</option>
            </select>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <label>Kenaikan Jabatan</label>
            <select name="naik" class="form-select form-select-sm">
                <option value="">Semua</option>
                <option value="tahun_ini" {{ ($filters['naik'] ?? '') === 'tahun_ini' ? 'selected' : '' }}>Tahun ini</option>
                <option value="1" {{ ($filters['naik'] ?? '') === '1' ? 'selected' : '' }}>Kurang dari = 1 tahun</option>
                <option value="4" {{ ($filters['naik'] ?? '') === '4' ? 'selected' : '' }}>Kurang dari = 4 tahun</option>
            </select>
        </div>
        <div class="col-lg-12 d-flex gap-2 justify-content-end">
            <div class="form-check ms-2 mt-2">
                <input class="form-check-input" type="checkbox" name="inactive" value="1" id="inactive"
                       {{ ($filters['inactive'] ?? '') ? 'checked' : '' }}>
                <label class="form-check-label small" for="inactive">Non-aktif</label>
            </div>
            <a href="{{ route('employees.non-asn') }}" class="btn btn-sm btn-outline-secondary" title="Pegawai Non ASN">
                <i class="bi bi-person-badge"></i>
            </a>
            <button class="btn btn-osdmrb btn-sm flex-fill"><i class="bi bi-search"></i></button>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="card-header-custom">
        <h5 class="mb-0">Daftar Pegawai ({{ $employees->total() }})</h5>

        <div class="d-flex gap-2 flex-wrap">
            <div class="dropdown export-btn">
                <button class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-download"></i> Export
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" data-no-loader href="{{ route('employees.export', ['format' => 'xlsx'] + $filters) }}">
                            <i class="bi bi-file-earmark-excel text-success"></i> Excel (.xlsx)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" data-no-loader href="{{ route('employees.export', ['format' => 'pdf'] + $filters) }}">
                            <i class="bi bi-file-earmark-pdf text-danger"></i> PDF
                        </a>
                    </li>
                </ul>
            </div>

            <a href="{{ route('employees.import') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-upload"></i> Import Massal
            </a>

            <a href="{{ route('employees.create') }}" class="btn btn-sm btn-osdmrb">
                <i class="bi bi-plus-lg"></i> Tambah Pegawai
            </a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nama</th>
                    <th>NIP</th>
                    <th>Jabatan</th>
                    <th>Golongan</th>
                    <th>Status</th>
                    <th>Unit</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($employees as $employee)
                    <tr>
                        <td>{{ $employees->firstItem() + $loop->iteration - 1 }}</td>
                        <td class="fw-semibold">{{ $employee->name }}
                            @unless($employee->is_active)<span class="badge bg-secondary">Non-aktif</span>@endunless
                        </td>
                        <td>{{ $employee->nip }}</td>
                        <td>{{ $employee->position_name ?? '-' }}</td>
                        <td>{{ $employee->rank?->code ?? '-' }}</td>
                        <td><span class="badge {{ $employee->employmentStatus?->code === 'PNS' ? 'bg-primary' : 'bg-info' }}">{{ $employee->employmentStatus?->name ?? '-' }}</span></td>
                        <td>{{ $employee->unit?->name ?? '-' }}</td>
                        <td class="text-center text-nowrap">
                            <a href="{{ route('employees.show', $employee) }}" class="btn btn-sm btn-outline-osdmrb" title="Detail"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('employees.edit', $employee) }}" class="btn btn-sm btn-outline-secondary" title="Ubah"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route('employees.destroy', $employee) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('Hapus data pegawai ini?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data pegawai.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $employees->links() }}</div>
</div>

@endsection

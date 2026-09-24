@extends('layouts.app')

@section('page_title', 'Direktori Pegawai')
@section('page_subtitle', 'Cari & lihat data pegawai instansi (khusus lihat / view only)')

@section('content')

@php($statusFilter = collect($filters['status'] ?? []))
@php($unitFilter = collect($filters['unit'] ?? []))

<div class="filter-card mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-lg-4 col-md-6">
            <label>Pencarian</label>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Nama / NIP..."
                   value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <label>Jenis Pegawai</label>
            <select name="jenis" class="form-select form-select-sm">
                <option value="">Semua</option>
                <option value="asn" {{ ($filters['jenis'] ?? '') === 'asn' ? 'selected' : '' }}>ASN</option>
                <option value="non_asn" {{ ($filters['jenis'] ?? '') === 'non_asn' ? 'selected' : '' }}>Non ASN</option>
            </select>
        </div>
        <div class="col-lg-3 col-md-6">
            <label>Status Kepegawaian <small class="text-muted">(bisa pilih &gt;1)</small></label>
            <x-multi-select name="status" placeholder="Semua Status"
                            :options="$statusList->mapWithKeys(fn ($s) => [$s->id => $s->name])->all()"
                            :selected="$statusFilter" />
        </div>
        <div class="col-lg-3 col-md-6">
            <label>Unit Kerja <small class="text-muted">(bisa pilih &gt;1)</small></label>
            <x-multi-select name="unit" placeholder="Semua Unit Kerja"
                            :options="$unitList->mapWithKeys(fn ($u) => [$u->id => $u->name])->all()"
                            :selected="$unitFilter" />
        </div>
        <div class="col-lg-12 d-flex justify-content-end gap-2">
            <a href="{{ route('employees.directory') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-counterclockwise"></i> Reset
            </a>
            <button class="btn btn-osdmrb btn-sm px-4"><i class="bi bi-search"></i> Cari</button>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="card-header-custom">
        <h5 class="mb-0">Daftar Pegawai ({{ $employees->total() }})</h5>
        <span class="badge bg-light text-dark border"><i class="bi bi-eye"></i> view only</span>
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
                        <td class="fw-semibold">{{ $employee->name }}</td>
                        <td>{{ $employee->nip }}</td>
                        <td>{{ $employee->position_name ?? '-' }}</td>
                        <td>{{ $employee->rank?->code ?? '-' }}</td>
                        <td>
                            @if ($employee->employee_type === \App\Models\Employee::TYPE_NON_ASN)
                                <span class="badge bg-secondary">{{ $employee->category ?? 'Non ASN' }}</span>
                            @else
                                <span class="badge {{ $employee->is_retired ? 'bg-secondary' : (in_array($employee->employmentStatus?->code, ['ASN', 'PNS']) ? 'bg-primary' : 'bg-info') }}">{{ $employee->display_status }}</span>
                            @endif
                        </td>
                        <td>{{ $employee->unit?->name ?? '-' }}</td>
                        <td class="text-center text-nowrap">
                            <a href="{{ route('employees.show', $employee) }}" class="btn btn-sm btn-outline-osdmrb" title="Lihat Detail (view only)">
                                <i class="bi bi-eye"></i>
                            </a>
                            {{-- Catatan rapat 23 Sept 2026: unduh CV hanya admin / pemilik profil --}}
                            @if (auth()->user()->isPrivileged())
                                <a href="{{ route('employees.cv', $employee) }}" class="btn btn-sm btn-outline-secondary" title="Unduh CV (PDF)" data-no-loader>
                                    <i class="bi bi-file-earmark-person"></i>
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada pegawai yang cocok dengan pencarian.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $employees->links() }}</div>
</div>

@endsection

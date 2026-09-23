@extends('layouts.app')

@section('page_title', 'Pegawai Non ASN')
@section('page_subtitle', 'Data pegawai non ASN — pramubakti, security, cleaning service, dll')

@section('content')

@php
    $categoryFilter = collect($filters['category'] ?? [])->filter()->values();
    if ($categoryFilter->isEmpty() && is_string($filters['category'] ?? null) && $filters['category'] !== '') {
        $categoryFilter = collect([$filters['category']]);
    }
@endphp

{{-- ================= FILTER ================= --}}
<div class="filter-card mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-lg-4 col-md-6">
            <label>Pencarian</label>
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Nama / ID pegawai..." value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="col-lg-3 col-md-4 col-6">
            <label>Kategori <small class="text-muted">(bisa pilih &gt;1)</small></label>
            <x-multi-select name="category" placeholder="Semua Kategori"
                            :options="collect($categories)->mapWithKeys(fn ($c) => [$c => $c])->all()"
                            :selected="$categoryFilter" />
        </div>
        <div class="col-lg-5 col-md-12 d-flex gap-2 justify-content-end">
            <div class="form-check ms-2 mt-2">
                <input class="form-check-input" type="checkbox" name="inactive" value="1" id="inactive"
                       {{ ($filters['inactive'] ?? '') ? 'checked' : '' }}>
                <label class="form-check-label small" for="inactive">Non-aktif</label>
            </div>
            <a href="{{ route('employees.non-asn') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-counterclockwise"></i>
            </a>
            <button class="btn btn-osdmrb btn-sm px-3"><i class="bi bi-search"></i></button>
        </div>
    </form>
</div>

{{-- ================= TABEL ================= --}}
<div class="table-card">
    <div class="card-header-custom">
        <h5 class="mb-0">Daftar Pegawai Non ASN ({{ $employees->total() }})</h5>

        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('employees.non-asn.create') }}" class="btn btn-sm btn-osdmrb">
                <i class="bi bi-plus-lg"></i> Tambah
            </a>
            <a href="{{ route('employees.non-asn.import') }}" class="btn btn-sm btn-outline-success">
                <i class="bi bi-file-earmark-arrow-up"></i> Import Excel
            </a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nama</th>
                    <th>ID Pegawai</th>
                    <th>Kategori</th>
                    <th>Jabatan / Penempatan</th>
                    <th>Status</th>
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
                        <td>
                            <span class="badge" style="background:rgba(232,161,60,.18);color:#a1721f">
                                {{ $employee->category ?? 'Non ASN' }}
                            </span>
                        </td>
                        <td>{{ $employee->position_name ?? '-' }}</td>
                        <td><small class="text-muted">{{ $employee->employee_type_label }}</small></td>
                        <td class="text-center text-nowrap">
                            <a href="{{ route('employees.show', $employee) }}" class="btn btn-sm btn-outline-osdmrb" title="Detail"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('employees.non-asn.edit', $employee) }}" class="btn btn-sm btn-outline-secondary" title="Ubah"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route('employees.destroy', $employee) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('Hapus data pegawai ini?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">
                        Tidak ada data pegawai non ASN.<br>
                        <small>Klik <strong>Import Excel</strong> untuk mengunggah berkas daftar
                        Security / Cleaning Service / Pramubakti.</small>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $employees->links() }}</div>
</div>

@endsection

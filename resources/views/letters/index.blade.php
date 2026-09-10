@extends('layouts.app')

@section('page_title', 'Layanan Persuratan')
@section('page_subtitle', 'Pengajuan, verifikasi & penerbitan surat kepegawaian')

@section('content')

<div class="filter-card mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-lg-4 col-md-6">
            <label>Pencarian</label>
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Nomor / perihal / nama pegawai..." value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="col-lg-3 col-md-3 col-6">
            <label>Jenis Surat</label>
            <select name="type" class="form-select form-select-sm">
                <option value="">Semua Jenis</option>
                @foreach ($letterTypes as $type)
                    <option value="{{ $type->id }}" {{ ($filters['type'] ?? '') == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-3 col-md-3 col-6">
            <label>Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">Semua Status</option>
                @foreach ([
                    'PENDING' => 'Menunggu Verifikasi',
                    'VERIFIED' => 'Terverifikasi',
                    'APPROVED' => 'Disetujui',
                    'REJECTED' => 'Ditolak',
                ] as $value => $label)
                    <option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-12">
            <button class="btn btn-osdmrb btn-sm w-100"><i class="bi bi-search"></i> Cari</button>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="card-header-custom">
        <h5 class="mb-0">Daftar Pengajuan Surat ({{ $letters->total() }})</h5>

        <div class="d-flex gap-2 flex-wrap export-btn">
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-download"></i> Export
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" data-no-loader href="{{ route('letters.export', ['format' => 'xlsx'] + $filters) }}">
                            <i class="bi bi-file-earmark-excel text-success"></i> Excel (.xlsx)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" data-no-loader href="{{ route('letters.export', ['format' => 'pdf'] + $filters) }}">
                            <i class="bi bi-file-earmark-pdf text-danger"></i> PDF
                        </a>
                    </li>
                </ul>
            </div>

            <a href="{{ route('letters.create') }}" class="btn btn-sm btn-osdmrb">
                <i class="bi bi-plus-lg"></i> Ajukan Surat
            </a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Nomor Surat</th>
                    <th>Pegawai</th>
                    <th>Jenis Surat</th>
                    <th>Perihal</th>
                    <th>Tanggal</th>
                    <th>Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($letters as $letter)
                    <tr>
                        <td><code>{{ $letter->number ?? '(belum terbit)' }}</code></td>
                        <td class="fw-semibold">{{ $letter->employee?->name }}</td>
                        <td>{{ $letter->letterType?->name }}</td>
                        <td>{{ Str::limit($letter->subject, 45) }}</td>
                        <td>{{ $letter->created_at->translatedFormat('d/m/Y') }}</td>
                        <td><span class="badge bg-{{ $letter->status_badge }}">{{ $letter->status_label }}</span></td>
                        <td class="text-center text-nowrap">
                            <a href="{{ route('letters.show', $letter) }}" class="btn btn-sm btn-outline-osdmrb" title="Detail"><i class="bi bi-eye"></i></a>
                            @if ($letter->status === \App\Models\Letter::STATUS_APPROVED)
                                <a href="{{ route('letters.print', $letter) }}" target="_blank" class="btn btn-sm btn-outline-success" title="Cetak PDF"><i class="bi bi-printer"></i></a>
                            @endif
                            @if ($letter->status === \App\Models\Letter::STATUS_PENDING && (auth()->user()->employee_id === $letter->employee_id || auth()->user()->isPrivileged()))
                                <form action="{{ route('letters.destroy', $letter) }}" method="POST" class="d-inline" onsubmit="return confirm('Batalkan pengajuan ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Batalkan"><i class="bi bi-x"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada pengajuan surat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $letters->links() }}</div>
</div>

@endsection

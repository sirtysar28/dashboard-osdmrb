@extends('layouts.app')

@section('page_title', 'Pengajuan Cuti')
@section('page_subtitle', 'Pengajuan, verifikasi & persetujuan cuti pegawai')

@section('content')

@php
    /* Catatan Masukan 30 Sept 2026: fitur pencarian daftar cuti hanya untuk
       Admin Bagian & Super Admin — akun pegawai tidak perlu pencarian karena
       daftar cuti pegawai lain tidak dapat dilihat akun pegawai lain. */
    $canSearch = auth()->user()->isAdmin() || auth()->user()->isSuperAdmin();
@endphp

{{-- ================= FILTER (Catatan 21 Okt 2026) =================
     + filter rentang tanggal & unit kerja untuk melihat data cuti. --}}
<div class="filter-card mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-lg-2 col-md-4 col-6">
            <label>Mulai Tanggal</label>
            <input type="date" name="date_from" class="form-control form-control-sm"
                   value="{{ $filters['date_from'] ?? '' }}">
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <label>s/d</label>
            <input type="date" name="date_to" class="form-control form-control-sm"
                   value="{{ $filters['date_to'] ?? '' }}">
        </div>
        <div class="col-lg-3 col-md-4 col-12">
            <label>Unit Kerja <small class="text-muted">(bisa pilih &gt;1)</small></label>
            <x-multi-select name="unit" placeholder="Semua Unit"
                            :options="$unitOptions->pluck('name', 'id')"
                            :selected="collect($filters['unit'] ?? [])->all()" />
        </div>
        <div class="col-lg-2 col-md-6 col-12">
            <label>Jenis Cuti <small class="text-muted">(bisa pilih &gt;1)</small></label>
            <x-multi-select name="type" placeholder="Semua Jenis"
                            :options="\App\Models\LeaveRequest::typeOptions()"
                            :selected="collect($filters['type'] ?? [])->all()" />
        </div>
        <div class="col-lg-2 col-md-6 col-12">
            <label>Status <small class="text-muted">(bisa pilih &gt;1)</small></label>
            <x-multi-select name="status" placeholder="Semua Status"
                            :options="[
                                'PENDING' => 'Menunggu Verifikasi',
                                'VERIFIED' => 'Terverifikasi',
                                'APPROVED' => 'Disetujui',
                                'REJECTED' => 'Ditolak',
                            ]"
                            :selected="collect($filters['status'] ?? [])->all()" />
        </div>
        @if ($canSearch)
            <div class="col-lg-3 col-md-12">
                <label>Pencarian</label>
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Nama pegawai / alasan..." value="{{ $filters['search'] ?? '' }}">
            </div>
        @endif
        <div class="col-lg-3 col-md-12 d-flex gap-2 justify-content-end">
            <a href="{{ route('leaves.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-counterclockwise"></i> Reset
            </a>
            <button class="btn btn-osdmrb btn-sm px-4"><i class="bi bi-search"></i> Cari</button>
        </div>
    </form>
</div>

{{-- ================= RINGKASAN MONITORING (mengikuti filter) ================= --}}
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-stack"></i></div>
            <div>
                <span>Total Pengajuan</span>
                <h2>{{ number_format($stats['total']) }}</h2>
                <small>sesuai filter aktif</small>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon success"><i class="bi bi-check2-circle"></i></div>
            <div>
                <span>Disetujui</span>
                <h2>{{ number_format($stats['approved']) }}</h2>
                <small>{{ number_format($stats['days_taken']) }} hari cuti telah diambil</small>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon accent"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <span>Menunggu Verifikasi</span>
                <h2>{{ number_format($stats['pending']) }}</h2>
                <small>perlu ditindaklanjuti</small>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon danger"><i class="bi bi-x-circle"></i></div>
            <div>
                <span>Ditolak</span>
                <h2>{{ number_format($stats['rejected']) }}</h2>
                <small>pengajuan tidak disetujui</small>
            </div>
        </div>
    </div>
</div>

<div class="table-card">
    <div class="card-header-custom flex-wrap gap-2">
        <h5 class="mb-0">Daftar Pengajuan Cuti ({{ $leaves->total() }})</h5>

        <div class="d-flex gap-2 flex-wrap">
            @if (auth()->user()->isPrivileged())
                <a href="{{ route('leaves.rekap', array_filter($filters, fn ($v) => $v !== null && $v !== '' && $v !== [])) }}"
                   class="btn btn-sm btn-outline-osdmrb">
                    <i class="bi bi-clipboard2-data"></i> Rekap Cuti Pegawai
                </a>
            @endif
            <a href="{{ route('leaves.create') }}" class="btn btn-sm btn-osdmrb">
                <i class="bi bi-plus-lg"></i> Ajukan Cuti
            </a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Pegawai</th>
                    <th>Jenis Cuti</th>
                    <th>Rentang Cuti</th>
                    <th>Jumlah Hari</th>
                    <th>Diajukan</th>
                    <th>Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($leaves as $leave)
                    <tr>
                        <td class="fw-semibold">{{ $leave->employee?->name }}</td>
                        <td>{{ $leave->type_label }}</td>
                        <td class="text-nowrap">
                            {{ $leave->start_date?->translatedFormat('d/m/Y') }} s.d. {{ $leave->end_date?->translatedFormat('d/m/Y') }}
                        </td>
                        <td>{{ $leave->total_days }} hari</td>
                        <td>{{ $leave->created_at->translatedFormat('d/m/Y') }}</td>
                        <td><span class="badge bg-{{ $leave->status_badge }}">{{ $leave->status_label }}</span></td>
                        <td class="text-center text-nowrap">
                            <a href="{{ route('leaves.show', $leave) }}" class="btn btn-sm btn-outline-osdmrb" title="Detail"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('leaves.print', $leave) }}" class="btn btn-sm btn-outline-danger" title="Cetak / Unduh PDF (format baku BKN)" data-no-loader target="_blank"><i class="bi bi-printer"></i></a>
                            @if ($leave->status === \App\Models\LeaveRequest::STATUS_PENDING && (auth()->user()->employee_id === $leave->employee_id || auth()->user()->isPrivileged()))
                                <form action="{{ route('leaves.destroy', $leave) }}" method="POST" class="d-inline" onsubmit="return confirm('Batalkan pengajuan cuti ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Batalkan"><i class="bi bi-x"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada pengajuan cuti.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $leaves->links() }}</div>
</div>

@endsection

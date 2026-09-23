@extends('layouts.app')

@section('page_title', 'Pengajuan Cuti')
@section('page_subtitle', 'Pengajuan, verifikasi & persetujuan cuti pegawai')

@section('content')

<div class="filter-card mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-lg-4 col-md-6">
            <label>Pencarian</label>
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Nama pegawai / alasan..." value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="col-lg-3 col-md-3 col-6">
            <label>Jenis Cuti <small class="text-muted">(bisa pilih &gt;1)</small></label>
            <x-multi-select name="type" placeholder="Semua Jenis"
                            :options="\App\Models\LeaveRequest::typeOptions()"
                            :selected="collect($filters['type'] ?? [])->all()" />
        </div>
        <div class="col-lg-3 col-md-3 col-6">
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
        <div class="col-lg-2 col-md-12">
            <button class="btn btn-osdmrb btn-sm w-100"><i class="bi bi-search"></i> Cari</button>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="card-header-custom">
        <h5 class="mb-0">Daftar Pengajuan Cuti ({{ $leaves->total() }})</h5>

        <a href="{{ route('leaves.create') }}" class="btn btn-sm btn-osdmrb">
            <i class="bi bi-plus-lg"></i> Ajukan Cuti
        </a>
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
                            <a href="{{ route('leaves.print', $leave) }}" class="btn btn-sm btn-outline-danger" title="Cetak / Unduh PDF" data-no-loader target="_blank"><i class="bi bi-printer"></i></a>
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

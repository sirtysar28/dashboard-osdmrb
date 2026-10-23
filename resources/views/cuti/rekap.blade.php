@extends('layouts.app')

@section('page_title', 'Rekap Cuti Pegawai')
@section('page_subtitle', 'Riwayat & jumlah cuti yang telah diambil per pegawai')

@section('content')

{{-- ================= FILTER: rentang tanggal & unit kerja (21 Okt 2026) ================= --}}
<div class="filter-card mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-lg-3 col-md-4 col-6">
            <label>Cuti Mulai Tanggal</label>
            <input type="date" name="date_from" class="form-control form-control-sm"
                   value="{{ $filters['date_from'] ?? '' }}">
        </div>
        <div class="col-lg-3 col-md-4 col-6">
            <label>s/d</label>
            <input type="date" name="date_to" class="form-control form-control-sm"
                   value="{{ $filters['date_to'] ?? '' }}">
        </div>
        <div class="col-lg-4 col-md-12 col-12">
            <label>Unit Kerja <small class="text-muted">(bisa pilih &gt;1)</small></label>
            <x-multi-select name="unit" placeholder="Semua Unit"
                            :options="$unitOptions->pluck('name', 'id')"
                            :selected="collect($filters['unit'] ?? [])->all()" />
        </div>
        <div class="col-lg-2 col-md-6 col-8">
            <label>Pencarian</label>
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Nama / NIP pegawai..." value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="col-lg-12 col-md-6 col-4 d-flex gap-2 justify-content-end">
            <a href="{{ route('leaves.rekap') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-counterclockwise"></i> Reset
            </a>
            <a href="{{ route('leaves.rekap.export', array_filter($filters, fn ($v) => $v !== null && $v !== '' && $v !== [])) }}"
               class="btn btn-outline-osdmrb btn-sm" data-no-loader>
                <i class="bi bi-file-earmark-excel"></i> Ekspor Excel
            </a>
            <button class="btn btn-osdmrb btn-sm px-4"><i class="bi bi-search"></i> Terapkan</button>
        </div>
    </form>
</div>

{{-- ================= RINGKASAN ================= --}}
<div class="row g-3 mb-4">
    <div class="col-xl-4 col-md-4">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
            <div>
                <span>Pegawai Mengambil Cuti</span>
                <h2>{{ number_format($grandTotal['employees']) }}</h2>
                <small>cuti disetujui sesuai filter</small>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4">
        <div class="stat-card">
            <div class="stat-icon accent"><i class="bi bi-stack"></i></div>
            <div>
                <span>Total Pengajuan Disetujui</span>
                <h2>{{ number_format($grandTotal['requests']) }}</h2>
                <small>surat permohonan cuti</small>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4">
        <div class="stat-card">
            <div class="stat-icon success"><i class="bi bi-calendar2-check"></i></div>
            <div>
                <span>Total Hari Cuti Diambil</span>
                <h2>{{ number_format($grandTotal['days']) }}</h2>
                <small>akumulasi hari</small>
            </div>
        </div>
    </div>
</div>

{{-- ================= TABEL REKAP ================= --}}
<div class="table-card">
    <div class="card-header-custom">
        <h5 class="mb-0"><i class="bi bi-clipboard2-data me-2"></i>Rekap Cuti per Pegawai</h5>
        <span class="badge bg-primary-subtle text-primary">{{ $items->total() }} pegawai</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0 text-nowrap">
            <thead>
                <tr>
                    <th class="text-center" style="width:44px">No</th>
                    <th>Pegawai</th>
                    <th>Unit Kerja</th>
                    <th>Status</th>
                    <th class="text-center">Tahunan</th>
                    <th class="text-center">Besar</th>
                    <th class="text-center">Sakit</th>
                    <th class="text-center">Melahirkan</th>
                    <th class="text-center">Alasan Penting</th>
                    <th class="text-center">Bersama</th>
                    <th class="text-center">CLTN</th>
                    <th class="text-center">Total Hari</th>
                </tr>
            </thead>
            <tbody>
                @php($types = ['tahunan', 'besar', 'sakit', 'melahirkan', 'alasan_penting', 'bersama', 'cltn'])
                @forelse ($items as $row)
                    <tr>
                        <td class="text-center text-muted">{{ $items->firstItem() + $loop->iteration - 1 }}</td>
                        <td>
                            <a href="{{ route('employees.show', $row['id']) }}" class="fw-semibold text-decoration-none d-block">{{ $row['name'] }}</a>
                            <small class="text-muted">{{ $row['nip'] }}</small>
                        </td>
                        <td>{{ $row['unit'] }}</td>
                        <td><small>{{ $row['status'] }}</small></td>
                        @foreach ($types as $type)
                            <td class="text-center">{{ $row['days'][$type] ?? 0 }}</td>
                        @endforeach
                        <td class="text-center fw-bold">{{ number_format($row['total_days']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="text-center text-muted py-4">Belum ada pegawai yang mengambil cuti sesuai filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $items->links() }}</div>
</div>

<div class="alert alert-info small mt-3" role="alert">
    <i class="bi bi-info-circle me-1"></i>
    Rekap hanya menghitung pengajuan cuti berstatus <strong>Disetujui</strong> (cuti yang telah diambil).
    Rentang tanggal mencakup pengajuan yang <em>beririsan</em> dengan periode terpilih.
    <a href="{{ route('leaves.index') }}" class="fw-semibold">Kembali ke daftar pengajuan cuti &raquo;</a>
</div>

@endsection

@extends('layouts.app')

@section('page_title', 'Data Renang Pegawai')
@section('page_subtitle', 'Rekapitulasi kemampuan renang pegawai ASN per unit kerja')

@section('content')

<?php $canSeeEmployee = auth()->user()->isPrivileged(); ?>

{{-- ================= FILTER PENCARIAN =================
     Catatan Masukan 19 Nov 2026: filter Unit Kerja & Kemampuan Berenang
     dengan tombol Cari dan Reset. --}}
<div class="filter-card mb-4">
    <form method="GET" action="{{ route('renang.index') }}" class="row g-2 align-items-end">
        <div class="col-lg-5 col-md-6 col-12">
            <label>Unit Kerja</label>
            <select name="unit" class="form-select form-select-sm">
                <option value="">Semua Unit Kerja</option>
                @foreach ($unitOptions as $unit)
                    <option value="{{ $unit->id }}" {{ (string) ($filters['unit'] ?? '') === (string) $unit->id ? 'selected' : '' }}>
                        {{ $unit->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-4 col-md-6 col-12">
            <label>Kemampuan Berenang</label>
            <select name="kemampuan" class="form-select form-select-sm">
                <option value="">Semua Kemampuan</option>
                <option value="lulus" {{ ($filters['kemampuan'] ?? '') === 'lulus' ? 'selected' : '' }}>Lulus Berenang</option>
                <option value="belum" {{ ($filters['kemampuan'] ?? '') === 'belum' ? 'selected' : '' }}>Belum Lulus</option>
                <option value="kosong" {{ ($filters['kemampuan'] ?? '') === 'kosong' ? 'selected' : '' }}>Belum Diisi</option>
            </select>
        </div>
        <div class="col-lg-3 col-md-12 d-flex gap-2 justify-content-end">
            <a href="{{ route('renang.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-counterclockwise"></i> Reset
            </a>
            <button class="btn btn-osdmrb btn-sm px-4"><i class="bi bi-search"></i> Cari</button>
        </div>
    </form>
</div>

{{-- ================= CARD RINGKASAN ================= --}}
<div class="row g-3 mb-4">
    <div class="col-lg-4 col-md-4 col-6">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
            <div>
                <span>Total Pegawai</span>
                <h2>{{ number_format($stats['total']) }}</h2>
                <small>pegawai ASN aktif</small>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-4 col-6">
        <div class="stat-card">
            <div class="stat-icon success"><i class="bi bi-trophy"></i></div>
            <div>
                <span>Lulus Berenang</span>
                <h2>{{ number_format($stats['lulus']) }}</h2>
                <small>{{ $stats['total'] > 0 ? round($stats['lulus'] / $stats['total'] * 100) : 0 }}% dari total pegawai</small>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-4 col-6">
        <div class="stat-card">
            <div class="stat-icon danger"><i class="bi bi-water"></i></div>
            <div>
                <span>Belum Lulus</span>
                <h2>{{ number_format($stats['belum']) }}</h2>
                <small>{{ $stats['kosong'] > 0 ? number_format($stats['kosong']).' pegawai belum mengisi data' : 'seluruh data terisi' }}</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">

    {{-- ================= STATISTIK: REKAP PER UNIT KERJA ================= --}}
    <div class="col-xl-5">
        <div class="chart-card h-100">
            <h5><i class="bi bi-bar-chart-steps me-2"></i>Rekapitulasi Kemampuan Renang per Unit Kerja</h5>

            <?php $rekapMax = $rekap->max('total') ?: 1; ?>
            @forelse ($rekap as $row)
                @php
                    $pctLulus = (int) round($row->lulus / $rekapMax * 100);
                    $pctBelum = (int) round($row->belum / $rekapMax * 100);
                @endphp
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="fw-semibold text-truncate" title="{{ $row->unit }}">{{ $row->unit }}</span>
                        <span class="text-muted text-nowrap">{{ $row->lulus }} lulus &middot; {{ $row->belum }} belum</span>
                    </div>
                    <div class="progress" style="height: 10px;" title="Lulus: {{ $row->lulus }} | Belum lulus: {{ $row->belum }} @if($row->kosong)| Belum diisi: {{ $row->kosong }}@endif">
                        <div class="progress-bar" style="width: {{ $pctLulus }}%; background: var(--osdmrb-primary);" aria-label="Lulus"></div>
                        <div class="progress-bar bg-danger" style="width: {{ $pctBelum }}%;" aria-label="Belum lulus"></div>
                    </div>
                </div>
            @empty
                <p class="text-center text-muted py-4 mb-0">Belum ada data renang pegawai untuk filter ini.</p>
            @endforelse

            <div class="d-flex gap-3 small text-muted mt-2">
                <span><span class="d-inline-block rounded-circle" style="width:10px;height:10px;background:var(--osdmrb-primary)"></span> Lulus Berenang</span>
                <span><span class="d-inline-block rounded-circle bg-danger" style="width:10px;height:10px"></span> Belum Lulus</span>
            </div>
        </div>
    </div>

    {{-- ================= DAFTAR DATA RENANG PEGAWAI ================= --}}
    <div class="col-xl-7">
        <div class="table-card h-100">
            <div class="card-header-custom flex-wrap gap-2">
                <h5 class="mb-0"><i class="bi bi-water me-2"></i>Daftar Data Renang Pegawai</h5>
                <span class="badge bg-primary-subtle text-primary">{{ number_format($employees->total()) }} pegawai</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:44px">No</th>
                            <th>Nama Pegawai</th>
                            <th>NIP</th>
                            <th>Unit Kerja</th>
                            <th>Jabatan</th>
                            <th class="text-center">Kemampuan Renang</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            <tr>
                                <td class="text-center text-muted">{{ $employees->firstItem() + $loop->iteration - 1 }}</td>
                                <td class="fw-semibold">
                                    @if ($canSeeEmployee)
                                        <a href="{{ route('employees.show', $employee) }}" class="text-decoration-none">{{ $employee->name }}</a>
                                    @else
                                        {{ $employee->name }}
                                    @endif
                                </td>
                                <td><small>{{ $employee->nip }}</small></td>
                                <td><small>{{ $employee->unit?->name ?? '-' }}</small></td>
                                <td><small>{{ $employee->position_name ?? '-' }}</small></td>
                                <td class="text-center">
                                    @if ($employee->swimming_skill === 'lulus')
                                        <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>Lulus Berenang</span>
                                    @elseif ($employee->swimming_skill === 'belum')
                                        <span class="badge bg-danger-subtle text-danger"><i class="bi bi-x-circle me-1"></i>Belum Lulus</span>
                                    @else
                                        <span class="badge bg-light text-dark border">Belum Diisi</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada data pegawai untuk filter ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3 px-3 pb-2">
                {{ $employees->links() }}
            </div>
        </div>
    </div>
</div>

@endsection

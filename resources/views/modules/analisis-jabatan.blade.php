@extends('layouts.app')

@section('page_title', 'Analisis Jabatan Fungsional')
@section('page_subtitle', 'Pemetaan formasi & pemangku jabatan fungsional tertentu')

@section('content')

<!-- ================= RINGKASAN ================= -->
<div class="row g-3 mb-4">
    <div class="col-lg-3 col-md-6 col-6">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-clipboard2-data"></i></div>
            <div>
                <span>Jenis Jabatan</span>
                <h2>{{ number_format($totalJabatan) }}</h2>
                <small>fungsional tertentu</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-6">
        <div class="stat-card">
            <div class="stat-icon success"><i class="bi bi-person-check"></i></div>
            <div>
                <span>Total Pemangku</span>
                <h2>{{ number_format($totalPemangku) }}</h2>
                <small>pegawai aktif</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-6">
        <div class="stat-card">
            <div class="stat-icon danger"><i class="bi bi-person-x"></i></div>
            <div>
                <span>Formasi Kosong</span>
                <h2>{{ number_format($jabatanKosong) }}</h2>
                <small>belum terisi</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-6">
        <div class="stat-card">
            <div class="stat-icon accent"><i class="bi bi-bar-chart-steps"></i></div>
            <div>
                <span>Rasio Isi</span>
                <h2>{{ $totalJabatan > 0 ? number_format($totalPemangku / $totalJabatan, 1) : 0 }}</h2>
                <small>pemangku / jenis</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- ================= TABEL JABATAN ================= -->
    <div class="col-lg-7">
        <div class="table-card h-100">
            <div class="card-header-custom">
                <h5 class="mb-0"><i class="bi bi-list-columns me-2"></i>Daftar Jabatan Fungsional</h5>
                <span class="badge bg-primary-subtle text-primary">{{ $positions->count() }} jenis</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama Jabatan</th>
                            <th>Jenjang</th>
                            <th class="text-center">Pemangku</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($positions as $position)
                            <tr>
                                <td><code>{{ $position->code }}</code></td>
                                <td class="fw-semibold">{{ $position->name }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $position->jobLevel?->name ?? '-' }}</span></td>
                                <td class="text-center">{{ $position->holders_count }}</td>
                                <td>
                                    @if ($position->holders_count === 0)
                                        <span class="badge bg-danger-subtle text-danger">Kosong</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success">Terisi</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data jabatan fungsional.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ================= DISTRIBUSI JENJANG ================= -->
    <div class="col-lg-5">
        <div class="chart-card h-100">
            <h5><i class="bi bi-bar-chart-steps me-2"></i>Distribusi Jenjang Fungsional</h5>

            @forelse ($jenjang as $row)
                @php
                    $max = $jenjang->max('total') ?: 1;
                    $pct = (int) round($row->total / $max * 100);
                @endphp
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="fw-semibold">{{ $row->label }}</span>
                        <span class="text-muted">{{ $row->total }} pegawai</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar" style="width: {{ $pct }}%; background: var(--osdmrb-primary);"></div>
                    </div>
                </div>
            @empty
                <p class="text-center text-muted py-5 mb-0">Belum ada data jenjang fungsional pegawai.</p>
            @endforelse
        </div>
    </div>
</div>

<!-- ================= CATATAN ANALISIS (selebar full) ================= -->
<div class="chart-card mt-3">
    <h5><i class="bi bi-info-circle me-2"></i>Catatan Analisis</h5>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="d-flex gap-2 h-100">
                <i class="bi bi-search fs-5" style="color: var(--osdmrb-primary);"></i>
                <p class="small text-muted mb-0">Formasi kosong menjadi bahan <em>needs analysis</em> pengadaan CPNS/PPPK tahun berikutnya.</p>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="d-flex gap-2 h-100">
                <i class="bi bi-bar-chart-line fs-5" style="color: var(--osdmrb-primary);"></i>
                <p class="small text-muted mb-0">Kesenjangan jenjang (piramida) perlu ditinjau untuk keseimbangan karier fungsional.</p>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="d-flex gap-2 h-100">
                <i class="bi bi-clock-history fs-5" style="color: var(--osdmrb-primary);"></i>
                <p class="small text-muted mb-0">Data pemangku diambil dari riwayat jabatan aktif pegawai.</p>
            </div>
        </div>
    </div>
</div>

@endsection

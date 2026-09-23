@extends('layouts.app')

@section('page_title', 'Analisis Jabatan Pelaksana')
@section('page_subtitle', 'Pemetaan formasi & pemangku jabatan pelaksana (fungsional umum)')

@section('content')

<!-- ================= RINGKASAN ================= -->
<div class="row g-3 mb-4">
    <div class="col-lg-3 col-md-6 col-6">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-person-lines-fill"></i></div>
            <div>
                <span>Total Pelaksana</span>
                <h2>{{ number_format($totalPelaksana) }}</h2>
                <small>pelaksana aktif</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-6">
        <div class="stat-card">
            <div class="stat-icon success"><i class="bi bi-person-check"></i></div>
            <div>
                <span>Total Pemangku</span>
                <h2>{{ number_format($totalPemangku) }}</h2>
                <small>terpetakan jabatan</small>
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
                <h5 class="mb-0"><i class="bi bi-list-columns me-2"></i>Daftar Jabatan Pelaksana</h5>
                <span class="badge bg-primary-subtle text-primary">{{ $totalJabatan }} jenis</span>
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
                            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data jabatan pelaksana.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ================= DISTRIBUSI JENJANG ================= -->
    <div class="col-lg-5">
        <div class="chart-card h-100">
            <h5><i class="bi bi-bar-chart-steps me-2"></i>Distribusi Perjenjang Pelaksana</h5>

            @forelse ($jenjang as $row)
                @php
                    $max = $jenjang->max('total') ?: 1;
                    $pct = (int) round($row['total'] / $max * 100);
                @endphp
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="fw-semibold">{{ $row['label'] }}</span>
                        <span class="text-muted">{{ $row['total'] }} pelaksana</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar" style="width: {{ $pct }}%; background: var(--osdmrb-primary);"></div>
                    </div>
                </div>
            @empty
                <p class="text-center text-muted py-4 mb-0">Belum ada data pelaksana terpetakan.</p>
            @endforelse

            <h5 class="mt-4"><i class="bi bi-building me-2"></i>Sebaran per Unit Kerja</h5>
            @php($unitMax = $perUnit->max('total') ?: 1)
            @forelse ($perUnit as $row)
                <div class="mb-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="fw-semibold text-truncate" title="{{ $row['label'] }}">{{ $row['label'] }}</span>
                        <span class="text-muted">{{ $row['total'] }}</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar" style="width: {{ (int) round($row['total'] / $unitMax * 100) }}%; background: var(--osdmrb-accent);"></div>
                    </div>
                </div>
            @empty
                <p class="text-center text-muted py-3 mb-0">Belum ada data sebaran unit.</p>
            @endforelse
        </div>
    </div>
</div>

{{-- Catatan analisis --}}
<div class="row g-3 mt-0">
    <div class="col-lg-12">
        <div class="chart-card">
            <h5><i class="bi bi-info-circle me-2"></i>Catatan Analisis</h5>
            <ul class="small text-muted mb-0 ps-3">
                <li class="mb-2">Jabatan pelaksana mencakup pegawai non-eselon &amp; non-fungsional (pelaksana murni); jenjang Penyelia dan Terampil tergolong fungsional.</li>
                <li class="mb-2">Formasi pelaksana kosong menjadi bahan pengisian jabatan melalui seleksi PPPK / perpindahan antar unit.</li>
                <li>Data pemangku diambil dari riwayat jabatan aktif pegawai ASN (jenis jabatan Pelaksana).</li>
            </ul>
        </div>
    </div>
</div>

@endsection

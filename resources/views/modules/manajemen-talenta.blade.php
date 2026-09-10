@extends('layouts.app')

@section('page_title', 'Manajemen Talenta')
@section('page_subtitle', 'Pemetaan talent pool & pipeline suksesi pegawai')

@section('content')

<!-- ================= RINGKASAN ================= -->
<div class="row g-3 mb-4">
    <div class="col-xl col-md-4 col-6">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
            <div>
                <span>Total ASN</span>
                <h2>{{ number_format($summary['total']) }}</h2>
                <small>pegawai aktif</small>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-4 col-6">
        <div class="stat-card">
            <div class="stat-icon success"><i class="bi bi-star-fill"></i></div>
            <div>
                <span>High Potential</span>
                <h2>{{ number_format($summary['highPotential']) }}</h2>
                <small>&le; 40 th &amp; jabatan senior</small>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-4 col-6">
        <div class="stat-card">
            <div class="stat-icon accent"><i class="bi bi-person-badge"></i></div>
            <div>
                <span>Siap Suksesi</span>
                <h2>{{ number_format($summary['siapSuksesi']) }}</h2>
                <small>jabatan senior</small>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-6 col-6">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-emoji-smile"></i></div>
            <div>
                <span>Kader Muda</span>
                <h2>{{ number_format($summary['kaderMuda']) }}</h2>
                <small>&le; 40 tahun</small>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-6 col-12">
        <div class="stat-card">
            <div class="stat-icon danger"><i class="bi bi-hourglass-bottom"></i></div>
            <div>
                <span>Persiapan Pensiun</span>
                <h2>{{ number_format($summary['persiapanPensiun']) }}</h2>
                <small>&le; 2 tahun lagi</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- ================= PIPELINE TALENTA ================= -->
    <div class="col-lg-8">
        <div class="table-card h-100">
            <div class="card-header-custom">
                <h5 class="mb-0"><i class="bi bi-diagram-2 me-2"></i>Pipeline Talenta (Prioritas)</h5>
                <span class="badge bg-primary-subtle text-primary">{{ $pipeline->count() }} pegawai</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama</th>
                            <th>Jabatan</th>
                            <th>Gol.</th>
                            <th class="text-center">Usia</th>
                            <th>Kategori</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pipeline as $item)
                            @php($employee = $item['employee'])
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <a href="{{ route('employees.show', $employee) }}" class="text-decoration-none fw-semibold">
                                        {{ $employee->name }}
                                    </a>
                                    <div class="text-muted small">{{ $employee->nip }}</div>
                                </td>
                                <td class="small">
                                    {{ $employee->position_name ?: ($employee->currentPosition?->position?->name ?? '-') }}
                                </td>
                                <td>{{ $employee->rank?->group_name ?? '-' }}</td>
                                <td class="text-center">{{ $item['age'] ?? '-' }}</td>
                                <td>
                                    @switch($item['category'])
                                        @case('High Potential')<span class="badge bg-success-subtle text-success">High Potential</span>@break
                                        @case('Siap Suksesi')<span class="badge bg-primary-subtle text-primary">Siap Suksesi</span>@break
                                        @case('Kader Muda')<span class="badge bg-info-subtle text-info">Kader Muda</span>@break
                                        @case('Persiapan Pensiun')<span class="badge bg-warning-subtle text-warning">Persiapan Pensiun</span>@break
                                        @default<span class="badge bg-light text-dark border">Reguler</span>
                                    @endswitch
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data pegawai.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ================= KERANGKA 9 BOX ================= -->
    <div class="col-lg-4">
        <div class="chart-card h-100">
            <h5><i class="bi bi-grid-3x3-gap me-2"></i>Kerangka 9-Box Grid</h5>
            <p class="small text-muted">Potensi &times; Kinerja. Saat ini kategori memakai heuristik usia &amp; jenjang jabatan — akan ditingkatkan saat data penilaian kinerja &amp; asesmen tersedia.</p>

            <div class="nine-box mb-3">
                @foreach ([['Bintang', 'bi-star-fill'], ['Calon Pemimpin', 'bi-award'], ['Question Mark', 'bi-question-circle'], ['High Pro', 'bi-rocket-takeoff'], ['Teras', 'bi-gem'], ['Karya Cetak', 'bi-brush'], ['Berpotensi', 'bi-graph-up'], ['Andal', 'bi-person-check'], ['Kurang Optimal', 'bi-arrow-down-circle']] as [$label, $icon])
                    <div class="nine-box-cell">
                        <i class="bi {{ $icon }}"></i>
                        <span>{{ $label }}</span>
                    </div>
                @endforeach
            </div>

            <div class="small text-muted">
                <div class="d-flex justify-content-between"><span>Sumbu Y</span><strong>Potensi (tinggi &rarr; rendah)</strong></div>
                <div class="d-flex justify-content-between"><span>Sumbu X</span><strong>Kinerja (rendah &rarr; tinggi)</strong></div>
            </div>
        </div>
    </div>
</div>

@endsection

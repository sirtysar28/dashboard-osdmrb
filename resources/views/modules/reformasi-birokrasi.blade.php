@extends('layouts.app')

@section('page_title', 'Reformasi Birokrasi')
@section('page_subtitle', 'Monitoring 8 area Reformasi Birokrasi & budaya kerja AKHLAK')

@section('content')

<!-- ================= HERO ================= -->
<div class="module-hero mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h4><i class="bi bi-arrow-repeat me-2"></i>Grand Design Reformasi Birokrasi</h4>
            <p class="mb-0">Monitoring kemajuan 8 area reformasi birokrasi menuju World Class Bureaucracy 2024 &ndash; birokrasi yang bersih, dinamis, dan melayani.</p>
        </div>
        <div class="text-end">
            <span class="d-block small text-muted">Rata-rata Kemajuan</span>
            <strong class="module-hero-number">
                {{ number_format(collect($areas)->avg('progress'), 1) }}%
            </strong>
        </div>
    </div>
    <div class="progress mt-3" style="height: 8px;">
        <div class="progress-bar" style="width: {{ collect($areas)->avg('progress') }}%; background: var(--osdmrb-accent);"></div>
    </div>
</div>

<!-- ================= 8 AREA RB ================= -->
<div class="row g-3 mb-4">
    @foreach ($areas as $area)
        <div class="col-xl-3 col-md-6">
            <div class="rb-card h-100">
                <div class="d-flex align-items-start justify-content-between mb-2">
                    <div class="rb-icon"><i class="bi {{ $area['icon'] }}"></i></div>
                    <span class="badge {{ $area['status'] === 'Berjalan' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}">
                        {{ $area['status'] }}
                    </span>
                </div>
                <h6 class="fw-bold mb-1">{{ $area['name'] }}</h6>
                <p class="small text-muted">{{ $area['desc'] }}</p>
                <div class="d-flex justify-content-between align-items-center small mb-1">
                    <span class="text-muted">Kemajuan</span>
                    <strong>{{ $area['progress'] }}%</strong>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar" style="width: {{ $area['progress'] }}%;"></div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<!-- ================= AKHLAK ================= -->
<div class="row g-3">
    <div class="col-lg-12">
        <div class="table-card">
            <div class="card-header-custom">
                <h5 class="mb-0"><i class="bi bi-heart me-2"></i>Budaya Kerja Ber-AKHLAK</h5>
                <span class="badge bg-primary-subtle text-primary">Core Values</span>
            </div>

            <div class="row g-3 p-2">
                @foreach ([
                    ['Berorientasi Pelayanan', 'bi-hand-thumbs-up', 'Berkomitmen memberikan pelayanan prima demi kepuasan masyarakat.'],
                    ['Akuntabel', 'bi-clipboard-check', 'Melaksanakan tugas dengan jujur, bertanggung jawab, cermat, disiplin, dan berintegritas tinggi.'],
                    ['Kompeten', 'bi-mortarboard', 'Terus belajar dan mengembangkan kapabilitas untuk menjawab tantangan yang selalu berubah.'],
                    ['Harmonis', 'bi-people', 'Saling peduli, menghargikan perbedaan, dan bersenang bekerja sama.'],
                    ['Loyal', 'bi-shield-lock', 'Berdedikasi pada bangsa dan negara, serta mengutamakan kepentingan publik.'],
                    ['Adaptif', 'bi-lightning', 'Terus berinovasi dan antusias menghadapi perubahan.'],
                    ['Kolaboratif', 'bi-diagram-3', 'Membangun kerja sama yang sinergis untuk hasil maksimal.'],
                ] as [$name, $icon, $desc])
                    <div class="col-xl col-md-4 col-6">
                        <div class="akhlak-item h-100">
                            <i class="bi {{ $icon }}"></i>
                            <strong>{{ $name }}</strong>
                            <p>{{ $desc }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@endsection

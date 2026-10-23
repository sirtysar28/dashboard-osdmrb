@extends('layouts.app')

@section('page_title', 'Dashboard Peta Jabatan')
@section('page_subtitle', 'Pemetaan sebaran pegawai jabatan fungsional — langsung dari data pegawai')

@section('content')

{{-- ================= FILTER =================
     Filter unit kerja (eselon I & tingkat balai, beserta seluruh
     turunannya) dan jenis jabatan fungsional. --}}
<div class="filter-card mb-4">
    <form method="GET" action="{{ route('modules.peta-jabatan') }}">
        <div class="row g-2">
            <div class="col-lg-4 col-md-4 col-12">
                <label>Unit Kerja (Eselon I)</label>
                <select name="es1" class="form-select form-select-sm">
                    <option value="">Semua Unit Kerja</option>
                    @foreach ($filterOptions['es1List'] as $unit)
                        <option value="{{ $unit->id }}" {{ (string) ($filters['es1'] ?? '') === (string) $unit->id ? 'selected' : '' }}>
                            {{ $unit->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-4 col-md-4 col-12">
                <label>Unit Kerja Balai</label>
                <select name="balai" class="form-select form-select-sm">
                    <option value="">Semua Balai</option>
                    @foreach ($filterOptions['balaiList'] as $unit)
                        <option value="{{ $unit->id }}" {{ (string) ($filters['balai'] ?? '') === (string) $unit->id ? 'selected' : '' }}>
                            {{ $unit->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-4 col-md-4 col-12">
                <label>Jabatan Fungsional</label>
                <select name="jabatan" class="form-select form-select-sm">
                    <option value="">Semua Jenis Jabatan</option>
                    @foreach ($filterOptions['jabatanList'] as $name)
                        <option value="{{ $name }}" {{ ($filters['jabatan'] ?? '') === $name ? 'selected' : '' }}>
                            {{ $name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 d-flex gap-2 justify-content-end">
                <a href="{{ route('modules.peta-jabatan') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </a>
                <button class="btn btn-osdmrb btn-sm px-4">
                    <i class="bi bi-search"></i> Terapkan
                </button>
            </div>
        </div>
    </form>
</div>

{{-- ================= RINGKASAN STATISTIK =================
     Seluruh angka dihitung otomatis dari data pegawai aktif. --}}
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
            <div>
                <span>Jumlah Pegawai Fungsional</span>
                <h2>{{ number_format($summary['existing']) }}</h2>
                <small>penduduki jabatan fungsional</small>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon success"><i class="bi bi-clipboard2-data"></i></div>
            <div>
                <span>Jenis Jabatan Fungsional</span>
                <h2>{{ number_format($summary['jenis']) }}</h2>
                <small>terpetakan dari data pegawai</small>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon accent"><i class="bi bi-diagram-3"></i></div>
            <div>
                <span>Unit Kerja Terpetakan</span>
                <h2>{{ number_format($summary['units']) }}</h2>
                <small>memiliki jabatan fungsional</small>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-calculator"></i></div>
            <div>
                <span>Rata-rata per Jenis Jabatan</span>
                <h2>{{ number_format($summary['average'], 1) }}</h2>
                <small>pegawai tiap jenis jabatan</small>
            </div>
        </div>
    </div>
</div>

{{-- ================= GRAFIK SEBARAN ================= --}}
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="chart-card h-100">
            <h5><i class="bi bi-bar-chart-line me-2"></i>Jabatan Fungsional — Jumlah Pegawai Terbanyak</h5>
            <div class="chart-wrap" style="height:420px"><canvas id="petaJabatanChart"></canvas></div>
            <small class="text-muted d-block mt-2">12 jenis jabatan fungsional dengan jumlah pegawai terbanyak sesuai filter.</small>
        </div>
    </div>

    {{-- ================= REKOMENDASI & STRATEGI ================= --}}
    <div class="col-lg-5">
        <div class="chart-card h-100">
            <h5><i class="bi bi-lightbulb me-2"></i>Rekomendasi</h5>
            <p class="small text-muted">
                Peta jabatan ini dihitung <strong>langsung dari data pegawai aktif</strong> — tanpa import Excel —
                sehingga selalu sinkron dengan basis data kepegawaian. Gunakan sebaran jenis jabatan fungsional
                per unit kerja sebagai dasar analisis formasi, regenerasi, dan pengembangan kompetensi.
            </p>
            <h5 class="mt-4"><i class="bi bi-rocket-takeoff me-2"></i>Strategi Tindak Lanjut</h5>
            <ul class="small text-muted mb-0">
                <li class="mb-2">Jadikan sebaran ini dasar pengusulan formasi CPNS/PPPK pada jabatan fungsional.</li>
                <li class="mb-2">Perhatikan jenis jabatan dengan pemangku tunggal untuk perencanaan suksesi &amp; regenerasi.</li>
                <li class="mb-2">Redistribusi pegawaian fungsional antar unit kerja agar sebaran lebih merata.</li>
                <li class="mb-0">Penyelenggaraan diklat fungsional sesuai kebutuhan peningkatan jenjang jabatan.</li>
            </ul>
        </div>
    </div>
</div>

{{-- ================= TABEL PETA JABATAN ================= --}}
<div class="table-card mb-4">
    <div class="card-header-custom flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-table me-2"></i>Detail Peta Jabatan Fungsional</h5>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('modules.peta-jabatan.export', array_filter($filters)) }}"
               class="btn btn-sm btn-outline-osdmrb" data-no-loader>
                <i class="bi bi-file-earmark-excel"></i> Ekspor Excel
            </a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th class="text-center" style="width:44px">No</th>
                    <th>Jenis Jabatan Fungsional</th>
                    <th>Unit Kerja</th>
                    <th>Jenjang Terisi</th>
                    <th class="text-center">Jumlah Pegawai</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($table as $row)
                    <tr>
                        <td class="text-center text-muted">{{ $table->firstItem() + $loop->iteration - 1 }}</td>
                        <td class="fw-semibold">{{ $row->name }}</td>
                        <td>{{ $row->unit_name }}</td>
                        <td>
                            @if (empty($row->levels))
                                <small class="text-muted">-</small>
                            @else
                                @foreach ($row->levels as $level => $count)
                                    <span class="badge bg-primary-subtle text-primary-emphasis me-1">{{ $level }} &middot; {{ $count }}</span>
                                @endforeach
                            @endif
                        </td>
                        <td class="text-center fw-semibold">{{ number_format($row->existing_count) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            Belum ada data pegawai jabatan fungsional untuk filter ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $table->links() }}</div>
</div>

@endsection

@push('scripts')
{{-- Chart.js lokal: bebas dari blokir/ gangguan CDN. --}}
<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const el = document.getElementById('petaJabatanChart');
        if (!el) return;

        /* pengaman: bila Chart gagal dimuat, tampilkan pesan */
        if (typeof Chart === 'undefined') {
            el.closest('.chart-wrap').innerHTML =
                '<div class="text-muted small text-center py-5">Pustaka grafik (Chart.js) gagal dimuat. Muat ulang halaman.</div>';
            return;
        }

        Chart.defaults.font = { family: "'Segoe UI', sans-serif", size: 11 };

        const themePrimary = @js(\App\Models\Setting::get('theme_primary', '#163d4f'));
        const chart = @json($chart->all());

        new Chart(el, {
            type: 'bar',
            data: {
                labels: chart.map((r) => r.label),
                datasets: [
                    {
                        label: 'Pegawai Existing',
                        data: chart.map((r) => r.existing),
                        backgroundColor: themePrimary,
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                animation: { duration: 400 },
                scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
                plugins: {
                    legend: { display: true, position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 12 } },
                    tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + c.parsed.x + ' pegawai' } }
                }
            }
        });
    });
</script>
@endpush

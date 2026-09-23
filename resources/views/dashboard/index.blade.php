@extends('layouts.app')

@section('page_title', 'Dashboard Biro OSDMRB')
@section('page_subtitle', 'Monitoring Data Kepegawaian')

@section('content')

{{-- ================= PENGUMUMAN (paling atas semua dashboard) ================= --}}
<x-announcement-banner :announcements="$announcements" :announcement="$announcement" />

<!-- ================= FILTER =================
     Semua filter berupa dropdown CHECKLIST (tertutup secara default):
     klik untuk membuka, centang lebih dari satu opsi, lalu tekan "Terapkan". -->
<div class="filter-card mb-4">
    <form method="GET" action="{{ route('dashboard') }}">
        <div class="row g-2">
            <div class="col-lg-2 col-md-4 col-6">
                <label>Eselon I</label>
                <x-multi-select name="es1" placeholder="Semua Es. I"
                                :options="$filterOptions['es1List']->pluck('name', 'id')"
                                :selected="$filters['es1'] ?? []" />
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <label>Eselon II</label>
                <x-multi-select name="es2" placeholder="Semua Es. II"
                                :options="$filterOptions['es2List']->pluck('name', 'id')"
                                :selected="$filters['es2'] ?? []" />
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <label>Balai</label>
                <x-multi-select name="balai" placeholder="Semua Balai"
                                :options="$filterOptions['balaiList']->pluck('name', 'id')"
                                :selected="$filters['balai'] ?? []" />
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <label>Status ASN</label>
                <x-multi-select name="status_asn" placeholder="Semua Status"
                                :options="$filterOptions['statusList']->pluck('name', 'id')"
                                :selected="$filters['status_asn'] ?? []" />
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <label>Golongan</label>
                <x-multi-select name="rank" placeholder="Semua Golongan"
                                :options="$filterOptions['rankList']->pluck('code', 'id')"
                                :selected="$filters['rank'] ?? []" />
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <label>Pendidikan</label>
                <x-multi-select name="education" placeholder="Semua Pendidikan"
                                :options="$filterOptions['educationList']->pluck('name', 'id')"
                                :selected="$filters['education'] ?? []" />
            </div>
            <div class="col-lg-4 col-md-6 col-8">
                <label>Pencarian</label>
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Nama / NIP..." value="{{ $filters['search'] ?? '' }}">
            </div>
            <div class="col-lg-8 col-md-6 col-4 d-flex align-items-end gap-2 justify-content-end">
                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </a>
                <button class="btn btn-osdmrb btn-sm px-4">
                    <i class="bi bi-search"></i> Terapkan
                </button>
            </div>
        </div>
    </form>
</div>

<!-- ================= KPI TOTAL KESELURUHAN PEGAWAI (di bawah filter) =================
     Isi khusus (tidak dobel dengan stat-card-link di bawah):
     Total keseluruhan (ASN & PPPK aktif & Non ASN) beserta rinciannya. -->
<div class="row g-3 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="kpi-card">
            <div class="kpi-icon"><i class="bi bi-people-fill"></i></div>
            <div class="kpi-body">
                <span>Total Keseluruhan Pegawai</span>
                <h1>{{ number_format($summary['totalAll']) }}</h1>
                <small class="d-block text-white-50" style="font-size:11.5px">ASN &amp; PPPK aktif + Non ASN</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="kpi-card">
            <div class="kpi-icon"><i class="bi bi-person-check"></i></div>
            <div class="kpi-body">
                <span>ASN</span>
                <h1>{{ number_format($summary['asnStatusCount']) }}</h1>
                <small class="d-block text-white-50" style="font-size:11.5px">pegawai berstatus ASN aktif</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="kpi-card">
            <div class="kpi-icon"><i class="bi bi-file-earmark-person"></i></div>
            <div class="kpi-body">
                <span>PPPK Aktif</span>
                <h1>{{ number_format($summary['pppkCount']) }}</h1>
                <small class="d-block text-white-50" style="font-size:11.5px">penuh &amp; paruh waktu</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="kpi-card">
            <div class="kpi-icon"><i class="bi bi-person-badge"></i></div>
            <div class="kpi-body">
                <span>Non ASN</span>
                <h1>{{ number_format($summary['nonAsnCount']) }}</h1>
                <small class="d-block text-white-50" style="font-size:11.5px">pramubakti, security, dll</small>
            </div>
        </div>
    </div>
</div>

<!-- ================= STATISTIC CARDS (setelah KPI) =================
     Kartu yang datanya dobel dengan KPI total keseluruhan pegawai di atas
     (Total ASN, Non ASN, PPPK) sudah dihapus. -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <a href="{{ route('employees.index', ['jenis' => 'struktural']) }}" class="stat-card-link">
            <div class="stat-card stat-clickable">
                <div class="stat-icon"><i class="bi bi-person-workspace"></i></div>
                <div>
                    <span>Jabatan Struktural</span>
                    <h2>{{ number_format($summary['structuralCount']) }}</h2>
                    <small>Pimpinan / eselon</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-md-6">
        <a href="{{ route('employees.index', ['jenis' => 'fungsional']) }}" class="stat-card-link">
            <div class="stat-card stat-clickable">
                <div class="stat-icon success"><i class="bi bi-person-vcard"></i></div>
                <div>
                    <span>Jabatan Fungsional</span>
                    <h2>{{ number_format($summary['functionalCount']) }}</h2>
                    <small>Fungsional tertentu</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-md-6">
        <a href="{{ route('employees.index', ['pensiun' => 1]) }}" class="stat-card-link">
            <div class="stat-card stat-clickable">
                <div class="stat-icon danger"><i class="bi bi-hourglass-split"></i></div>
                <div>
                    <span>Akan Pensiun Tahun Ini</span>
                    <h2>{{ number_format($summary['retiringThisYear']) }}</h2>
                    <small>Batas usia pensiun {{ now()->year }}</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-md-6">
        <a href="{{ route('employees.index', ['pensiun' => 2]) }}" class="stat-card-link">
            <div class="stat-card stat-clickable">
                <div class="stat-icon"><i class="bi bi-hourglass-bottom"></i></div>
                <div>
                    <span>Pensiun &le; 2 Tahun</span>
                    <h2>{{ number_format($summary['retiringSoon']) }}</h2>
                    <small>pegawai mendekati BUP</small>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- ================= STATISTIC CARDS KENAIKAN JABATAN + PENGUNJUNG ================= -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <a href="{{ route('audit.index') }}" class="stat-card-link">
            <div class="stat-card stat-clickable">
                <div class="stat-icon accent"><i class="bi bi-graph-up-arrow"></i></div>
                <div>
                    <span>Pengunjung Hari Ini</span>
                    <h2>{{ number_format($visitorStats['today']) }}</h2>
                    <small>{{ number_format($visitorStats['uniqueToday']) }} pengguna berbeda</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-md-6">
        <a href="{{ route('employees.index', ['naik' => 'tahun_ini']) }}" class="stat-card-link">
            <div class="stat-card stat-clickable">
                <div class="stat-icon success"><i class="bi bi-arrow-up-circle-fill"></i></div>
                <div>
                    <span>Kenaikan Pangkat {{ now()->year }}</span>
                    <h2>{{ number_format($promotionStats['thisYear']) }}</h2>
                    <small>estimasi tahun berjalan</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-md-6">
        <a href="{{ route('employees.index', ['naik' => 1]) }}" class="stat-card-link">
            <div class="stat-card stat-clickable">
                <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
                <div>
                    <span>Kenaikan &le; 1 Tahun</span>
                    <h2>{{ number_format($promotionStats['dueSoon']) }}</h2>
                    <small>{{ $promotionStats['nextYear'] }} dijadwalkan tahun depan</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-md-6">
        <a href="{{ route('employees.index', ['naik' => 'overdue']) }}" class="stat-card-link">
            <div class="stat-card stat-clickable">
                <div class="stat-icon danger"><i class="bi bi-alarm-fill"></i></div>
                <div>
                    <span>Jatuh Tempo Kenaikan</span>
                    <h2>{{ number_format($promotionStats['overdue']) }}</h2>
                    <small>melewati estimasi kenaikan</small>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- ================= STATISTIC CARDS KENAIKAN GAJI BERKALA (KGB) =================
     KGB berkala 2 tahun untuk ASN, CPNS & PPPK/P3K. -->
<div class="row g-3 mb-4">
    <div class="col-xl-4 col-md-4">
        <a href="{{ route('employees.index', ['kgb' => 'tahun_ini']) }}" class="stat-card-link">
            <div class="stat-card stat-clickable">
                <div class="stat-icon success"><i class="bi bi-cash-coin"></i></div>
                <div>
                    <span>KGB {{ now()->year }}</span>
                    <h2>{{ number_format($salaryRaiseStats['thisYear']) }}</h2>
                    <small>kenaikan gaji berkala tahun ini</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-4 col-md-4">
        <a href="{{ route('employees.index', ['kgb' => 1]) }}" class="stat-card-link">
            <div class="stat-card stat-clickable">
                <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
                <div>
                    <span>KGB &le; 1 Tahun</span>
                    <h2>{{ number_format($salaryRaiseStats['dueSoon']) }}</h2>
                    <small>{{ $salaryRaiseStats['nextYear'] }} dijadwalkan tahun depan</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-4 col-md-4">
        <a href="{{ route('employees.index', ['kgb' => 'overdue']) }}" class="stat-card-link">
            <div class="stat-card stat-clickable">
                <div class="stat-icon danger"><i class="bi bi-alarm"></i></div>
                <div>
                    <span>Jatuh Tempo KGB</span>
                    <h2>{{ number_format($salaryRaiseStats['overdue']) }}</h2>
                    <small>melewati jadwal kenaikan gaji</small>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- ================= CHARTS ROW 1 ================= -->
<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="chart-card h-100">
            <h5><i class="bi bi-mortarboard me-2"></i>Tingkat Pendidikan</h5>
            <div class="chart-wrap"><canvas id="educationChart"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="chart-card h-100">
            <h5><i class="bi bi-bar-chart me-2"></i>Distribusi Usia</h5>
            <div class="chart-wrap"><canvas id="ageChart"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="chart-card h-100">
            <h5><i class="bi bi-graph-up me-2"></i>Proyeksi Pensiun</h5>
            <div class="chart-wrap"><canvas id="retirementChart"></canvas></div>
        </div>
    </div>
</div>

<!-- ================= CHARTS ROW 2 ================= -->
<div class="row g-3 mb-4">
    <div class="col-lg-3">
        <div class="chart-card h-100">
            <h5><i class="bi bi-gender-ambiguous me-2"></i>Komposisi Gender</h5>
            <div class="chart-wrap chart-wrap-donut"><canvas id="genderChart"></canvas></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="chart-card h-100">
            <h5><i class="bi bi-diagram-3 me-2"></i>Distribusi Unit Kerja</h5>
            {{-- Daftar unit kerja (list) — teks label diagram batang terlalu kecil & bertumpuk jika pakai chart --}}
            @php($unitMax = max(collect($unitDistribution)->max('total') ?: 1, 1))
            <div class="unit-list">
                @forelse ($unitDistribution as $unit)
                    <div class="unit-item">
                        <div class="unit-item-head">
                            <span class="unit-item-name" title="{{ $unit['label'] }}">{{ $unit['label'] }}</span>
                            <span class="unit-item-count">{{ number_format($unit['total']) }}</span>
                        </div>
                        <div class="unit-item-bar">
                            <div class="unit-item-fill" style="width: {{ max(round($unit['total'] / $unitMax * 100), 2) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted text-center py-4 mb-0">Tidak ada data unit kerja.</p>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="chart-card h-100">
            <h5><i class="bi bi-person-vcard me-2"></i>Status Kepegawaian</h5>
            <div class="chart-wrap chart-wrap-donut"><canvas id="statusChart"></canvas></div>
        </div>
    </div>
</div>

<!-- ================= CHARTS ROW 3 : KEMAMPUAN BERENANG + KGB ================= -->
<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="chart-card h-100">
            <h5><i class="bi bi-water me-2"></i>Kemampuan Berenang</h5>
            <div class="chart-wrap chart-wrap-donut"><canvas id="swimmingChart"></canvas></div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="chart-card h-100">
            <h5><i class="bi bi-cash-coin me-2"></i>Proyeksi Kenaikan Gaji Berkala (KGB)</h5>
            <div class="chart-wrap"><canvas id="salaryRaiseChart"></canvas></div>
            <small class="text-muted d-block mt-2">Kenaikan gaji berkala setiap 2 tahun — berlaku untuk ASN, CPNS &amp; PPPK/P3K.</small>
        </div>
    </div>
</div>

<!-- ================= KENAIKAN JABATAN / PANGKAT ================= -->
<div class="row g-3 mb-4">
    
    <div class="col-lg-8">
        <div class="table-card h-100">
            <div class="card-header-custom">
                <h5 class="mb-0">Proyeksi Kenaikan Pangkat Terdekat</h5>
                <a href="{{ route('employees.index', ['naik' => 1]) }}" class="btn btn-sm btn-outline-osdmrb">
                    Lihat Semua <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama</th>
                            <th>Jabatan</th>
                            <th>Golongan</th>
                            <th>Estimasi Kenaikan</th>
                            <th>Unit Kerja</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($upcomingPromotions as $employee)
                            @php($due = $employee->next_promotion_estimated)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <a href="{{ route('employees.show', $employee) }}" class="text-decoration-none fw-semibold">{{ $employee->name }}</a>
                                </td>
                                <td>{{ $employee->position_name ?: ($employee->currentPosition?->position?->name ?? '-') }}</td>
                                <td>{{ $employee->rank?->code ?? '-' }}</td>
                                <td class="text-nowrap">
                                    <span class="badge {{ $due->isSameYear(now()) ? 'bg-warning text-dark' : 'bg-primary' }}">
                                        {{ $due->translatedFormat('d M Y') }}
                                    </span>
                                    <small class="text-muted d-block">{{ $due->isSameYear(now()) ? 'tahun ini' : $due->diffForHumans(now(), parts: 1, short: true) }}</small>
                                </td>
                                <td>{{ $employee->unit?->name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data kenaikan jabatan/pangkat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="chart-card h-100">
            <h5><i class="bi bi-arrow-up-circle me-2"></i>Proyeksi Kenaikan Pangkat</h5>
            <div class="chart-wrap"><canvas id="promotionChart"></canvas></div>
        </div>
    </div>
</div>

<!-- ================= KENAIKAN GAJI BERKALA (KGB) ================= -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="table-card h-100">
            <div class="card-header-custom">
                <h5 class="mb-0">Jadwal Kenaikan Gaji Berkala (KGB) Terdekat</h5>
                <a href="{{ route('employees.index', ['kgb' => 1]) }}" class="btn btn-sm btn-outline-osdmrb">
                    Lihat Semua <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama</th>
                            <th>Golongan</th>
                            <th>TMT Golongan</th>
                            <th>Estimasi KGB</th>
                            <th>Unit Kerja</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($upcomingSalaryRaises as $employee)
                            @php($due = $employee->next_salary_raise)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <a href="{{ route('employees.show', $employee) }}" class="text-decoration-none fw-semibold">{{ $employee->name }}</a>
                                </td>
                                <td>{{ $employee->rank?->code ?? '-' }}</td>
                                <td>{{ $employee->tmt_golongan?->translatedFormat('d M Y') ?? '-' }}</td>
                                <td class="text-nowrap">
                                    <span class="badge {{ $due->isSameYear(now()) ? 'bg-warning text-dark' : 'bg-primary' }}">
                                        {{ $due->translatedFormat('d M Y') }}
                                    </span>
                                    <small class="text-muted d-block">{{ $due->isSameYear(now()) ? 'tahun ini' : $due->diffForHumans(now(), parts: 1, short: true) }}</small>
                                </td>
                                <td>{{ $employee->unit?->name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data TMT golongan untuk perhitungan KGB.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="chart-card h-100">
            <h5><i class="bi bi-info-circle me-2"></i>Tentang KGB</h5>
            <p class="small text-muted mb-2">
                Kenaikan Gaji Berkala (KGB) diberikan setiap <strong>2 tahun</strong> bagi
                <strong>ASN, CPNS dan PPPK/P3K</strong>, dihitung dari TMT golongan terakhir pegawai.
            </p>
            <table class="table table-sm small mb-0">
                <tr>
                    <td class="text-muted">KGB tahun {{ now()->year }}</td>
                    <td class="text-end fw-semibold">{{ number_format($salaryRaiseStats['thisYear']) }} pegawai</td>
                </tr>
                <tr>
                    <td class="text-muted">KGB tahun {{ now()->year + 1 }}</td>
                    <td class="text-end fw-semibold">{{ number_format($salaryRaiseStats['nextYear']) }} pegawai</td>
                </tr>
                <tr>
                    <td class="text-muted">Jatuh tempo (terlambat)</td>
                    <td class="text-end fw-semibold text-danger">{{ number_format($salaryRaiseStats['overdue']) }} pegawai</td>
                </tr>
            </table>
        </div>
    </div>
</div>

<!-- ================= TABEL PEGAWAI + SURVEI ================= -->
<div class="row g-3">
    <div class="col-lg-8">
        <div class="table-card">
            <div class="card-header-custom">
                <h5 class="mb-0">Data Pegawai</h5>
                <a href="{{ route('employees.index') }}" class="btn btn-sm btn-outline-osdmrb">
                    Lihat Semua <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama</th>
                            <th>NIP</th>
                            <th>Jabatan</th>
                            <th>Golongan</th>
                            <th>Status</th>
                            <th>Unit Kerja</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            <tr>
                                <td>{{ $employees->firstItem() + $loop->iteration - 1 }}</td>
                                <td>
                                    <a href="{{ route('employees.show', $employee) }}" class="text-decoration-none fw-semibold">
                                        {{ $employee->name }}
                                    </a>
                                </td>
                                <td>{{ $employee->nip }}</td>
                                <td>{{ $employee->position_name ?: ($employee->currentPosition?->position?->name ?? '-') }}</td>
                                <td>{{ $employee->rank?->code ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $employee->is_retired ? 'bg-secondary' : (in_array($employee->employmentStatus?->code, ['ASN', 'PNS']) ? 'bg-primary' : 'bg-info') }}">
                                        {{ $employee->display_status }}
                                    </span>
                                </td>
                                <td>{{ $employee->unit?->name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data pegawai.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $employees->links() }}
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <x-survey-form />

        <div class="chart-card mt-3">
            <h5><i class="bi bi-stars me-2"></i>Ringkasan Survei</h5>
            <div class="d-flex align-items-center gap-3 mb-2">
                <div>
                    <h1 class="mb-0" style="font-size:34px">{{ $surveyStats['average'] ?: '-' }}</h1>
                    <small class="text-muted">rata-rata dari {{ $surveyStats['total'] }} resp</small>
                </div>
                <div class="ms-auto text-end">
                    @for ($i = 1; $i <= 5; $i++)
                        <i class="bi {{ $i <= round($surveyStats['average']) ? 'bi-star-fill text-warning' : 'bi-star text-muted' }}"></i>
                    @endfor
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    const chartFont = { family: "'Segoe UI', sans-serif", size: 11 };
    Chart.defaults.font = chartFont;

    /* warna tema dari Pengaturan (mengikuti tema aplikasi) */
    const themePrimary = @js(\App\Models\Setting::get('theme_primary', '#163d4f'));
    const themeLight = @js(\App\Models\Setting::get('theme_primary_light', '#2b5f78'));
    const themeAccent = @js(\App\Models\Setting::get('theme_accent', '#e8a13c'));

    /* Opsi dasar: ukuran diambil dari wrapper .chart-wrap (tinggi tetap)
       supaya grafik STATIS dan tidak terus memanjang ke bawah. */
    const baseOptions = {
        responsive: true,
        maintainAspectRatio: false,
        resizeDelay: 150,
        animation: { duration: 400 },
        plugins: { legend: { display: false } }
    };

    /* Chart Tingkat Pendidikan (bar horizontal) */
    new Chart(document.getElementById('educationChart'), {
        type: 'bar',
        data: {
            labels: @js(collect($educationChart)->pluck('label')),
            datasets: [{
                label: 'Jumlah Pegawai',
                data: @js(collect($educationChart)->pluck('total')),
                backgroundColor: themePrimary,
                borderRadius: 5
            }]
        },
        options: {
            ...baseOptions,
            indexAxis: 'y',
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    /* Chart Distribusi Usia (bar horizontal) */
    new Chart(document.getElementById('ageChart'), {
        type: 'bar',
        data: {
            labels: @js(collect($ageDistribution)->pluck('label')),
            datasets: [{
                label: 'Jumlah Pegawai',
                data: @js(collect($ageDistribution)->pluck('total')),
                backgroundColor: themeLight,
                borderRadius: 5
            }]
        },
        options: {
            ...baseOptions,
            indexAxis: 'y',
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    /* Chart Proyeksi Pensiun (DIAGRAM GARIS / LINE) */
    new Chart(document.getElementById('retirementChart'), {
        type: 'line',
        data: {
            labels: @js(collect($retirementProjection)->pluck('label')),
            datasets: [{
                label: 'Pensiun',
                data: @js(collect($retirementProjection)->pluck('total')),
                borderColor: themeAccent,
                backgroundColor: themeAccent + '2e',
                fill: true,
                tension: 0.35,
                borderWidth: 2.5,
                pointRadius: 4,
                pointBackgroundColor: themeAccent,
                pointHoverRadius: 6
            }]
        },
        options: {
            ...baseOptions,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    /* Chart Proyeksi Kenaikan Pangkat (DIAGRAM GARIS / LINE) */
    new Chart(document.getElementById('promotionChart'), {
        type: 'line',
        data: {
            labels: @js(collect($promotionStats['projection'])->pluck('label')),
            datasets: [{
                label: 'Kenaikan Pangkat',
                data: @js(collect($promotionStats['projection'])->pluck('total')),
                // borderColor: themePrimary,
                // backgroundColor: themePrimary + '2e',
                borderColor: themeAccent,
                backgroundColor: themeAccent + '2e',
                fill: true,
                tension: 0.35,
                borderWidth: 2.5,
                pointRadius: 4,
                // pointBackgroundColor: themePrimary,
                pointBackgroundColor: themeAccent,
                pointHoverRadius: 6
            }]
        },
        options: {
            ...baseOptions,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    /* Chart Gender (DONUT) */
    new Chart(document.getElementById('genderChart'), {
        type: 'doughnut',
        data: {
            labels: @js(collect($genderComposition)->pluck('label')),
            datasets: [{
                data: @js(collect($genderComposition)->pluck('total')),
                backgroundColor: [themePrimary, themeAccent],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            ...baseOptions,
            cutout: '60%',
            plugins: {
                legend: { display: true, position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 12 } }
            }
        }
    });

    /* Chart Kemampuan Berenang (DONUT) */
    new Chart(document.getElementById('swimmingChart'), {
        type: 'doughnut',
        data: {
            labels: @js(collect($swimmingComposition)->pluck('label')),
            datasets: [{
                data: @js(collect($swimmingComposition)->pluck('total')),
                backgroundColor: [themePrimary, themeAccent, '#adb5bd'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            ...baseOptions,
            cutout: '60%',
            plugins: {
                legend: { display: true, position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 12 } }
            }
        }
    });

    /* Chart Proyeksi Kenaikan Gaji Berkala / KGB (DIAGRAM GARIS / LINE) */
    new Chart(document.getElementById('salaryRaiseChart'), {
        type: 'line',
        data: {
            labels: @js(collect($salaryRaiseStats['projection'])->pluck('label')),
            datasets: [{
                label: 'KGB',
                data: @js(collect($salaryRaiseStats['projection'])->pluck('total')),
                borderColor: themePrimary,
                backgroundColor: themePrimary + '2e',
                fill: true,
                tension: 0.35,
                borderWidth: 2.5,
                pointRadius: 4,
                pointBackgroundColor: themePrimary,
                pointHoverRadius: 6
            }]
        },
        options: {
            ...baseOptions,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    /* Chart Status Kepegawaian (DONUT) */
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: @js(collect($statusComposition)->pluck('label')),
            datasets: [{
                data: @js(collect($statusComposition)->pluck('total')),
                backgroundColor: [themePrimary, themeAccent, themeLight, '#c9a227', '#7fa8bd'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            ...baseOptions,
            cutout: '55%',
            plugins: {
                legend: { display: true, position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 10 } }
            }
        }
    });
</script>
@endpush

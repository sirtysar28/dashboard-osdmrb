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
                {{-- Opsi "Non ASN" ditambahkan di akhir daftar status
                     (nilai = Employee::TYPE_NON_ASN) supaya kartu Non ASN
                     ikut terhitung saat filter status digunakan --}}
                <x-multi-select name="status_asn" placeholder="Semua Status"
                                :options="$filterOptions['statusList']->pluck('name', 'id')->put(\App\Models\Employee::TYPE_NON_ASN, 'Non ASN')"
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
     Total keseluruhan (ASN & PPPK & Non ASN) beserta rinciannya.
     Catatan rapat 23 Sept 2026: tanpa kata "aktif" pada nama ASN & PPPK;
     jumlah mengikuti filter (unit & pencarian), termasuk Non ASN. -->
<div class="row g-3 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="kpi-card">
            <div class="kpi-icon"><i class="bi bi-people-fill"></i></div>
            <div class="kpi-body">
                <span>Total Keseluruhan Pegawai</span>
                <h1>{{ number_format($summary['totalAll']) }}</h1>
                <small class="d-block text-white-50" style="font-size:11.5px">ASN &amp; PPPK + Non ASN</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="kpi-card">
            <div class="kpi-icon"><i class="bi bi-person-check"></i></div>
            <div class="kpi-body">
                <span>ASN</span>
                <h1>{{ number_format($summary['asnStatusCount']) }}</h1>
                <small class="d-block text-white-50" style="font-size:11.5px">pegawai berstatus ASN</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="kpi-card">
            <div class="kpi-icon"><i class="bi bi-file-earmark-person"></i></div>
            <div class="kpi-body">
                <span>PPPK</span>
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


<!-- ================= STATISTIC CARDS KENAIKAN JABATAN + PENGUNJUNG =================
     Card "Jatuh Tempo Kenaikan" dihapus (permintaan). -->
<div class="row g-3 mb-4">
    <div class="col-xl-4 col-md-6">
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
    <div class="col-xl-4 col-md-6">
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
    <div class="col-xl-4 col-md-6">
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
</div>

<!-- ================= STATISTIC CARDS KENAIKAN GAJI BERKALA (KGB) =================
     KGB berkala 2 tahun untuk ASN, CPNS & PPPK/P3K.
     Card "Jatuh Tempo KGB" dihapus (permintaan). -->
<div class="row g-3 mb-4">
    <div class="col-xl-6 col-md-6">
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
    <div class="col-xl-6 col-md-6">
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
                            {{-- Catatan 21 Okt 2026: angka bisa diklik -> daftar pegawai unit --}}
                            <button type="button" class="btn btn-sm unit-drill-btn" data-unit="{{ $unit['label'] }}"
                                    title="Lihat daftar pegawai unit ini">
                                <i class="bi bi-people-fill me-1"></i>{{ number_format($unit['total']) }}
                            </button>
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
            <h5><i class="bi bi-water me-2"></i>Kemampuan Renang</h5>
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
<!-- ================= TABEL PEGAWAI + SURVEI ================= -->
<div class="row g-3">
    <div class="col-lg-8">
        <div class="table-card">
            <div class="card-header-custom">
                <h5 class="mb-0">Hasil Filter — Data Pegawai</h5>
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
                                    <span class="badge {{ $employee->is_retired ? 'bg-secondary' : (in_array($employee->employmentStatus?->code, ['ASN', 'PNS']) && ! $employee->is_effective_cpns ? 'bg-primary' : 'bg-info') }}">
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

        {{-- ================= ULANG TAHUN PEGAWAI (Catatan 21 Okt 2026) =================
             Menampilkan pegawai berulang tahun bulan ini / beberapa bulan ke depan.
             Maks. 5 item per halaman + paginasi; mode tampilan daftar / grafik. --}}
        <div class="chart-card mt-3" id="birthdayCard">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-2">
                <h5 class="mb-0"><i class="bi bi-cake2 me-2"></i>Ulang Tahun Pegawai</h5>
                <div class="d-flex gap-1">
                    <select id="birthdayPeriod" class="form-select form-select-sm" style="width:auto"
                            title="Periode ulang tahun">
                        <option value="1">Bulan ini</option>
                        <option value="3">3 bulan ke depan</option>
                        <option value="6">6 bulan ke depan</option>
                        <option value="12">12 bulan ke depan</option>
                    </select>
                    <select id="birthdayMode" class="form-select form-select-sm" style="width:auto"
                            title="Bentuk tampilan informasi">
                        <option value="list">Daftar</option>
                        <option value="chart">Grafik</option>
                    </select>
                </div>
            </div>

            <div id="birthdayMonthChip" class="mb-2" style="display:none">
                <span class="badge bg-primary-subtle text-primary">
                    <i class="bi bi-calendar-event me-1"></i><span id="birthdayMonthLabel"></span>
                    <button type="button" id="birthdayMonthReset" class="btn btn-sm p-0 text-primary"
                            title="Hapus filter bulan" style="line-height:1">&times;</button>
                </span>
            </div>

            {{-- mode daftar (maks 5 item + paginasi) --}}
            <div id="birthdayListWrap">
                <ul class="list-group list-group-flush" id="birthdayList"></ul>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <small class="text-muted" id="birthdayMeta">&nbsp;</small>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" id="birthdayPrev" title="Halaman sebelumnya">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="birthdayNext" title="Halaman berikutnya">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>

            {{-- mode grafik per bulan --}}
            <div id="birthdayChartWrap" style="display:none">
                <div class="chart-wrap"><canvas id="birthdayChart"></canvas></div>
                <small class="text-muted d-block mt-2">Klik batang bulan untuk melihat daftar pegawainya.</small>
            </div>
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

<div class="modal fade" id="drilldownModal" tabindex="-1" aria-hidden="true" aria-labelledby="drilldownModalTitle">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="drilldownModalTitle">Daftar Pegawai</h5>
                <span class="badge bg-primary-subtle text-primary ms-2" id="drilldownTotal">0 pegawai</span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-0">
                {{-- Filter pencarian popup — hanya nama / NIP / jabatan
                     (Catatan Masukan 23 Okt 2026) --}}
                <div class="px-3 py-2 border-bottom">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="drilldownSearch"
                               placeholder="Cari nama / NIP / jabatan..." autocomplete="off">
                        <button class="btn btn-outline-secondary" type="button" id="drilldownSearchClear"
                                title="Hapus pencarian"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Nama</th>
                                <th>NIP</th>
                                <th>Jabatan</th>
                                <th>Unit Kerja</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="drilldownBody"></tbody>
                    </table>
                </div>
                <div class="text-center text-muted small py-3" id="drilldownEmpty" style="display:none">
                    Tidak ada pegawai pada kategori ini.
                </div>
                {{-- Paginasi: maks. 10 baris per halaman --}}
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-2 border-top"
                     id="drilldownFooter" style="display:none">
                    <span class="text-muted small" id="drilldownInfo"></span>
                    <nav aria-label="Navigasi halaman daftar pegawai">
                        <ul class="pagination pagination-sm mb-0" id="drilldownPagination"></ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

{{-- ================= POPUP DAFTAR PEGAWAI (DRILL-DOWN INFOGRAFIS) =================
     Catatan Masukan 21 Okt 2026: klik angka/segmen grafik dashboard untuk
     menampilkan daftar pegawai penyusun angka tersebut. --}}

@push('scripts')
{{-- Chart.js lokal: bebas dari blokir/ gangguan CDN — penyebab grafik
   dashboard tidak muncul di sebagian jaringan kementerian. --}}
<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
<script>
    /* pengaman: bila Chart/ bootstrap gagal dimuat, tampilkan pesan —
       bukan mati senyap. (bootstrap penting karena popup drill-down
       memakai bootstrap.Modal; tanpa pengaman, satu kegagalan pustaka
       mematikan SEMUA grafik dashboard.) */
    if (typeof Chart === 'undefined') {
        document.querySelectorAll('.chart-wrap').forEach(function (w) {
            w.innerHTML = '<div class="text-muted small text-center py-5">Pustaka grafik (Chart.js) gagal dimuat. Muat ulang halaman.</div>';
        });
    } else if (typeof bootstrap === 'undefined') {
        document.querySelectorAll('.chart-wrap').forEach(function (w) {
            w.innerHTML = '<div class="text-muted small text-center py-5">Pustaka antarmuka (Bootstrap JS) gagal dimuat. Muat ulang halaman.</div>';
        });
    }
</script>
<script>
    if (typeof Chart !== 'undefined' && typeof bootstrap !== 'undefined') {
    const chartFont = { family: "'Segoe UI', sans-serif", size: 11 };
    Chart.defaults.font = chartFont;

    /* ================== DRILL-DOWN INFOGRAFIS (21 Okt 2026) ==================
       Klik angka/segmen grafik -> popup daftar pegawai penyusun angka. */
    const drilldownUrl = @js(route('dashboard.drilldown'));
    const drilldownModalEl = document.getElementById('drilldownModal');

    /* bcache: popup drill-down adalah fitur pelengkap — kegagalannya
       TIDAK BOLEH mematikan render grafik (try/catch pengaman). */
    let drilldownModal = null;
    try {
        drilldownModal = drilldownModalEl ? new bootstrap.Modal(drilldownModalEl) : null;
    } catch (e) {
        console.warn('Drill-down popup tidak tersedia:', e);
    }
    const drilldownTitleMap = {
        education: 'Tingkat Pendidikan',
        age: 'Kelompok Usia',
        gender: 'Komposisi Gender',
        swimming: 'Kemampuan Renang',
        retirement: 'Proyeksi Pensiun',
        status: 'Status Kepegawaian',
        promotion: 'Proyeksi Kenaikan Pangkat',
        salary_raise: 'Proyeksi Kenaikan Gaji Berkala (KGB)',
        unit: 'Distribusi Unit Kerja',
    };

    /* keamanan isi tabel: data pegawai di-escape sebelum masuk template */
    function drilldownEsc(v) {
        return String(v ?? '')
            .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    }

    /* state popup: kategori, nilai, pencarian & halaman aktif
       (paginasi + pencarian nama/NIP/jabatan — Catatan Masukan 23 Okt 2026) */
    const drilldownState = { category: '', value: '', search: '', page: 1, lastPage: 1 };
    let drilldownRequest = 0; /* respons fetch lama diabaikan (race condition) */

    /* tombol halaman ringkas: 1 … (p-1) p (p+1) … terakhir */
    function drilldownPaginationLinks(page, lastPage) {
        const pages = [];
        const add = (p) => { if (p >= 1 && p <= lastPage && !pages.includes(p)) pages.push(p); };
        add(1); add(page - 1); add(page); add(page + 1); add(lastPage);
        pages.sort((a, b) => a - b);

        let html = '<li class="page-item ' + (page <= 1 ? 'disabled' : '') + '">' +
            '<a class="page-link" href="#" data-page="' + (page - 1) + '" aria-label="Halaman sebelumnya">&laquo;</a></li>';

        let prev = 0;
        pages.forEach((p) => {
            if (p - prev > 1) html += '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
            html += '<li class="page-item ' + (p === page ? 'active' : '') + '">' +
                '<a class="page-link" href="#" data-page="' + p + '">' + p + '</a></li>';
            prev = p;
        });

        html += '<li class="page-item ' + (page >= lastPage ? 'disabled' : '') + '">' +
            '<a class="page-link" href="#" data-page="' + (page + 1) + '" aria-label="Halaman berikutnya">&raquo;</a></li>';
        return html;
    }

    function loadDrilldown() {
        if (!drilldownModal) return;

        const body = document.getElementById('drilldownBody');
        const empty = document.getElementById('drilldownEmpty');
        const footer = document.getElementById('drilldownFooter');
        const total = document.getElementById('drilldownTotal');

        body.innerHTML = '<tr><td colspan="6" class="text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span>Memuat...</td></tr>';
        empty.style.display = 'none';
        footer.style.display = 'none';
        total.textContent = '...';

        // filter dashboard aktif ikut dikirim supaya daftar sesuai grafik;
        // `q` = pencarian popup (nama/NIP/jabatan), `page` = halaman aktif
        const params = new URLSearchParams(window.location.search);
        params.set('category', drilldownState.category);
        params.set('value', drilldownState.value);
        params.set('q', drilldownState.search);
        params.set('page', drilldownState.page);

        const reqId = ++drilldownRequest;

        fetch(drilldownUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
            .then((res) => res.json())
            .then((data) => {
                if (reqId !== drilldownRequest) return; /* sudah ada request baru */

                total.textContent = data.total + ' pegawai';
                const offset = (data.page - 1) * data.per_page;

                if (!data.items.length) {
                    body.innerHTML = '';
                    empty.textContent = drilldownState.search
                        ? 'Tidak ada pegawai yang cocok dengan pencarian "' + drilldownState.search + '".'
                        : 'Tidak ada pegawai pada kategori ini.';
                    empty.style.display = 'block';
                    return;
                }

                body.innerHTML = data.items.map((p, i) => `
                    <tr>
                        <td class="text-muted">${offset + i + 1}</td>
                        <td><a href="{{ url('/employees') }}/${p.id}" class="fw-semibold text-decoration-none">${drilldownEsc(p.name)}</a></td>
                        <td class="text-nowrap">${drilldownEsc(p.nip || '-')}</td>
                        <td>${drilldownEsc(p.position || '-')}</td>
                        <td>${drilldownEsc(p.unit || '-')}</td>
                        <td>${drilldownEsc(p.status || '-')}</td>
                    </tr>`).join('');

                /* info + tombol paginasi (maks. 10 baris per halaman) */
                document.getElementById('drilldownInfo').textContent =
                    'Menampilkan ' + (offset + 1) + '\u2013' + (offset + data.items.length) +
                    ' dari ' + data.total + ' pegawai';
                document.getElementById('drilldownPagination').innerHTML =
                    drilldownPaginationLinks(data.page, data.last_page);
                drilldownState.lastPage = data.last_page;
                footer.style.display = 'flex';
            })
            .catch(() => {
                if (reqId !== drilldownRequest) return;
                body.innerHTML = '';
                empty.textContent = 'Gagal memuat data pegawai.';
                empty.style.display = 'block';
            });
    }

    function openDrilldown(category, value) {
        if (!drilldownModal || value === null || value === undefined || value === '') return;

        document.getElementById('drilldownModalTitle').textContent =
            (drilldownTitleMap[category] || 'Daftar Pegawai') + ' — ' + value;

        /* tiap popup dibuka: pencarian & halaman dikembalikan ke awal */
        drilldownState.category = category;
        drilldownState.value = value;
        drilldownState.search = '';
        drilldownState.page = 1;
        const searchInput = document.getElementById('drilldownSearch');
        if (searchInput) searchInput.value = '';

        drilldownModal.show();
        loadDrilldown();
    }

    /* pencarian popup — debounce 300ms; Enter langsung; kembali ke hal. 1 */
    const drilldownSearchInput = document.getElementById('drilldownSearch');
    let drilldownSearchTimer = null;
    if (drilldownSearchInput) {
        drilldownSearchInput.addEventListener('input', function () {
            clearTimeout(drilldownSearchTimer);
            drilldownSearchTimer = setTimeout(() => {
                drilldownState.search = this.value.trim();
                drilldownState.page = 1;
                loadDrilldown();
            }, 300);
        });
        drilldownSearchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(drilldownSearchTimer);
                drilldownState.search = this.value.trim();
                drilldownState.page = 1;
                loadDrilldown();
            }
        });
    }
    const drilldownSearchClear = document.getElementById('drilldownSearchClear');
    if (drilldownSearchClear) {
        drilldownSearchClear.addEventListener('click', function () {
            if (drilldownSearchInput) drilldownSearchInput.value = '';
            drilldownState.search = '';
            drilldownState.page = 1;
            loadDrilldown();
        });
    }

    /* klik nomor halaman / panah paginasi popup */
    const drilldownPaginationEl = document.getElementById('drilldownPagination');
    if (drilldownPaginationEl) {
        drilldownPaginationEl.addEventListener('click', function (e) {
            const link = e.target.closest('a.page-link');
            if (!link) return;
            e.preventDefault();

            const target = parseInt(link.dataset.page, 10);
            if (!target || target < 1 || target > drilldownState.lastPage || target === drilldownState.page) return;

            drilldownState.page = target;
            loadDrilldown();

            /* ikut geser tabel popup kembali ke atas */
            const scrollEl = drilldownModalEl ? drilldownModalEl.querySelector('.table-responsive') : null;
            if (scrollEl) scrollEl.scrollTop = 0;
        });
    }

    /* handler klik seragam untuk semua chart dashboard */
    const drilldownClick = (category) => function (event, elements, chart) {
        if (!elements.length) return;

        const index = elements[0].index;
        const value = chart.data.labels[index];
        openDrilldown(category, value);
    };

    /* klik angka distribusi unit kerja (list) */
    document.addEventListener('click', function (event) {
        const btn = event.target.closest('.unit-drill-btn');
        if (btn) openDrilldown('unit', btn.dataset.unit);
    });

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

    /* ================== HELPER ANTI-GAGAL ==================
       Satu chart yang error tidak boleh mematikan chart lain —
       tiap pembuatan chart dibungkus try/catch. */
    function safeChart(id, config) {
        try {
            new Chart(document.getElementById(id), config);
        } catch (e) {
            console.warn('Chart "' + id + '" gagal dirender:', e);
        }
    }

    /* Chart Tingkat Pendidikan (bar horizontal) */
    safeChart('educationChart', {
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
            onClick: drilldownClick('education'),
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    /* Chart Distribusi Usia (bar horizontal) */
    safeChart('ageChart', {
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
            onClick: drilldownClick('age'),
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    /* Chart Proyeksi Pensiun (DIAGRAM GARIS / LINE) */
    safeChart('retirementChart', {
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
            onClick: drilldownClick('retirement'),
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    /* Chart Proyeksi Kenaikan Pangkat (DIAGRAM GARIS / LINE) */
    safeChart('promotionChart', {
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
            onClick: drilldownClick('promotion'),
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    /* Chart Gender (DONUT) */
    safeChart('genderChart', {
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
            onClick: drilldownClick('gender'),
            plugins: {
                legend: { display: true, position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 12 } }
            }
        }
    });

    /* Chart Kemampuan Berenang (DONUT) */
    safeChart('swimmingChart', {
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
            onClick: drilldownClick('swimming'),
            plugins: {
                legend: { display: true, position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 12 } }
            }
        }
    });

    /* Chart Proyeksi Kenaikan Gaji Berkala / KGB (DIAGRAM GARIS / LINE) */
    safeChart('salaryRaiseChart', {
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
            onClick: drilldownClick('salary_raise'),
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    /* Chart Status Kepegawaian (DONUT) */
    safeChart('statusChart', {
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
            onClick: drilldownClick('status'),
            plugins: {
                legend: { display: true, position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 10 } }
            }
        }
    });

    /* ================== ULANG TAHUN PEGAWAI (21 Okt 2026) ==================
       Daftar (maks 5 item + paginasi) / grafik per bulan — AJAX agar tetap
       mengikuti filter dashboard yang sedang aktif. */
    (function () {
        const card = document.getElementById('birthdayCard');
        if (!card) return;

        const birthdaysUrl = @js(route('dashboard.birthdays'));
        const listEl = document.getElementById('birthdayList');
        const metaEl = document.getElementById('birthdayMeta');
        const prevBtn = document.getElementById('birthdayPrev');
        const nextBtn = document.getElementById('birthdayNext');
        const periodEl = document.getElementById('birthdayPeriod');
        const modeEl = document.getElementById('birthdayMode');
        const listWrap = document.getElementById('birthdayListWrap');
        const chartWrap = document.getElementById('birthdayChartWrap');
        const chipEl = document.getElementById('birthdayMonthChip');
        const chipLabel = document.getElementById('birthdayMonthLabel');

        let page = 1;
        let month = null;
        let lastData = { total: 0, page: 1, last_page: 1, items: [], monthly: [] };

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text == null ? '' : String(text);
            return div.innerHTML;
        }

        function badge(p) {
            if (p.days_left === 0) return '<span class="badge bg-danger-subtle text-danger">Hari ini</span>';
            if (p.days_left <= 7) return '<span class="badge bg-warning-subtle text-warning-emphasis">' + p.days_left + ' hari lagi</span>';
            return '<span class="badge bg-light text-dark border">' + p.days_left + ' hari lagi</span>';
        }

        function renderList(data) {
            lastData = data;

            listEl.innerHTML = data.items.length
                ? data.items.map((p) => `
                    <li class="list-group-item px-0 d-flex align-items-center gap-2">
                        <span class="holder-avatar flex-shrink-0">${escapeHtml((p.name || '?').split(' ').map((w) => w.charAt(0)).slice(0, 2).join('').toUpperCase())}</span>
                        <div class="min-width-0 flex-grow-1">
                            <a href="{{ url('/employees') }}/${p.id}" class="fw-semibold text-decoration-none d-block text-truncate">${escapeHtml(p.name)}</a>
                            <small class="text-muted d-block text-truncate">${escapeHtml(p.unit)}</small>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <span class="d-block small fw-semibold">${escapeHtml(p.date)}</span>
                            <small class="text-muted">-${p.turning_age} th &middot; ${badge(p)}</small>
                        </div>
                    </li>`).join('')
                : '<li class="list-group-item text-center text-muted py-4">Tidak ada pegawai berulang tahun pada periode ini.</li>';

            const from = data.total ? (data.page - 1) * data.per_page + 1 : 0;
            const to = Math.min(data.page * data.per_page, data.total);
            metaEl.textContent = data.total ? 'Menampilkan ' + from + '\u2013' + to + ' dari ' + data.total : ' ';

            prevBtn.disabled = data.page <= 1;
            nextBtn.disabled = data.page >= data.last_page;
            prevBtn.classList.toggle('disabled', data.page <= 1);
            nextBtn.classList.toggle('disabled', data.page >= data.last_page);
        }

        let bchart = null;

        function renderChart(monthly) {
            const ctx = document.getElementById('birthdayChart');

            if (bchart) bchart.destroy();

            try {
                bchart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: monthly.map((m) => m.short),
                    datasets: [{
                        label: 'Pegawai berulang tahun',
                        data: monthly.map((m) => m.total),
                        backgroundColor: themeAccent,
                        borderRadius: 5
                    }]
                },
                options: {
                    ...baseOptions,
                    onClick: (event, elements) => {
                        if (!elements.length) return;

                        // klik batang bulan -> tampilkan daftar pegawai bulan tsb.
                        month = monthly[elements[0].index].month;
                        chipLabel.textContent = 'Bulan: ' + monthly[elements[0].index].label;
                        chipEl.style.display = 'block';
                        modeEl.value = 'list';
                        syncMode();
                        load(1);
                    },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
                });
            } catch (e) {
                console.warn('Chart "birthdayChart" gagal dirender:', e);
            }
        }

        function syncMode() {
            const chart = modeEl.value === 'chart';
            listWrap.style.display = chart ? 'none' : 'block';
            chartWrap.style.display = chart ? 'block' : 'none';
            if (chart) renderChart(lastData.monthly || []);
        }

        function load(targetPage) {
            page = targetPage || 1;

            const params = new URLSearchParams(window.location.search);
            params.set('months', periodEl.value);
            params.set('page', page);
            if (month !== null) params.set('month', month);
            else params.delete('month');
            params.delete('category');
            params.delete('value');

            metaEl.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            fetch(birthdaysUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
                .then((res) => res.json())
                .then((data) => {
                    renderList(data);
                    if (modeEl.value === 'chart') renderChart(data.monthly || []);
                })
                .catch(() => {
                    metaEl.textContent = 'Gagal memuat data.';
                });
        }

        prevBtn.addEventListener('click', () => { if (page > 1) load(page - 1); });
        nextBtn.addEventListener('click', () => { if (page < lastData.last_page) load(page + 1); });
        periodEl.addEventListener('change', () => { month = null; chipEl.style.display = 'none'; load(1); });
        modeEl.addEventListener('change', syncMode);
        document.getElementById('birthdayMonthReset').addEventListener('click', () => {
            month = null;
            chipEl.style.display = 'none';
            load(1);
        });

        load(1);
    })();
    } /* end if typeof Chart !== 'undefined' */
</script>
@endpush

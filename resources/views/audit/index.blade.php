@extends('layouts.app')

@section('page_title', 'Audit Log & Statistik Pengunjung')
@section('page_subtitle', 'Rekam jejak aktivitas pengguna dan statistik kunjungan aplikasi')

@section('content')

{{-- ================= STATISTIC CARDS ================= --}}
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
            <div>
                <span>Pengunjung Hari Ini</span>
                <h2>{{ number_format($stats['today']) }}</h2>
                <small>{{ number_format($stats['uniqueToday']) }} pengguna berbeda</small>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon accent"><i class="bi bi-calendar-week"></i></div>
            <div>
                <span>Kunjungan Minggu Ini</span>
                <h2>{{ number_format($stats['week']) }}</h2>
                <small>halaman diakses</small>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon success"><i class="bi bi-calendar-month"></i></div>
            <div>
                <span>Kunjungan Bulan Ini</span>
                <h2>{{ number_format($stats['month']) }}</h2>
                <small>halaman diakses</small>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon danger"><i class="bi bi-bar-chart-line"></i></div>
            <div>
                <span>Total Kunjungan</span>
                <h2>{{ number_format($stats['total']) }}</h2>
                <small>sepanjang waktu</small>
            </div>
        </div>
    </div>
</div>

{{-- ================= FILTER ================= --}}
<div class="filter-card mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-lg-3 col-md-6">
            <label>Pencarian</label>
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Nama / keterangan / IP..." value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <label>Aktivitas</label>
            <select name="event" class="form-select form-select-sm">
                <option value="">Semua</option>
                @foreach ($events as $value => $label)
                    <option value="{{ $value }}" {{ ($filters['event'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-3 col-6">
            <label>Dari Tanggal</label>
            <input type="date" name="start" class="form-control form-control-sm" value="{{ $filters['start'] ?? '' }}">
        </div>
        <div class="col-lg-2 col-md-3 col-6">
            <label>Sampai Tanggal</label>
            <input type="date" name="end" class="form-control form-control-sm" value="{{ $filters['end'] ?? '' }}">
        </div>
        <div class="col-lg-3 d-flex gap-2 justify-content-end">
            <a href="{{ route('audit.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-counterclockwise"></i>
            </a>
            <div class="dropdown export-btn">
                <button class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-download"></i> Export
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" data-no-loader
                           href="{{ route('audit.export', ['format' => 'xlsx'] + $filters) }}">
                            <i class="bi bi-file-earmark-excel text-success"></i> Excel (.xlsx)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" data-no-loader
                           href="{{ route('audit.export', ['format' => 'pdf'] + $filters) }}">
                            <i class="bi bi-file-earmark-pdf text-danger"></i> PDF
                        </a>
                    </li>
                </ul>
            </div>
            <button class="btn btn-osdmrb btn-sm px-3"><i class="bi bi-search"></i></button>
        </div>
    </form>
</div>

{{-- ================= TABEL LOG ================= --}}
<div class="table-card">
    <div class="card-header-custom">
        <h5 class="mb-0">Rekam Aktivitas ({{ $logs->total() }})</h5>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Pengguna</th>
                    <th>Aktivitas</th>
                    <th>Modul</th>
                    <th>Keterangan</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-nowrap"><small>{{ $log->created_at->setTimezone(config('app.timezone'))->translatedFormat('d M Y H:i') }}</small></td>
                        <td class="fw-semibold">{{ $log->user_name ?? 'Tamu' }}</td>
                        <td><span class="badge bg-{{ $log->event_badge }}">{{ $log->event_label }}</span></td>
                        <td><small>{{ $log->module ?? '-' }}</small></td>
                        <td><small>{{ Str::limit($log->description, 70) }}</small></td>
                        <td><small>{{ $log->ip_address ?? '-' }}</small></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada aktivitas tercatat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $logs->links() }}</div>
</div>

@endsection

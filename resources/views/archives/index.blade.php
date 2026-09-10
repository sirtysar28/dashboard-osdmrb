@extends('layouts.app')

@section('page_title', 'Kearsipan')
@section('page_subtitle', 'Katalog dokumen arsip & layanan kearsipan instansi')

@section('content')

{{-- ================= STATISTIK ================= --}}
@if (isset($stats))
    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-md-4 col-6">
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-archive-fill"></i></div>
                <div><span>Total Arsip</span><h2>{{ number_format($stats['total']) }}</h2></div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="stat-card">
                <div class="stat-icon success"><i class="bi bi-check2-circle"></i></div>
                <div><span>Tersedia</span><h2>{{ number_format($stats['available']) }}</h2></div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="stat-card">
                <div class="stat-icon accent"><i class="bi bi-box-arrow-up-right"></i></div>
                <div><span>Dipinjam</span><h2>{{ number_format($stats['borrowed']) }}</h2></div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-folder2-open"></i></div>
                <div><span>Inaktif</span><h2>{{ number_format($stats['inactive']) }}</h2></div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="stat-card">
                <div class="stat-icon success"><i class="bi bi-hourglass-bottom"></i></div>
                <div><span>Permanen</span><h2>{{ number_format($stats['permanent']) }}</h2></div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="stat-card">
                <div class="stat-icon danger"><i class="bi bi-hourglass-split"></i></div>
                <div><span>Permintaan Pinjam</span><h2>{{ number_format($stats['loansPending']) }}</h2></div>
            </div>
        </div>
    </div>
@elseif (isset($myLoanStats))
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="stat-card">
                <div class="stat-icon accent"><i class="bi bi-hourglass-split"></i></div>
                <div><span>Pengajuan Menunggu</span><h2>{{ $myLoanStats['pending'] }}</h2></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-box-arrow-up-right"></i></div>
                <div><span>Sedang Dipinjam</span><h2>{{ $myLoanStats['approved'] }}</h2></div>
            </div>
        </div>
    </div>
@endif

{{-- ================= FILTER ================= --}}
<div class="filter-card mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-lg-4 col-md-6">
            <label>Pencarian</label>
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Nomor arsip / judul / uraian..." value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="col-lg-2 col-md-3 col-6">
            <label>Klasifikasi</label>
            <select name="category" class="form-select form-select-sm">
                <option value="">Semua</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" {{ ($filters['category'] ?? '') == $category->id ? 'selected' : '' }}>
                        {{ $category->code }} — {{ $category->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-3 col-6">
            <label>Jenis</label>
            <select name="type" class="form-select form-select-sm">
                <option value="">Semua</option>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" {{ ($filters['type'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-3 col-6">
            <label>Tahun</label>
            <select name="year" class="form-select form-select-sm">
                <option value="">Semua</option>
                @foreach ($years as $year)
                    <option value="{{ $year }}" {{ ($filters['year'] ?? '') == $year ? 'selected' : '' }}>{{ $year }}</option>
                @endforeach
            </select>
        </div>
        @if (auth()->user()->isPrivileged())
            <div class="col-lg-2 col-md-3 col-6">
                <label>Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="col-lg-12 d-flex gap-2 justify-content-end">
            <a href="{{ route('archives.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-counterclockwise"></i> Reset
            </a>
            <button class="btn btn-osdmrb btn-sm px-4"><i class="bi bi-search"></i> Cari</button>
        </div>
    </form>
</div>

{{-- ================= TOMBOL TAMBAH + EXPORT ================= --}}
<div class="d-flex justify-content-end mb-3 gap-2 flex-wrap export-btn">
    <div class="dropdown">
        <button class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-download"></i> Export
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li>
                <a class="dropdown-item" data-no-loader href="{{ route('archives.export', ['format' => 'xlsx'] + $filters) }}">
                    <i class="bi bi-file-earmark-excel text-success"></i> Excel (.xlsx)
                </a>
            </li>
            <li>
                <a class="dropdown-item" data-no-loader href="{{ route('archives.export', ['format' => 'pdf'] + $filters) }}">
                    <i class="bi bi-file-earmark-pdf text-danger"></i> PDF
                </a>
            </li>
        </ul>
    </div>

    @if (auth()->user()->isPrivileged())
        <a href="{{ route('archives.create') }}" class="btn btn-osdmrb btn-sm">
            <i class="bi bi-plus-lg"></i> Tambah Dokumen Arsip
        </a>
    @endif
</div>

{{-- ================= GRID ARSIP ================= --}}
<div class="row g-3">
    @forelse ($archives as $archive)
        <div class="col-xl-4 col-md-6">
            <div class="chart-card d-flex flex-column h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge {{ $archive->category ? 'bg-primary' : 'bg-secondary' }}">
                        {{ $archive->category?->code ?? 'UMUM' }}
                    </span>
                    <span class="badge bg-{{ $archive->status_badge }}">{{ $archive->status_label }}</span>
                </div>

                <h6 class="fw-bold mb-1" style="font-size:14px; color:#143647">{{ $archive->title }}</h6>
                <code class="small mb-2">{{ $archive->archive_number }}</code>

                <p class="text-muted small mb-2 flex-grow-1">
                    {{ Str::limit($archive->description, 100) }}
                </p>

                <div class="small text-muted mb-2">
                    <div><i class="bi bi-folder2 me-1"></i>{{ $archive->type_label }}</div>
                    <div><i class="bi bi-calendar3 me-1"></i>{{ $archive->document_date?->translatedFormat('d F Y') ?? '-' }}
                        ({{ $archive->year ?? '-' }})</div>
                    @if ($archive->employee)
                        <div><i class="bi bi-person me-1"></i>{{ $archive->employee->name }}</div>
                    @endif
                    <div><i class="bi bi-hourglass me-1"></i>{{ $archive->retention_label }}</div>
                </div>

                <div class="d-flex gap-2">
                    <a href="{{ route('archives.show', $archive) }}" class="btn btn-sm btn-outline-osdmrb flex-fill">
                        <i class="bi bi-eye"></i> Detail
                    </a>
                    @if ($archive->loans_count > 0)
                        <span class="badge bg-warning align-self-center">{{ $archive->loans_count }} pinjam</span>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="chart-card text-center text-muted py-5">
                <i class="bi bi-archive" style="font-size:40px"></i>
                <p class="mt-2 mb-0">Tidak ada dokumen arsip yang cocok dengan pencarian.</p>
            </div>
        </div>
    @endforelse
    </div>

    <div class="mt-3">{{ $archives->links() }}</div>

@endsection

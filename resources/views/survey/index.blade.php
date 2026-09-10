@extends('layouts.app')

@section('page_title', 'Survei & Masukan')
@section('page_subtitle', 'Hasil survei kepuasan serta masukan & saran pengguna aplikasi')

@section('content')

{{-- ================= STATISTIC CARDS ================= --}}
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-chat-square-heart"></i></div>
            <div>
                <span>Total Responden</span>
                <h2>{{ number_format($stats['total']) }}</h2>
                <small>pengguna mengisi survei</small>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon accent"><i class="bi bi-star"></i></div>
            <div>
                <span>Rating Rata-rata</span>
                <h2>{{ $stats['average'] ?: '-' }} <small style="font-size:13px">/ 5</small></h2>
                <small>bintang</small>
            </div>
        </div>
    </div>
    <div class="col-xl-6 col-md-12">
        <div class="chart-card h-100 d-flex flex-column justify-content-center">
            <h5 class="mb-3"><i class="bi bi-bar-chart me-2"></i>Distribusi Rating</h5>
            @foreach ($stats['distribution'] as $star => $count)
                @php($max = max(1, max($stats['distribution'])))
                <div class="d-flex align-items-center gap-2 mb-1">
                    <small style="width:64px">{{ $star }} <i class="bi bi-star-fill text-warning"></i></small>
                    <div class="progress flex-fill" style="height:8px">
                        <div class="progress-bar" style="width: {{ round($count / $max * 100) }}%; background: var(--osdmrb-primary)"></div>
                    </div>
                    <small class="text-muted" style="width:34px" class="text-end">{{ $count }}</small>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ================= FILTER ================= --}}
<div class="filter-card mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-lg-4 col-md-6">
            <label>Pencarian</label>
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Nama / isi masukan..." value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="col-lg-3 col-md-4 col-6">
            <label>Rating</label>
            <select name="rating" class="form-select form-select-sm">
                <option value="">Semua</option>
                @foreach (range(5, 1) as $star)
                    <option value="{{ $star }}" {{ ($filters['rating'] ?? '') == $star ? 'selected' : '' }}>
                        {{ $star }} bintang
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-5 d-flex justify-content-end gap-2">
            <a href="{{ route('surveys.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-counterclockwise"></i>
            </a>
            <button class="btn btn-osdmrb btn-sm px-3"><i class="bi bi-search"></i></button>
        </div>
    </form>
</div>

{{-- ================= TABEL ================= --}}
<div class="table-card">
    <div class="card-header-custom">
        <h5 class="mb-0">Masukan &amp; Saran Terbaru ({{ $surveys->total() }})</h5>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Pengguna</th>
                    <th>Rating</th>
                    <th>Masukan &amp; Saran</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($surveys as $survey)
                    <tr>
                        <td class="text-nowrap"><small>{{ $survey->created_at->setTimezone(config('app.timezone'))->translatedFormat('d M Y H:i') }}</small></td>
                        <td class="fw-semibold">{{ $survey->user_name ?? 'Anonim' }}</td>
                        <td class="text-nowrap">
                            @for ($i = 1; $i <= 5; $i++)
                                <i class="bi {{ $i <= $survey->rating ? 'bi-star-fill text-warning' : 'bi-star text-muted' }}"></i>
                            @endfor
                        </td>
                        <td style="max-width:420px"><small>{{ $survey->message ?: '-' }}</small></td>
                        <td class="text-center">
                            <form action="{{ route('surveys.destroy', $survey) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('Hapus jawaban survei ini?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada jawaban survei.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $surveys->links() }}</div>
</div>

@endsection

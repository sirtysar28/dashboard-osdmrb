@extends('layouts.app')

@section('page_title', 'Beranda Pegawai')
@section('page_subtitle', 'Layanan kepegawaian & persuratan Anda')

@section('content')

{{-- ================= PENGUMUMAN (paling atas semua dashboard) ================= --}}
<x-announcement-banner :announcements="$announcements" :announcement="$announcement" />

{{-- ================= RINGKASAN PENGAJUAN ================= --}}
@if ($menuLetters)
<div class="row g-3 mb-4">
    <div class="col-lg-3 col-md-6 col-6">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-envelope-paper"></i></div>
            <div><span>Total Pengajuan</span><h2>{{ $letterStats['total'] }}</h2></div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-6">
        <div class="stat-card">
            <div class="stat-icon accent"><i class="bi bi-hourglass-split"></i></div>
            <div><span>Menunggu</span><h2>{{ $letterStats['pending'] }}</h2></div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-6">
        <div class="stat-card">
            <div class="stat-icon success"><i class="bi bi-patch-check"></i></div>
            <div><span>Disetujui</span><h2>{{ $letterStats['approved'] }}</h2></div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-6">
        <div class="stat-card">
            <div class="stat-icon danger"><i class="bi bi-x-circle"></i></div>
            <div><span>Ditolak</span><h2>{{ $letterStats['rejected'] }}</h2></div>
        </div>
    </div>
</div>
@endif

<div class="row g-3">

    <!-- ================= PROFIL KERJA ================= -->
    @if ($employee)
        <div class="col-lg-5">
            <div class="chart-card mb-3">
                <h5><i class="bi bi-person-vcard me-2"></i>Profil Kepegawaian</h5>
                <table class="table table-sm mb-0" style="font-size:13.5px">
                    <tr><td class="text-muted" style="width:170px">Nama</td><td class="fw-semibold">{{ $employee->name }}</td></tr>
                    <tr><td class="text-muted">NIP</td><td>{{ $employee->nip }}</td></tr>
                    <tr><td class="text-muted">Status</td><td>{{ $employee->employmentStatus?->name ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Golongan</td><td>{{ $employee->rank?->code ?? '-' }} {{ $employee->rank?->name ? '— ' . $employee->rank->name : '' }}</td></tr>
                    <tr><td class="text-muted">Jabatan</td><td>{{ $employee->position_name ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Unit Kerja</td><td>{{ $employee->unit?->name ?? '-' }}</td></tr>
                    <tr><td class="text-muted">TMT Golongan</td><td>{{ $employee->tmt_golongan?->translatedFormat('d F Y') ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Batas Pensiun</td><td>{{ $employee->retirement_date?->translatedFormat('d F Y') ?? '-' }}</td></tr>
                </table>
            </div>

            <div class="chart-card">
                <h5><i class="bi bi-lightning-charge me-2"></i>Layanan Cepat</h5>
                <div class="d-grid gap-2">
                    @if ($menuLetters)
                        <a href="{{ route('letters.create') }}" class="btn btn-osdmrb">
                            <i class="bi bi-file-earmark-plus"></i> Ajukan Surat Baru
                        </a>
                        <a href="{{ route('letters.index') }}" class="btn btn-outline-osdmrb">
                            <i class="bi bi-envelope-paper"></i> Riwayat Persuratan
                        </a>
                    @endif
                    @if ($menuArchives)
                        <a href="{{ route('archives.index') }}" class="btn btn-outline-osdmrb">
                            <i class="bi bi-archive"></i> Katalog Kearsipan
                        </a>
                        <a href="{{ route('archive-loans.index') }}" class="btn btn-outline-osdmrb">
                            <i class="bi bi-box-arrow-up-right"></i> Peminjaman Arsip Saya
                        </a>
                    @endif
                    @unless ($menuLetters || $menuArchives)
                        <p class="text-muted small mb-0">Layanan persuratan &amp; kearsipan sedang tidak aktif.</p>
                    @endunless
                </div>
            </div>
        </div>
    @endif

    <!-- ================= PENGAJUAN TERAKHIR / SURVEI ================= -->
    @if ($menuLetters)
        <div class="col-lg-{{ $employee ? 4 : 8 }}">
            <div class="table-card h-100">
                <div class="card-header-custom">
                    <h5 class="mb-0">Pengajuan Terakhir</h5>
                    <a href="{{ route('letters.index') }}" class="btn btn-sm btn-outline-osdmrb">Lihat Semua</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>Nomor</th><th>Jenis</th><th>Status</th><th class="text-center">Aksi</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($letters as $letter)
                                <tr>
                                    <td><code>{{ $letter->number ?? '-' }}</code></td>
                                    <td>{{ $letter->letterType?->name }}</td>
                                    <td><span class="badge bg-{{ $letter->status_badge }}">{{ $letter->status_label }}</span></td>
                                    <td class="text-center">
                                        <a href="{{ route('letters.show', $letter) }}" class="btn btn-sm btn-outline-osdmrb"><i class="bi bi-eye"></i></a>
                                        @if ($letter->status === \App\Models\Letter::STATUS_APPROVED)
                                            <a href="{{ route('letters.print', $letter) }}" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-printer"></i></a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">Belum ada pengajuan surat.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="col-lg-{{ $menuLetters ? ($employee ? 3 : 4) : ($employee ? 7 : 12) }}">
        <x-survey-form />
    </div>
</div>

@endsection

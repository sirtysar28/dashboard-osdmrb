@extends('layouts.app')

@section('page_title', 'Detail Arsip')
@section('page_subtitle', $archive->title)

@section('content')

<div class="row g-3">

    <div class="col-lg-7">

        <div class="detail-card mb-3">
            <div class="card-header-custom">
                <h5 class="mb-0"><i class="bi bi-archive me-2"></i>{{ $archive->archive_number }}</h5>
                <div>
                    <span class="badge bg-{{ $archive->status_badge }}">{{ $archive->status_label }}</span>
                    <span class="badge {{ $archive->visibility === 'INTERNAL' ? 'bg-danger' : 'bg-primary' }}">
                        {{ $archive->visibility === 'INTERNAL' ? 'Internal' : 'Publik' }}
                    </span>
                </div>
            </div>

            <table class="table table-sm mb-0" style="font-size:13.5px">
                <tr><td class="text-muted" style="width:190px">Judul / Uraian</td><td class="fw-semibold">{{ $archive->title }}</td></tr>
                <tr><td class="text-muted">Keterangan</td><td>{{ $archive->description ?? '-' }}</td></tr>
                <tr><td class="text-muted">Klasifikasi</td><td>{{ $archive->category ? $archive->category->code.' — '.$archive->category->name : '-' }}</td></tr>
                <tr><td class="text-muted">Jenis Dokumen</td><td>{{ $archive->type_label }}</td></tr>
                <tr><td class="text-muted">Tanggal Dokumen</td><td>{{ $archive->document_date?->translatedFormat('d F Y') ?? '-' }} ({{ $archive->year ?? '-' }})</td></tr>
                <tr><td class="text-muted">Retensi</td><td>{{ $archive->retention_label }}
                    @if ($archive->retention_years) — {{ $archive->retention_years }} tahun @endif</td></tr>
                <tr><td class="text-muted">Batas Simpan</td><td>{{ $archive->retention_until?->translatedFormat('d F Y') ?? '-' }}</td></tr>
                <tr><td class="text-muted">Lokasi Fisik</td><td>{{ $archive->physical_location ?? '-' }}</td></tr>
                @if ($archive->employee)
                    <tr><td class="text-muted">Pegawai Terkait</td>
                        <td><a href="{{ route('employees.show', $archive->employee) }}" class="text-decoration-none">{{ $archive->employee->name }}</a></td></tr>
                @endif
                @if ($archive->unit)
                    <tr><td class="text-muted">Unit Pengolah</td><td>{{ $archive->unit->name }}</td></tr>
                @endif
                @if ($archive->letter)
                    <tr><td class="text-muted">Terkait Surat</td>
                        <td><a href="{{ route('letters.show', $archive->letter) }}" class="text-decoration-none">
                            {{ $archive->letter->number }} — {{ $archive->letter->subject }}</a></td></tr>
                @endif
                <tr><td class="text-muted">Salinan Digital</td>
                    <td>
                        @if ($archive->file_path)
                            <a data-no-loader href="{{ route('archives.download', $archive) }}" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-download"></i> {{ $archive->file_name ?? 'Unduh' }}
                            </a>
                        @else
                            <span class="text-muted">Tidak tersedia</span>
                        @endif
                    </td></tr>
                <tr><td class="text-muted">Diarsipkan oleh</td>
                    <td>{{ $archive->uploader?->name ?? 'Otomatis' }},
                        {{ $archive->created_at->translatedFormat('d F Y H:i') }}</td></tr>
            </table>
        </div>

        {{-- ================= RIWAYAT PEMINJAMAN ================= --}}
        <div class="detail-card">
            <h5><i class="bi bi-clock-history me-2"></i>Riwayat Peminjaman</h5>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr><th>Peminjam</th><th>Tgl Pinjam</th><th>Batas Kembali</th><th>Dikembalikan</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($archive->loans as $loan)
                            <tr>
                                <td>{{ $loan->employee?->name }}</td>
                                <td>{{ $loan->loan_date->translatedFormat('d/m/Y') }}</td>
                                <td>{{ $loan->due_date->translatedFormat('d/m/Y') }}
                                    @if ($loan->is_overdue)<span class="badge bg-danger">terlambat</span>@endif
                                </td>
                                <td>{{ $loan->returned_at?->translatedFormat('d/m/Y') ?? '-' }}</td>
                                <td><span class="badge bg-{{ $loan->status_badge }}">{{ $loan->status_label }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">Belum pernah dipinjam.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">

        {{-- ================= AKSI ADMIN ================= --}}
        @if (auth()->user()->isPrivileged())
            <div class="detail-card mb-3">
                <h5><i class="bi bi-gear me-2"></i>Aksi Admin</h5>
                <div class="d-grid gap-2">
                    <a href="{{ route('archives.edit', $archive) }}" class="btn btn-outline-osdmrb">
                        <i class="bi bi-pencil"></i> Ubah Data Arsip
                    </a>
                    <form action="{{ route('archives.destroy', $archive) }}" method="POST"
                          onsubmit="return confirm('Hapus dokumen arsip ini?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger w-100"><i class="bi bi-trash"></i> Hapus Arsip</button>
                    </form>
                </div>
            </div>
        @endif

        {{-- ================= FORM PEMINJAMAN ================= --}}
        @if (auth()->user()->employee_id)
            <div class="detail-card">
                <h5><i class="bi bi-box-arrow-up-right me-2"></i>Ajukan Peminjaman</h5>

                @if ($archive->is_borrowable && ! $hasActiveLoan)
                    <form method="POST" action="{{ route('archive-loans.store', $archive) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Keperluan <span class="text-danger">*</span></label>
                            <textarea name="purpose" rows="3" class="form-control" required
                                      placeholder="mis. keperluan penyusunan laporan kepegawaian">{{ old('purpose') }}</textarea>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label">Tanggal Pinjam</label>
                                <input type="date" name="loan_date" class="form-control" required
                                       value="{{ old('loan_date', now()->format('Y-m-d')) }}" min="{{ now()->format('Y-m-d') }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Batas Kembali</label>
                                <input type="date" name="due_date" class="form-control" required
                                       value="{{ old('due_date', now()->addDays(7)->format('Y-m-d')) }}" min="{{ now()->format('Y-m-d') }}">
                            </div>
                        </div>
                        <button class="btn btn-osdmrb w-100"><i class="bi bi-send"></i> Ajukan Peminjaman</button>
                    </form>
                @elseif ($hasActiveLoan)
                    <div class="alert alert-warning small py-2 mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Arsip ini sedang diajukan/dipinjam. Pantau statusnya di menu
                        <a href="{{ route('archive-loans.index') }}" class="text-decoration-none">Peminjaman Arsip</a>.
                    </div>
                @else
                    <div class="alert alert-secondary small py-2 mb-0">
                        Arsip berstatus <strong>{{ $archive->status_label }}</strong> sehingga tidak dapat dipinjam saat ini.
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>

@endsection

@extends('layouts.app')

@section('page_title', 'Peminjaman Arsip')
@section('page_subtitle', auth()->user()->isPrivileged() ? 'Kelola seluruh pengajuan peminjaman arsip' : 'Riwayat & status peminjaman arsip Anda')

@section('content')

<div class="filter-card mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-lg-4 col-md-6">
            <label>Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">Semua Status</option>
                @foreach (['PENDING' => 'Menunggu Persetujuan', 'APPROVED' => 'Dipinjam', 'REJECTED' => 'Ditolak', 'RETURNED' => 'Dikembalikan'] as $value => $label)
                    <option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-3">
            <button class="btn btn-osdmrb btn-sm"><i class="bi bi-funnel"></i> Terapkan</button>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="card-header-custom">
        <h5 class="mb-0">Daftar Peminjaman ({{ $loans->total() }})</h5>

        <div class="d-flex gap-2 flex-wrap export-btn">
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-download"></i> Export
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" data-no-loader href="{{ route('archive-loans.export', ['format' => 'xlsx'] + $filters) }}">
                            <i class="bi bi-file-earmark-excel text-success"></i> Excel (.xlsx)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" data-no-loader href="{{ route('archive-loans.export', ['format' => 'pdf'] + $filters) }}">
                            <i class="bi bi-file-earmark-pdf text-danger"></i> PDF
                        </a>
                    </li>
                </ul>
            </div>

            <a href="{{ route('archives.index') }}" class="btn btn-sm btn-outline-osdmrb">
                <i class="bi bi-archive"></i> Katalog Arsip
            </a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Arsip</th>
                    <th>Peminjam</th>
                    <th>Keperluan</th>
                    <th>Tgl Pinjam</th>
                    <th>Batas Kembali</th>
                    <th>Status</th>
                    @if (auth()->user()->isPrivileged())<th class="text-center">Aksi</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($loans as $loan)
                    <tr>
                        <td>
                            <a href="{{ route('archives.show', $loan->archive) }}" class="text-decoration-none fw-semibold">
                                {{ Str::limit($loan->archive->title, 40) }}
                            </a>
                            <br><code class="small">{{ $loan->archive->archive_number }}</code>
                        </td>
                        <td>{{ $loan->employee?->name }}</td>
                        <td style="max-width:220px">{{ Str::limit($loan->purpose, 60) }}</td>
                        <td>{{ $loan->loan_date->translatedFormat('d/m/Y') }}</td>
                        <td>{{ $loan->due_date->translatedFormat('d/m/Y') }}
                            @if ($loan->is_overdue)<span class="badge bg-danger">terlambat</span>@endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $loan->status_badge }}">{{ $loan->status_label }}</span>
                            @if ($loan->note)<br><small class="text-muted fst-italic">{{ Str::limit($loan->note, 40) }}</small>@endif
                        </td>
                        @if (auth()->user()->isPrivileged())
                            <td class="text-center text-nowrap">
                                @if ($loan->status === \App\Models\ArchiveLoan::STATUS_PENDING)
                                    <form action="{{ route('archive-loans.approve', $loan) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-success" title="Setujui"><i class="bi bi-check-lg"></i></button>
                                    </form>
                                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal"
                                            data-bs-target="#rejectModal{{ $loan->id }}" title="Tolak"><i class="bi bi-x-lg"></i></button>
                                @elseif ($loan->status === \App\Models\ArchiveLoan::STATUS_APPROVED)
                                    <form action="{{ route('archive-loans.returned', $loan) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Tandai arsip telah dikembalikan?')">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-success" title="Diterima kembali">
                                            <i class="bi bi-arrow-counterclockwise"></i> Kembali
                                        </button>
                                    </form>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        @endif
                    </tr>

                    {{-- Modal penolakan --}}
                    @if (auth()->user()->isPrivileged() && $loan->status === \App\Models\ArchiveLoan::STATUS_PENDING)
                        <div class="modal fade" id="rejectModal{{ $loan->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <form method="POST" action="{{ route('archive-loans.reject', $loan) }}" class="modal-content">
                                    @csrf
                                    <div class="modal-header">
                                        <h5 class="modal-title small">Tolak Peminjaman — {{ $loan->employee?->name }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <label class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                                        <textarea name="note" rows="3" class="form-control" required></textarea>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                        <button class="btn btn-danger btn-sm">Tolak</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif
                @empty
                    <tr>
                        <td colspan="{{ auth()->user()->isPrivileged() ? 7 : 6 }}" class="text-center text-muted py-4">
                            Belum ada data peminjaman arsip.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $loans->links() }}</div>
</div>

@endsection

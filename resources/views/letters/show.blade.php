@extends('layouts.app')

@section('page_title', 'Detail Surat')
@section('page_subtitle', $letter->subject)

@section('content')

<div class="row g-3">

    <div class="col-lg-8">

        <!-- ================= RINGKASAN ================= -->
        <div class="chart-card mb-3">
            <div class="card-header-custom">
                <h5 class="mb-0"><i class="bi bi-envelope-paper me-2"></i>Informasi Surat</h5>
                <span class="badge bg-{{ $letter->status_badge }} fs-6">{{ $letter->status_label }}</span>
            </div>

            <table class="table table-sm mb-0" style="font-size:13.5px">
                <tr>
                    <td class="text-muted" style="width:200px">Nomor Surat</td>
                    <td><strong>{{ $letter->number ?? '(belum diterbitkan)' }}</strong></td>
                </tr>
                <tr><td class="text-muted">Jenis Surat</td><td>{{ $letter->letterType?->name }}</td></tr>
                <tr><td class="text-muted">Perihal</td><td>{{ $letter->subject }}</td></tr>
                <tr><td class="text-muted">Tanggal Surat</td><td>{{ $letter->letter_date?->translatedFormat('d F Y') ?? '-' }}</td></tr>
                <tr><td class="text-muted">Keperluan / Dasar</td><td>{{ $letter->purpose }}</td></tr>
                @if ($letter->meta['place'] ?? null)
                    <tr><td class="text-muted">Tempat</td><td>{{ $letter->meta['place'] }}</td></tr>
                @endif
                @if ($letter->meta['start_date'] ?? null)
                    <tr><td class="text-muted">Waktu Kegiatan</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($letter->meta['start_date'])->translatedFormat('d F Y') }}
                            @if ($letter->meta['end_date'] ?? null)
                                s.d. {{ \Illuminate\Support\Carbon::parse($letter->meta['end_date'])->translatedFormat('d F Y') }}
                            @endif
                        </td></tr>
                @endif
                @if ($letter->note)
                    <tr><td class="text-muted">Catatan</td><td class="text-danger">{{ $letter->note }}</td></tr>
                @endif
            </table>
        </div>

        <!-- ================= DATA PEMOHON ================= -->
        <div class="chart-card">
            <h5><i class="bi bi-person me-2"></i>Data Pemohon</h5>
            <table class="table table-sm mb-0" style="font-size:13.5px">
                <tr><td class="text-muted" style="width:200px">Nama</td><td class="fw-semibold">{{ $letter->employee?->name }}</td></tr>
                <tr><td class="text-muted">NIP</td><td>{{ $letter->employee?->nip }}</td></tr>
                <tr><td class="text-muted">Pangkat / Golongan</td>
                    <td>{{ $letter->employee?->rank?->name ?? '-' }} {{ $letter->employee?->rank ? '/ ' . $letter->employee->rank->code : '' }}</td></tr>
                <tr><td class="text-muted">Jabatan</td><td>{{ $letter->employee?->position_name ?? '-' }}</td></tr>
                <tr><td class="text-muted">Unit Kerja</td><td>{{ $letter->employee?->unit?->name ?? '-' }}</td></tr>
            </table>
        </div>
    </div>

    <div class="col-lg-4">

        <!-- ================= AKSI WORKFLOW ================= -->
        <div class="chart-card mb-3">
            <h5><i class="bi bi-gear me-2"></i>Aksi</h5>

            <div class="d-grid gap-2">
                @if ($letter->status === \App\Models\Letter::STATUS_APPROVED)
                    <a href="{{ route('letters.print', $letter) }}" target="_blank" class="btn btn-success">
                        <i class="bi bi-printer"></i> Cetak / Unduh PDF
                    </a>
                @endif

                @can('verify letters')
                    @if ($letter->status === \App\Models\Letter::STATUS_PENDING)
                        <form method="POST" action="{{ route('letters.verify', $letter) }}">
                            @csrf
                            <textarea name="note" rows="2" class="form-control mb-2" placeholder="Catatan verifikasi (opsional)"></textarea>
                            <button class="btn btn-osdmrb w-100"><i class="bi bi-check2-circle"></i> Verifikasi Surat</button>
                        </form>
                    @endif
                @endcan

                @can('approve letters')
                    @if ($letter->status === \App\Models\Letter::STATUS_VERIFIED)
                        <form method="POST" action="{{ route('letters.approve', $letter) }}">
                            @csrf
                            <textarea name="note" rows="2" class="form-control mb-2" placeholder="Catatan persetujuan (opsional)"></textarea>
                            <button class="btn btn-success w-100"><i class="bi bi-patch-check"></i> Setujui &amp; Terbitkan Nomor</button>
                        </form>
                    @endif
                @endcan

                @can('verify letters')
                    @if (in_array($letter->status, [\App\Models\Letter::STATUS_PENDING, \App\Models\Letter::STATUS_VERIFIED]))
                        <form method="POST" action="{{ route('letters.reject', $letter) }}"
                              onsubmit="return confirm('Tolak pengajuan surat ini?')">
                            @csrf
                            <textarea name="note" rows="2" class="form-control mb-2" required
                                      placeholder="Alasan penolakan (wajib)"></textarea>
                            <button class="btn btn-outline-danger w-100"><i class="bi bi-x-circle"></i> Tolak Pengajuan</button>
                        </form>
                    @endif
                @endcan

                @if ($letter->status === \App\Models\Letter::STATUS_PENDING && (auth()->user()->employee_id === $letter->employee_id || auth()->user()->isPrivileged()))
                    <form method="POST" action="{{ route('letters.destroy', $letter) }}"
                          onsubmit="return confirm('Batalkan pengajuan ini?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-counterclockwise"></i> Batalkan Pengajuan</button>
                    </form>
                @endif

                <a href="{{ route('letters.index') }}" class="btn btn-link text-decoration-none small">Kembali ke daftar</a>
            </div>
        </div>

        <!-- ================= TIMELINE WORKFLOW ================= -->
        <div class="chart-card">
            <h5><i class="bi bi-clock-history me-2"></i>Riwayat Workflow</h5>
            <ul class="timeline">
                @forelse ($letter->logs as $log)
                    <li class="timeline-item">
                        <div class="d-flex justify-content-between">
                            <strong style="font-size:13px">{{ $log->action_label }}</strong>
                            <small class="text-muted">{{ $log->created_at->translatedFormat('d/m/Y H:i') }}</small>
                        </div>
                        <div class="text-muted" style="font-size:12px">
                            oleh {{ $log->user?->name ?? 'Sistem' }}
                            @if ($log->from_status && $log->to_status)
                                &bull; {{ $log->from_status }} &rarr; {{ $log->to_status }}
                            @endif
                        </div>
                        @if ($log->note)
                            <div class="small fst-italic">"{{ $log->note }}"</div>
                        @endif
                    </li>
                @empty
                    <li class="timeline-item muted text-muted small">Belum ada aktivitas.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

@endsection

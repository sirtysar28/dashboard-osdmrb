@extends('layouts.app')

@section('page_title', 'Pengajuan Layanan')
@section('page_subtitle', 'Pengajuan layanan kepegawaian ASN — KGB, kenaikan pangkat, pensiun, izin belajar & pengembangan kompetensi')

@section('content')

<?php $privileged = auth()->user()->isPrivileged(); ?>

{{-- ================= FORM PENGAJUAN LAYANAN (DI ATAS — revisi 21 Nov 2026) =================
     Catatan Masukan 19 Nov 2026: Unit Kerja, Nama yang mengajukan,
     Jenis Layanan, unggah Nota Dinas & link Bukti Dukung. --}}
<div class="table-card mb-3">
    <div class="card-header-custom">
        <h5 class="mb-0"><i class="bi bi-box-arrow-in-up me-2"></i>Form Pengajuan Layanan</h5>
    </div>
    <form method="POST" action="{{ route('layanan.store') }}" enctype="multipart/form-data" class="p-3">
        @csrf

        <div class="row g-3">
            <div class="col-xl-4 col-md-6">
                <label class="form-label">Unit Kerja <span class="text-danger">*</span></label>
                <select name="unit_id" id="unitSelect" class="form-select" required>
                    <option value="">— Pilih Unit Kerja —</option>
                    @foreach ($unitOptions as $unit)
                        <option value="{{ $unit->id }}"
                                {{ (string) old('unit_id', $defaultEmployee?->unit_id ?? '') === (string) $unit->id ? 'selected' : '' }}>
                            {{ $unit->name }}
                        </option>
                    @endforeach
                </select>
                @error('unit_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="col-xl-4 col-md-6">
                <label class="form-label">Nama yang Mengajukan <span class="text-danger">*</span></label>
                @if ($privileged)
                    <select name="employee_id" id="employeeSelect" class="form-select" required>
                        <option value="">— Pilih Pegawai —</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee['id'] }}" data-unit="{{ $employee['unit_id'] }}"
                                    {{ (string) old('employee_id', $defaultEmployeeId ?? '') === (string) $employee['id'] ? 'selected' : '' }}>
                                {{ $employee['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Daftar pegawai otomatis mengikuti unit kerja terpilih (pegawai ASN aktif).</div>
                @else
                    <input type="text" class="form-control" readonly
                           value="{{ $defaultEmployee?->name ?? auth()->user()->name }}{{ $defaultEmployee?->nip ? ' — '.$defaultEmployee->nip : '' }}">
                    <input type="hidden" name="employee_id" value="{{ $defaultEmployeeId }}">
                @endif
                @error('employee_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="col-xl-4 col-md-6">
                <label class="form-label">Jenis Layanan <span class="text-danger">*</span></label>
                <select name="service_type" class="form-select" required>
                    <option value="">— Pilih Jenis Layanan —</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" {{ old('service_type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('service_type')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="col-xl-4 col-md-6">
                <label class="form-label">Upload Nota Dinas <span class="text-danger">*</span></label>
                <input type="file" name="nota" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                <div class="form-text">Format PDF/JPG/PNG, maksimal 10 MB.</div>
                @error('nota')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="col-xl-8 col-md-12">
                <label class="form-label">Link Bukti Dukung</label>
                <input type="url" name="supporting_link" class="form-control"
                       placeholder="https://drive.google.com/..." value="{{ old('supporting_link') }}">
                <div class="form-text">Tautan berkas pendukung pengajuan (opsional).</div>
                @error('supporting_link')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="d-grid d-sm-flex justify-content-sm-end mt-2">
            <button type="submit" class="btn btn-osdmrb px-5" data-loader-text="Mengirim pengajuan...">
                <i class="bi bi-send me-1"></i> Kirim Pengajuan
            </button>
        </div>
    </form>
</div>

{{-- ================= DAFTAR PENGAJUAN (DI BAWAH — 10 per halaman) ================= --}}
<div class="table-card">
            <div class="card-header-custom flex-wrap gap-2">
                <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Daftar Pengajuan</h5>
                <span class="badge bg-primary-subtle text-primary">{{ $requests->total() }} pengajuan</span>
            </div>

            {{-- Filter daftar pengajuan --}}
            <form method="GET" class="px-3 pt-3">
                <div class="row g-2">
                    <div class="col-sm-6 col-12">
                        <select name="jenis" class="form-select form-select-sm">
                            <option value="">Semua Jenis Layanan</option>
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['jenis'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-12">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Semua Status</option>
                            @foreach (['PENDING' => 'Diajukan', 'DIPROSES' => 'Diproses', 'SELESAI' => 'Selesai', 'DITOLAK' => 'Ditolak'] as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($privileged)
                        <div class="col-sm-4 col-12">
                            <select name="unit" class="form-select form-select-sm">
                                <option value="">Semua Unit Kerja</option>
                                @foreach ($unitOptions as $unit)
                                    <option value="{{ $unit->id }}" {{ (string) ($filters['unit'] ?? '') === (string) $unit->id ? 'selected' : '' }}>{{ $unit->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-8 col-12">
                            <input type="text" name="search" class="form-control form-control-sm"
                                   placeholder="Cari nama / NIP pegawai..." value="{{ $filters['search'] ?? '' }}">
                        </div>
                    @endif
                    <div class="col-12 d-flex gap-2 justify-content-end">
                        <a href="{{ route('layanan.index') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </a>
                        <button class="btn btn-osdmrb btn-sm px-4"><i class="bi bi-search"></i> Cari</button>
                    </div>
                </div>
            </form>
            <br>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:44px">No</th>
                            <th>Tanggal</th>
                            <th>Pegawai</th>
                            <th>Unit Kerja</th>
                            <th>Jenis Layanan</th>
                            <th class="text-center">Nota Dinas</th>
                            <th class="text-center">Bukti Dukung</th>
                            <th>Status</th>
                            @if ($privileged)<th class="text-center">Aksi</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $item)
                            <tr>
                                <td class="text-center text-muted">{{ $requests->firstItem() + $loop->iteration - 1 }}</td>
                                <td class="text-nowrap">{{ $item->created_at->translatedFormat('d/m/Y') }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $item->employee?->name }}</div>
                                    <small class="text-muted">{{ $item->employee?->nip }}</small>
                                </td>
                                <td><small>{{ $item->unit?->name }}</small></td>
                                <td>{{ $item->type_label }}</td>
                                <td class="text-center">
                                    <a href="{{ route('layanan.nota', $item) }}" class="btn btn-sm btn-outline-osdmrb" title="Unduh Nota Dinas" data-no-loader>
                                        <i class="bi bi-file-earmark-arrow-down"></i>
                                    </a>
                                </td>
                                <td class="text-center">
                                    @if ($item->supporting_link)
                                        <a href="{{ $item->supporting_link }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary" title="Buka link Bukti Dukung" data-no-loader>
                                            <i class="bi bi-link-45deg"></i>
                                        </a>
                                    @else
                                        <span class="text-muted">&ndash;</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $item->status_badge }}" @if($item->note) title="{{ $item->note }}" @endif>{{ $item->status_label }}</span>
                                </td>
                                @if ($privileged)
                                    <td class="text-center text-nowrap">
                                        @if ($item->status === \App\Models\ServiceRequest::STATUS_PENDING)
                                            <form action="{{ route('layanan.process', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Proses pengajuan &quot;{{ $item->employee?->name }}&quot;?')">
                                                @csrf
                                                <button class="btn btn-sm btn-osdmrb" title="Proses pengajuan"><i class="bi bi-play-fill"></i></button>
                                            </form>
                                        @endif
                                        @if (in_array($item->status, [\App\Models\ServiceRequest::STATUS_PENDING, \App\Models\ServiceRequest::STATUS_PROCESSING]))
                                            <button type="button" class="btn btn-sm btn-success" title="Tandai selesai"
                                                    data-bs-toggle="modal" data-bs-target="#selesaiModal"
                                                    data-action="{{ route('layanan.complete', $item) }}"
                                                    data-label="{{ $item->employee?->name }} — {{ $item->type_label }}">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" title="Tolak pengajuan"
                                                    data-bs-toggle="modal" data-bs-target="#tolakModal"
                                                    data-action="{{ route('layanan.reject', $item) }}"
                                                    data-label="{{ $item->employee?->name }} — {{ $item->type_label }}">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $privileged ? 9 : 8 }}" class="text-center text-muted py-4">Belum ada pengajuan layanan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Catatan 19 Nov 2026: daftar dibatasi 10 item per halaman --}}
            <div class="mt-3 px-3 pb-2">
                {{ $requests->links() }}
            </div>
</div>

{{-- ================= MODAL TOLAK PENGGAJUAN (wajib alasan) ================= --}}
<div class="modal fade" id="tolakModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" id="tolakForm" class="modal-content" onsubmit="return confirm('Tolak pengajuan ini?')">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-x-circle me-2 text-danger"></i>Tolak Pengajuan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="tolakLabel">&nbsp;</p>
                <input type="hidden" name="_method" value="POST">
                <label class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                <textarea name="note" rows="3" class="form-control" required placeholder="Tuliskan alasan penolakan pengajuan..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger">Tolak Pengajuan</button>
            </div>
        </form>
    </div>
</div>

{{-- ================= MODAL SELESAIKAN PENGGAJUAN ================= --}}
<div class="modal fade" id="selesaiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" id="selesaiForm" class="modal-content" onsubmit="return confirm('Tandai pengajuan ini selesai?')">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-check-circle me-2 text-success"></i>Tandai Selesai</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="selesaiLabel">&nbsp;</p>
                <label class="form-label">Catatan Penyelesaian <span class="text-muted">(opsional)</span></label>
                <textarea name="note" rows="2" class="form-control" placeholder="Mis. surat keputusan telah terbit..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success">Simpan &amp; Selesai</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        /* ---------- Nama pengaju mengikuti Unit Kerja terpilih ---------- */
        var unitSelect = document.getElementById('unitSelect');
        var employeeSelect = document.getElementById('employeeSelect');

        function syncEmployees() {
            if (! unitSelect || ! employeeSelect) return;

            var unitId = unitSelect.value;
            var selected = employeeSelect.value;
            var firstVisible = null;

            Array.prototype.forEach.call(employeeSelect.options, function (opt) {
                if (! opt.value) return; // placeholder

                var match = ! unitId || ! opt.dataset.unit || opt.dataset.unit === unitId;
                opt.hidden = ! match;
                opt.disabled = ! match;
                if (match && firstVisible === null) firstVisible = opt;
            });

            if (selected && ! employeeSelect.selectedOptions[0]?.disabled) return;
            if (firstVisible) employeeSelect.value = firstVisible.value;
        }

        if (unitSelect) unitSelect.addEventListener('change', syncEmployees);
        syncEmployees();

        /* ---------- Modal tolak / selesai: isi aksi & label dinamis ---------- */
        ['tolak', 'selesai'].forEach(function (name) {
            var modal = document.getElementById(name + 'Modal');
            if (! modal) return;

            modal.addEventListener('show.bs.modal', function (event) {
                var btn = event.relatedTarget;
                if (! btn) return;

                modal.querySelector('form').action = btn.dataset.action || '';
                var label = document.getElementById(name + 'Label');
                if (label) label.textContent = btn.dataset.label || '';
            });
        });
    });
</script>
@endpush

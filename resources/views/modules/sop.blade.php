@extends('layouts.app')

@section('page_title', 'SOP Kementerian')
@section('page_subtitle', 'Standar Operasional Prosedur layanan &amp; tata kerja unit')

@section('content')

@php($canManage = auth()->user()->isPrivileged())

{{-- ================= KATEGORI ================= --}}
<div class="row g-3 mb-4">
    @foreach ($kategori as $i => $nama)
        <div class="col-xl-2 col-md-4 col-6">
            <div class="sop-cat-card">
                <i class="bi {{ ['bi-globe', 'bi-person-vcard', 'bi-cash-coin', 'bi-box-seam', 'bi-clipboard-data', 'bi-scale'][$i % 6] }}"></i>
                <span>{{ $nama }}</span>
                <small>{{ $counts[$nama] ?? 0 }} dokumen</small>
            </div>
        </div>
    @endforeach
</div>

{{-- ================= FILTER ================= --}}
<div class="filter-card mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-lg-5 col-md-6">
            <label>Pencarian</label>
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Nama SOP / nomor / unit penyusun..." value="{{ request('search') }}">
        </div>
        <div class="col-lg-3 col-md-3 col-6">
            <label>Kategori</label>
            <select name="kategori" class="form-select form-select-sm">
                <option value="">Semua</option>
                @foreach ($kategori as $nama)
                    <option value="{{ $nama }}" {{ request('kategori') === $nama ? 'selected' : '' }}>{{ $nama }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-3 col-6">
            <label>Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">Semua</option>
                @foreach (\App\Models\Sop::STATUSES as $value => $label)
                    <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-12 d-flex gap-2 justify-content-md-end">
            <a href="{{ route('modules.sop') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-counterclockwise"></i> <span class="d-none d-sm-inline">Reset</span>
            </a>
            <button class="btn btn-osdmrb btn-sm px-4"><i class="bi bi-search"></i> Cari</button>
        </div>
    </form>
</div>

{{-- ================= DAFTAR SOP ================= --}}
<div class="row g-3">
    <div class="col-lg-12">
        <div class="table-card">
            <div class="card-header-custom">
                <h5 class="mb-0"><i class="bi bi-journal-text me-2"></i>Daftar SOP ({{ $sops->total() }})</h5>

                @if ($canManage)
                    <button class="btn btn-sm btn-osdmrb" data-bs-toggle="modal" data-bs-target="#modalUnggahSop">
                        <i class="bi bi-plus-lg"></i> Unggah SOP
                    </button>
                @endif
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 60px;">No</th>
                            <th>Nama SOP</th>
                            <th>Kategori</th>
                            <th>Unit Penyusun</th>
                            <th class="text-center">Tahun</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width: 170px;">Dokumen</th>
                            @if ($canManage)<th class="text-center" style="width: 60px;">Aksi</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sops as $sop)
                            <tr>
                                <td>{{ $sops->firstItem() + $loop->iteration - 1 }}</td>
                                <td class="fw-semibold">{{ $sop->title }}
                                    @if ($sop->number)<br><code class="small text-muted">{{ $sop->number }}</code>@endif
                                </td>
                                <td><span class="badge bg-primary bg-osdmrb">{{ $sop->category }}</span></td>
                                <td>{{ $sop->unit ?? '-' }}</td>
                                <td class="text-center">{{ $sop->year ?? '-' }}</td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $sop->status_badge }}">{{ $sop->status_label }}</span>
                                </td>
                                <td class="text-center">
                                    @if ($sop->has_file)
                                        @php($previewFile = strtolower($sop->file_name ?: basename($sop->file_path)))
                                        <div class="d-flex gap-1 justify-content-center">
                                            <button type="button" class="btn btn-sm btn-outline-osdmrb" title="Preview isi dokumen"
                                                    data-preview-sop
                                                    data-preview-url="{{ route('modules.sop.preview', $sop) }}"
                                                    data-preview-download="{{ route('modules.sop.download', $sop) }}"
                                                    data-preview-title="{{ $sop->title }}"
                                                    data-preview-file="{{ $previewFile }}">
                                                <i class="bi bi-eye"></i> <span class="d-lg-none">Preview</span>
                                            </button>
                                            <a href="{{ route('modules.sop.download', $sop) }}" class="btn btn-sm btn-outline-osdmrb"
                                               data-no-loader title="Unduh dokumen SOP">
                                                <i class="bi bi-download"></i> <span class="d-lg-none">Unduh</span>
                                            </a>
                                        </div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                @if ($canManage)
                                    <td class="text-center">
                                        <form action="{{ route('modules.sop.destroy', $sop) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Hapus SOP ini?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canManage ? 8 : 7 }}" class="text-center py-5">
                                    <i class="bi bi-journal-text text-muted" style="font-size: 2.4rem;"></i>
                                    <p class="text-muted mt-2 mb-1"><strong>Belum ada SOP yang diunggah</strong></p>
                                    <p class="text-muted small mb-0">Dokumen SOP akan dikategorisasi, diberi status, dan dapat diunduh dari halaman ini.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($sops->hasPages())
                <div class="mt-3">{{ $sops->links() }}</div>
            @endif
        </div>
    </div>
</div>

{{-- ================= MODAL UNGGAH SOP ================= --}}
@if ($canManage)
    <div class="modal fade" id="modalUnggahSop" tabindex="-1" aria-labelledby="modalUnggahSopLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <form method="POST" action="{{ route('modules.sop.store') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="modal-header">
                        <h5 class="modal-title" id="modalUnggahSopLabel">
                            <i class="bi bi-cloud-arrow-up me-2"></i>Unggah SOP Kementerian
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label" for="sopTitle">Nama SOP <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="sopTitle" class="form-control @error('title') is-invalid @enderror"
                                       value="{{ old('title') }}" placeholder="mis. SOP Pelayanan Surat Keterangan Pegawai" required>
                                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="sopNumber">Nomor SOP</label>
                                <input type="text" name="number" id="sopNumber" class="form-control @error('number') is-invalid @enderror"
                                       value="{{ old('number') }}" placeholder="mis. SOP-021/OSDMRB/2026">
                                @error('number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="sopCategory">Kategori <span class="text-danger">*</span></label>
                                <select name="category" id="sopCategory" class="form-select @error('category') is-invalid @enderror" required>
                                    <option value="">- Pilih Kategori -</option>
                                    @foreach ($kategori as $nama)
                                        <option value="{{ $nama }}" {{ old('category') === $nama ? 'selected' : '' }}>{{ $nama }}</option>
                                    @endforeach
                                </select>
                                @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="sopUnit">Unit Penyusun</label>
                                <input type="text" name="unit" id="sopUnit" class="form-control @error('unit') is-invalid @enderror"
                                       value="{{ old('unit') }}" placeholder="mis. Biro OSDMRB">
                                @error('unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="sopYear">Tahun</label>
                                <input type="number" name="year" id="sopYear" class="form-control @error('year') is-invalid @enderror"
                                       value="{{ old('year', now()->year) }}" min="1900" max="2100">
                                @error('year')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="sopStatus">Status</label>
                                <select name="status" id="sopStatus" class="form-select @error('status') is-invalid @enderror">
                                    @foreach (\App\Models\Sop::STATUSES as $value => $label)
                                        <option value="{{ $value }}" {{ old('status', 'BERLAKU') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="sopDesc">Deskripsi / Uraian Singkat</label>
                                <textarea name="description" id="sopDesc" rows="2" class="form-control @error('description') is-invalid @enderror"
                                          placeholder="Ringkasan ruang lingkup SOP (opsional)">{{ old('description') }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="sopFile">Dokumen SOP (PDF / Word, maks. 10 MB)</label>
                                <input type="file" name="file" id="sopFile" accept=".pdf,.doc,.docx"
                                       class="form-control @error('file') is-invalid @enderror">
                                <div class="form-text">Unggah berkas resmi SOP untuk dapat diunduh seluruh pegawai.</div>
                                @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-osdmrb" data-loader-text="Mengunggah SOP">
                            <i class="bi bi-cloud-arrow-up me-1"></i> Unggah
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

{{-- ================= MODAL PREVIEW DOKUMEN SOP (popup) ================= --}}
<div class="modal fade" id="modalPreviewSop" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-truncate me-3">
                    <i class="bi bi-file-earmark-text me-2"></i><span id="sopPreviewTitle">Preview Dokumen</span>
                </h5>
                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                    <a href="#" id="sopPreviewDownload" class="btn btn-sm btn-outline-osdmrb" data-no-loader>
                        <i class="bi bi-download me-1"></i>Unduh
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
            </div>
            <div class="modal-body p-0">
                <iframe id="sopPreviewFrame" src="about:blank" title="Preview dokumen SOP"
                        style="width: 100%; height: 78vh; border: 0; display: block;"></iframe>

                {{-- fallback untuk dokumen non-PDF (doc/docx tidak bisa dirender browser) --}}
                <div id="sopPreviewFallback" class="text-center py-5 px-3" style="display: none;">
                    <i class="bi bi-file-earmark-word" style="font-size: 3.2rem; color: #2b579a;"></i>
                    <p class="text-muted mt-3 mb-1"><strong>Pratinjau langsung belum tersedia untuk dokumen Word</strong></p>
                    <p class="text-muted small mb-3">Silakan unduh dokumen untuk membaca isi lengkapnya.</p>
                    <a href="#" id="sopPreviewFallbackDownload" class="btn btn-osdmrb" data-no-loader>
                        <i class="bi bi-download me-1"></i>Unduh Dokumen
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    /* ============ POPUP PREVIEW DOKUMEN SOP ============ */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-preview-sop]');
        if (!btn) return;

        var modalEl   = document.getElementById('modalPreviewSop');
        if (!modalEl) return;

        var url      = btn.dataset.previewUrl;
        var download = btn.dataset.previewDownload;
        var file     = (btn.dataset.previewFile || '').toLowerCase();
        var isPdf    = file.endsWith('.pdf');

        document.getElementById('sopPreviewTitle').textContent = btn.dataset.previewTitle || 'Preview Dokumen';
        document.getElementById('sopPreviewDownload').href = download;

        var frame    = document.getElementById('sopPreviewFrame');
        var fallback = document.getElementById('sopPreviewFallback');

        if (isPdf) {
            fallback.style.display = 'none';
            frame.style.display = 'block';
            frame.src = url;
        } else {
            frame.src = 'about:blank';
            frame.style.display = 'none';
            fallback.style.display = 'block';
            document.getElementById('sopPreviewFallbackDownload').href = download;
        }

        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        /* bebaskan iframe saat popup ditutup */
        modalEl.addEventListener('hidden.bs.modal', function () {
            frame.src = 'about:blank';
        }, { once: true });
    });
</script>
@endpush

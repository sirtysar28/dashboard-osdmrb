@extends('layouts.app')

@section('page_title', $archive->exists ? 'Ubah Arsip' : 'Tambah Arsip')
@section('page_subtitle', 'Pengelolaan dokumen kearsipan')

@section('content')

<div class="form-narrow">
<form method="POST"
      action="{{ $archive->exists ? route('archives.update', $archive) : route('archives.store') }}"
      enctype="multipart/form-data">
    @csrf
    @if ($archive->exists)
        @method('PUT')
    @endif

    <div class="row g-3">

        <div class="col-lg-8">
            <div class="form-card mb-3">
                <h5><i class="bi bi-file-earmark-text me-2"></i>Informasi Dokumen</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nomor Arsip <span class="text-danger">*</span></label>
                        <input type="text" name="archive_number" class="form-control" required
                               value="{{ old('archive_number', $archive->archive_number) }}" placeholder="ARS/2026/0001">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jenis Dokumen <span class="text-danger">*</span></label>
                        <select name="type" class="form-select" required>
                            @foreach (\App\Models\Archive::TYPES as $value => $label)
                                <option value="{{ $value }}" {{ old('type', $archive->type) === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Judul / Uraian Informasi <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required
                               value="{{ old('title', $archive->title) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Keterangan Tambahan</label>
                        <textarea name="description" rows="3" class="form-control">{{ old('description', $archive->description) }}</textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tanggal Dokumen</label>
                        <input type="date" name="document_date" class="form-control"
                               value="{{ old('document_date', $archive->document_date?->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tahun</label>
                        <input type="number" name="year" class="form-control" min="1900" max="2100"
                               value="{{ old('year', $archive->year) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Klasifikasi Arsip</label>
                        <select name="archive_category_id" class="form-select">
                            <option value="">- Pilih -</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" {{ old('archive_category_id', $archive->archive_category_id) == $category->id ? 'selected' : '' }}>
                                    {{ $category->code }} — {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-card mb-3">
                <h5><i class="bi bi-link-45deg me-2"></i>Asosiasi Data</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Pegawai Terkait (arsip pribadi)</label>
                        <select name="employee_id" class="form-select">
                            <option value="">- Tidak terkait -</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" {{ old('employee_id', $archive->employee_id) == $employee->id ? 'selected' : '' }}>
                                    {{ $employee->name }} — {{ $employee->nip }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Unit Pengolah</label>
                        <select name="unit_id" class="form-select">
                            <option value="">- Tidak terkait -</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}" {{ old('unit_id', $archive->unit_id) == $unit->id ? 'selected' : '' }}>{{ $unit->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 form-col-start">
            <div class="form-card mb-3">
                <h5><i class="bi bi-clock-history me-2"></i>Retensi & Kedudukan</h5>
                <div class="mb-3">
                    <label class="form-label">Retensi Arsip <span class="text-danger">*</span></label>
                    <select name="retention" class="form-select" required>
                        @foreach (\App\Models\Archive::RETENTIONS as $value => $label)
                            <option value="{{ $value }}" {{ old('retention', $archive->retention) === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Masa Simpan (tahun)</label>
                    <input type="number" name="retention_years" class="form-control" min="0"
                           value="{{ old('retention_years', $archive->retention_years) }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Batas Simpan</label>
                    <input type="date" name="retention_until" class="form-control"
                           value="{{ old('retention_until', $archive->retention_until?->format('Y-m-d')) }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Lokasi Fisik</label>
                    <input type="text" name="physical_location" class="form-control"
                           placeholder="mis. Ruang A / Rak 3 / Boks 12" value="{{ old('physical_location', $archive->physical_location) }}">
                </div>
            </div>

            <div class="form-card mb-3">
                <h5><i class="bi bi-shield-lock me-2"></i>Status & Akses</h5>
                <div class="mb-3">
                    <label class="form-label">Status Arsip</label>
                    <select name="status" class="form-select">
                        @foreach (\App\Models\Archive::STATUSES as $value => $label)
                            <option value="{{ $value }}" {{ old('status', $archive->status ?: 'TERSEDIA') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Visibilitas</label>
                    <select name="visibility" class="form-select">
                        <option value="PUBLIK" {{ old('visibility', $archive->visibility ?: 'PUBLIK') === 'PUBLIK' ? 'selected' : '' }}>Publik (semua pegawai)</option>
                        <option value="INTERNAL" {{ old('visibility', $archive->visibility ?: 'PUBLIK') === 'INTERNAL' ? 'selected' : '' }}>Internal (admin saja)</option>
                    </select>
                </div>
            </div>

            <div class="form-card">
                <h5><i class="bi bi-cloud-upload me-2"></i>Salinan Digital</h5>
                @if ($archive->file_path)
                    <div class="alert alert-secondary small py-2 mb-3">
                        Berkas saat ini: <strong>{{ $archive->file_name }}</strong><br>
                        <a data-no-loader href="{{ route('archives.download', $archive) }}" class="text-decoration-none">Unduh berkas saat ini</a>
                    </div>
                @endif
                <input type="file" name="file" class="form-control {{ $archive->file_path ? '' : 'mb-1' }}">
                <div class="form-text">Maksimal 10 MB (PDF, gambar, dokumen). Kosongkan bila tidak diubah.</div>
            </div>
        </div>

        <div class="col-12">
            <div class="form-footer">
                <span class="form-footer-info">
                    <i class="bi bi-info-circle me-1"></i>kolom bertanda <span class="text-danger">*</span> wajib diisi
                </span>
                <a href="{{ route('archives.index') }}" class="btn btn-outline-secondary">Batal</a>
                <button type="submit" class="btn btn-osdmrb px-4">
                    <i class="bi bi-save"></i> {{ $archive->exists ? 'Simpan Perubahan' : 'Simpan Arsip' }}
                </button>
            </div>
        </div>

    </div>
</form>
</div>

@endsection

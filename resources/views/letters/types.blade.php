@extends('layouts.app')

@section('page_title', 'Jenis Surat')
@section('page_subtitle', 'Master layanan persuratan & template nomor')

@section('content')

@php($canManage = auth()->user()->isAdmin())

@unless ($canManage)
    <div class="alert alert-info d-flex align-items-center gap-2 mb-3" role="alert">
        <i class="bi bi-eye fs-5"></i>
        <div>
            <strong>Mode Biro SDM (hanya lihat).</strong>
            Jenis surat dapat dilihat &amp; diexport, namun perubahan hanya dilakukan oleh Admin Instansi.
        </div>
    </div>
@endunless

<div class="row g-3">
    <div class="col-lg-4 form-col-start{{ $canManage ? '' : ' d-none' }}">
        <div class="chart-card">
            <h5><i class="bi bi-plus-circle me-2"></i>Tambah Jenis Surat</h5>
            <form method="POST" action="{{ route('letter-types.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Kode</label>
                    <input type="text" name="code" class="form-control" required placeholder="ST">
                </div>
                <div class="mb-3">
                    <label class="form-label">Nama Jenis Surat</label>
                    <input type="text" name="name" class="form-control" required placeholder="Surat Tugas">
                </div>
                <div class="mb-3">
                    <label class="form-label">Format Nomor</label>
                    <input type="text" name="code_format" class="form-control" placeholder="{no}/OSDMRB/{romawi}/{tahun}">
                    <div class="form-text">Placeholder: {no}, {romawi}, {tahun}</div>
                </div>
                <button class="btn btn-osdmrb w-100">Simpan</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="table-card">
            <div class="card-header-custom export-btn">
                <h5 class="mb-0">Daftar Jenis Surat</h5>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" data-no-loader href="{{ route('master.export', ['format' => 'xlsx', 'tab' => 'letters']) }}">
                                <i class="bi bi-file-earmark-excel text-success"></i> Excel (.xlsx)
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" data-no-loader href="{{ route('master.export', ['format' => 'pdf', 'tab' => 'letters']) }}">
                                <i class="bi bi-file-earmark-pdf text-danger"></i> PDF
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>Kode</th><th>Nama Jenis Surat</th><th>Format Nomor</th><th>Surat</th><th class="text-center">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($letterTypes as $type)
                            <tr>
                                <td><code>{{ $type->code }}</code></td>
                                <td>{{ $type->name }}
                                    @unless($type->is_active)<span class="badge bg-secondary">non-aktif</span>@endunless
                                </td>
                                <td class="small">{{ $type->code_format ?? '-' }}</td>
                                <td>{{ $type->letters_count }}</td>
                                <td class="text-center text-nowrap">
                                    <button class="btn btn-sm btn-outline-osdmrb {{ $canManage ? '' : 'd-none' }}" title="Ubah"
                                            data-bs-toggle="modal" data-bs-target="#editLetterType{{ $type->id }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ route('letter-types.destroy', $type) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Hapus jenis surat ini beserta seluruh pengajuannya?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger {{ $canManage ? '' : 'd-none' }}" title="Hapus"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada jenis surat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Modals ubah jenis surat --}}
@foreach ($letterTypes as $type)
    <div class="modal fade {{ $canManage ? '' : 'd-none' }}" id="editLetterType{{ $type->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 14px">
                <form method="POST" action="{{ route('letter-types.update', $type) }}">
                    @csrf
                    @method('PUT')

                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" style="font-size: 15px; color: #143647">
                            <i class="bi bi-pencil-square me-1"></i> Ubah Jenis Surat
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Kode <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control" required value="{{ old('code', $type->code) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Jenis Surat <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required value="{{ old('name', $type->name) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Format Nomor</label>
                            <input type="text" name="code_format" class="form-control"
                                   value="{{ old('code_format', $type->code_format) }}" placeholder="{no}/OSDMRB/{romawi}/{tahun}">
                            <div class="form-text">Placeholder: {no}, {romawi}, {tahun}</div>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="lt_active_{{ $type->id }}"
                                   {{ old('is_active', $type->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="lt_active_{{ $type->id }}">Jenis surat aktif (dapat diajukan)</label>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-osdmrb btn-sm px-4">
                            <i class="bi bi-save"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

@endsection

@extends('layouts.app')

@section('page_title', 'Pengaturan Pengumuman')
@section('page_subtitle', 'Kelola pengumuman gambar & running text di dashboard (Administrator Utama)')

@section('content')

@include('settings.partials.nav')

<div class="row g-3">

    {{-- ===================== FORM TAMBAH ===================== --}}
    <div class="col-lg-5">
        <div class="chart-card">
            <h5><i class="bi bi-plus-circle me-2"></i>Tambah Pengumuman</h5>

            <form method="POST" action="{{ route('settings.announcements.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Judul <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control form-control-sm" required
                           value="{{ old('title') }}" placeholder="Judul pengumuman">
                    <div class="form-text">Judul tampil pada kartu & teks berjalan (marquee) di dashboard.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Teks Detail (opsional)</label>
                    <textarea name="running_text" rows="2" class="form-control form-control-sm"
                              placeholder="Isi detail pengumuman, tampil saat kartu diklik...">{{ old('running_text') }}</textarea>
                    <div class="form-text">Kosongkan agar teks berjalan memakai judul. Teks ini tampil di popup detail kartu.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Gambar Kartu — Upload Berkas</label>
                    <input type="file" name="image" class="form-control form-control-sm" accept="image/*">
                    <div class="form-text">
                        Format jpg/png/webp/gif.
                        <strong>Ukuran ideal 600 &times; 750 px</strong> (kartu portrait, rasio 4:5),
                        <strong>maksimal 1 MB</strong>.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">atau URL Gambar</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                        <input type="url" name="image_url" class="form-control form-control-sm"
                               value="{{ old('image_url') }}" placeholder="https://contoh.go.id/gambar.jpg">
                    </div>
                    <div class="form-text">Isi SALAH SATU: upload berkas <strong>atau</strong> URL gambar (upload lebih diprioritaskan bila keduanya diisi).</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Link Button (opsional)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-box-arrow-up-right"></i></span>
                        <input type="url" name="link_url" class="form-control form-control-sm"
                               value="{{ old('link_url') }}" placeholder="https://...">
                    </div>
                    <div class="form-text">Tombol tampil di popup detail kartu pengumuman.</div>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="isActiveNew" checked>
                    <label class="form-check-label" for="isActiveNew">Tampilkan pengumuman ini</label>
                </div>

                <button type="submit" class="btn btn-osdmrb btn-sm px-4" data-loader-text="Menyimpan pengumuman">
                    <i class="bi bi-save"></i> Simpan
                </button>
            </form>
        </div>
    </div>

    {{-- ===================== DAFTAR PENGUMUMAN ===================== --}}
    <div class="col-lg-7">
        <div class="table-card">
            <div class="card-header-custom">
                <h5 class="mb-0">Daftar Pengumuman ({{ $announcements->total() }})</h5>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Pengumuman</th>
                            <th>Status</th>
                            <th>Dibuat</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($announcements as $announcement)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @if ($announcement->image_url)
                                            <img src="{{ $announcement->image_url }}" alt="" width="46" height="32"
                                                 class="rounded object-fit-cover">
                                        @endif
                                        <div>
                                            <div class="fw-semibold">{{ $announcement->title }}</div>
                                            <small class="text-muted d-block text-truncate" style="max-width:260px">
                                                {{ Str::limit($announcement->running_text, 70) ?: 'Tanpa running text' }}
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge {{ $announcement->is_active ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $announcement->is_active ? 'Tampil' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td><small>{{ $announcement->created_at->translatedFormat('d M Y H:i') }}</small></td>
                                <td class="text-center text-nowrap">
                                    <button class="btn btn-sm btn-outline-osdmrb" data-bs-toggle="modal"
                                            data-bs-target="#editAnnouncement{{ $announcement->id }}" title="Ubah">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ route('settings.announcements.destroy', $announcement) }}"
                                          method="POST" class="d-inline"
                                          onsubmit="return confirm('Hapus pengumuman ini?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>

                            {{-- modal edit --}}
                            <div class="modal fade" id="editAnnouncement{{ $announcement->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Ubah Pengumuman</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST"
                                              action="{{ route('settings.announcements.update', $announcement) }}"
                                              enctype="multipart/form-data">
                                            @csrf @method('PUT')

                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Judul</label>
                                                    <input type="text" name="title" class="form-control form-control-sm"
                                                           value="{{ $announcement->title }}" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Teks Detail (opsional)</label>
                                                    <textarea name="running_text" rows="2"
                                                              class="form-control form-control-sm">{{ $announcement->running_text }}</textarea>
                                                    <div class="form-text">Kosongkan agar teks berjalan memakai judul.</div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Gambar Kartu — Upload Berkas</label>
                                                    @if ($announcement->image_url)
                                                        <img src="{{ $announcement->image_url }}" class="img-fluid rounded mb-2" style="max-height:120px" alt="">
                                                    @endif
                                                    <input type="file" name="image" class="form-control form-control-sm" accept="image/*">
                                                    <div class="form-text">
                                                        Format jpg/png/webp/gif. <strong>Ukuran ideal 600 &times; 750 px</strong> (portrait 4:5),
                                                        <strong>maksimal 1 MB</strong>.
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">atau URL Gambar</label>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                                                        <input type="url" name="image_url" class="form-control form-control-sm"
                                                               value="{{ $announcement->is_local_image ? '' : $announcement->image_path }}"
                                                               placeholder="https://contoh.go.id/gambar.jpg">
                                                    </div>
                                                    @if ($announcement->image_path)
                                                        <div class="form-check mt-2">
                                                            <input class="form-check-input" type="checkbox" name="remove_image" value="1"
                                                                   id="rm{{ $announcement->id }}">
                                                            <label class="form-check-label small" for="rm{{ $announcement->id }}">
                                                                Hapus gambar saat disimpan
                                                            </label>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Link Button (opsional)</label>
                                                    <input type="url" name="link_url" class="form-control form-control-sm"
                                                           value="{{ $announcement->link_url }}">
                                                </div>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" role="switch"
                                                           name="is_active" value="1" id="active{{ $announcement->id }}"
                                                           {{ $announcement->is_active ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="active{{ $announcement->id }}">Tampilkan</label>
                                                </div>
                                            </div>

                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" class="btn btn-sm btn-osdmrb px-4">Simpan Perubahan</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">Belum ada pengumuman.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $announcements->links() }}</div>
        </div>
    </div>
</div>

@endsection

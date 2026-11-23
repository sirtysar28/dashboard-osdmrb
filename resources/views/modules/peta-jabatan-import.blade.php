@extends('layouts.app')

@section('page_title', 'Import Peta Jabatan')
@section('page_subtitle', 'Perbarui data formasi jabatan fungsional dari Excel')

@section('content')

<div class="form-narrow">
    <div class="form-card mb-3">
        <h5><i class="bi bi-upload me-2"></i>Import Data Peta Jabatan (.xlsx)</h5>

        <p class="small text-muted">
            Unggah data pemetaan <strong>jabatan fungsional</strong> (jumlah pegawai existing &amp;
            kebutuhan formasi per unit kerja) dalam format Excel — sesuai Catatan Masukan 21 Okt 2026.
            Saat ini terdapat <strong>{{ number_format($totalRows) }}</strong> baris data peta jabatan tersimpan.
        </p>

        <ol class="small text-muted">
            <li class="mb-1">Unduh <a href="{{ route('modules.peta-jabatan.template') }}" class="fw-semibold" data-no-loader>template import</a> bila belum punya format baku.</li>
            <li class="mb-1">Baris pertama berkas harus berisi judul kolom: <code>Jabatan Fungsional</code>,
                <code>Unit Kerja</code>, <code>Jumlah Pegawai Existing</code>, <code>Kebutuhan Pegawai</code>,
                dan <code>Keterangan</code> (opsional).</li>
            <li class="mb-1">Nama unit kerja diisi persis sesuai daftar unit kerja aplikasi
                (mis. <em>Sekretariat Jenderal</em>); kosongkan bila formasi berlaku untuk seluruh kementerian.</li>
            <li class="mb-0">Baris dengan jabatan &amp; unit kerja yang sama akan diperbarui (tidak duplikat).</li>
        </ol>

        <form method="POST" action="{{ route('modules.peta-jabatan.import.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="row g-3 mt-1">
                <div class="col-md-7">
                    <label class="form-label">Berkas Excel <span class="text-danger">*</span></label>
                    <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                    <div class="form-text">Format .xlsx / .xls / .csv, maksimal 10 MB.</div>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Mode Import <span class="text-danger">*</span></label>
                    <select name="mode" class="form-select">
                        <option value="ganti">Ganti seluruh data (rekomendasi pembaruan tahunan)</option>
                        <option value="perbarui">Perbarui / tambah baris saja</option>
                    </select>
                    <div class="form-text">
                        <strong>Ganti</strong>: seluruh data lama dihapus lalu diganti berkas ini.
                        <strong>Perbarui</strong>: baris lama dengan jabatan &amp; unit sama diperbarui.
                    </div>
                </div>
            </div>

            <div class="form-footer">
                <a href="{{ route('modules.peta-jabatan') }}" class="btn btn-outline-secondary">Batal</a>
                <button type="submit" class="btn btn-osdmrb px-4" data-loader-text="Memproses import">
                    <i class="bi bi-upload"></i> Proses Import
                </button>
            </div>
        </form>
    </div>

    <div class="alert alert-warning small mb-0" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <strong>Mode "Ganti seluruh data"</strong> menghapus semua baris peta jabatan lama terlebih dahulu.
        Ekspor data terlebih dahulu bila perlu cadangan.
    </div>
</div>

@endsection

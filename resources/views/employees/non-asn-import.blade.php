@extends('layouts.app')

@section('page_title', 'Import Pegawai Non ASN')
@section('page_subtitle', 'Tambah / perbarui data pegawai non ASN dari berkas Excel')

@section('content')

<div class="form-narrow">
    <div class="row g-3">

        <div class="col-lg-7">
            <div class="form-card mb-3">
                <h5><i class="bi bi-cloud-upload me-2"></i>Unggah Berkas Excel</h5>

                @if (session('error'))
                    <div class="alert alert-danger small py-2">
                        <i class="bi bi-x-circle me-1"></i> {{ session('error') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('employees.non-asn.import.store') }}" enctype="multipart/form-data">
                    @csrf

                    <label class="import-dropzone d-block mb-3" for="file">
                        <i class="bi bi-file-earmark-spreadsheet"></i>
                        <p class="mb-1 mt-2 fw-semibold" style="font-size:14px; color:#143647">
                            Klik untuk memilih berkas, atau tarik ke sini
                        </p>
                        <p class="text-muted small mb-0">
                            Format .xlsx / .xls / .csv &mdash; maksimal 10 MB
                        </p>
                        <input type="file" id="file" name="file" class="d-none" accept=".xlsx,.xls,.csv" required
                               onchange="this.closest('label').querySelector('p.fw-semibold').textContent = this.files[0] ? this.files[0].name : 'Klik untuk memilih berkas, atau tarik ke sini'">
                    </label>

                    <label class="form-label">Kategori Pegawai</label>
                    <select name="category" class="form-select mb-3">
                        <option value="">Deteksi otomatis dari nama berkas</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}">{{ $category }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted d-block mb-3">
                        Nama berkas yang mengandung <code>security</code> / <code>cleaning</code> /
                        <code>pramubakti</code> / <code>personil pb</code> dikenali otomatis.
                    </small>

                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-osdmrb px-4" data-loader-text="Sedang mengimpor data pegawai non ASN">
                            <i class="bi bi-upload"></i> Mulai Import
                        </button>
                        <a href="{{ route('employees.non-asn') }}" class="btn btn-outline-secondary">Kembali</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-5 form-col-start">
            <div class="form-card mb-3">
                <h5><i class="bi bi-info-circle me-2"></i>Petunjuk Import</h5>
                <div class="alert alert-info py-2 small mb-3">
                    <i class="bi bi-lightbulb me-1"></i>
                    <strong>Berkas daftar non ASN instansi bisa langsung diunggah apa adanya</strong> —
                    posisi judul &amp; kolom yang tidak beraturan dikenali otomatis.
                </div>
                <ol class="import-steps ps-3 mb-3">
                    <li>Unggah satu berkas per kategori (Security / Cleaning Service / Pramubakti).</li>
                    <li>Baris yang berisi kolom <strong>NAMA</strong> akan diproses; baris kosong, judul, dan
                        tanda tangan dilewati otomatis.</li>
                    <li>Berkas <strong>Personil PB</strong> dengan kolom <code>ID PEGAWAI</code>, <code>JABATAN</code>,
                        <code>UNIT KERJA ESELON II</code> akan terbaca lengkap.</li>
                    <li>Pegawai dengan <strong>ID yang sudah ada</strong> akan diperbarui, ID baru ditambahkan.</li>
                    <li>ID kosong dibuat otomatis: <code>SEC-NAMA</code>, <code>CS-NAMA</code>, atau <code>PB-NAMA</code>.</li>
                    <li>Kategori bisa dipilih manual bila nama berkas tidak mengandung kata kunci.</li>
                </ol>
            </div>

            <div class="form-card">
                <h5><i class="bi bi-table me-2"></i>Format yang Didukung</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-guide mb-0 small">
                        <thead><tr><th>Berkas</th><th>Kolom terbaca</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>Personil PB</td>
                                <td><code>ID PEGAWAI</code>, <code>NAMA</code>, <code>UNIT KERJA ESELON II</code></td>
                            </tr>
                            <tr>
                                <td>Security</td>
                                <td><code>NAMA</code>, <code>JABATAN</code> (Koordinator, Pamdal, Chief, Secwan, &hellip;)</td>
                            </tr>
                            <tr>
                                <td>Cleaning servis</td>
                                <td><code>NAMA</code> saja</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var dropzone = document.querySelector('.import-dropzone');

        ['dragenter', 'dragover'].forEach(function (name) {
            dropzone.addEventListener(name, function (e) {
                e.preventDefault();
                dropzone.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(function (name) {
            dropzone.addEventListener(name, function (e) {
                e.preventDefault();
                dropzone.classList.remove('dragover');
            });
        });

        dropzone.addEventListener('drop', function (e) {
            var input = document.getElementById('file');
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                dropzone.querySelector('p.fw-semibold').textContent = e.dataTransfer.files[0].name;
            }
        });
    });
</script>
@endpush

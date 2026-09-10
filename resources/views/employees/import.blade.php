@extends('layouts.app')

@section('page_title', 'Import Massal Pegawai')
@section('page_subtitle', 'Tambah / perbarui banyak data pegawai sekaligus dari berkas Excel')

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

                @if (session('warning'))
                    <div class="alert alert-warning small py-2">
                        <i class="bi bi-exclamation-triangle me-1"></i> {{ session('warning') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('employees.import.store') }}" enctype="multipart/form-data">
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

                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-osdmrb px-4" data-loader-text="Sedang mengimpor data pegawai">
                            <i class="bi bi-upload"></i> Mulai Import
                        </button>
                        <a data-no-loader href="{{ route('employees.template') }}" class="btn btn-outline-success">
                            <i class="bi bi-file-earmark-arrow-down"></i> Unduh Template
                        </a>
                        <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary">Kembali</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-5 form-col-start">
            <div class="form-card mb-3">
                <h5><i class="bi bi-info-circle me-2"></i>Petunjuk Import</h5>
                <div class="alert alert-info py-2 small mb-3">
                    <i class="bi bi-lightbulb me-1"></i>
                    <strong>Berkas "Data Dashboard.xlsx" instansi bisa langsung diunggah</strong> tanpa perlu
                    memindahkan datanya ke template — format kolomnya sudah didukung.
                </div>
                <ol class="import-steps ps-3 mb-0">
                    <li>Unggah berkas Excel <strong>format Data Dashboard</strong> (NAMA, NIP, STATUS, Es. I, Es. II,
                        TMT JABATAN, PANGKAT, GOLONGAN, KENAIKAN PANGKAT, BATAS USIA PENSIUN, dst)
                        atau gunakan <strong>Unduh Template</strong> yang formatnya sama persis.</li>
                    <li>Baris pertama = judul kolom, jangan diubah.</li>
                    <li>Master diisi <strong>nama / kode</strong>:
                        <ul class="small text-muted mt-1 mb-1">
                            <li><code>STATUS</code> &mdash; PNS / CPNS / PPPK</li>
                            <li><code>PANGKAT</code> &amp; <code>GOLONGAN</code> &mdash; mis. Pembina / (IV.a)</li>
                            <li><code>LEVEL PENDIDIKAN</code> &mdash; SD s.d. S3</li>
                            <li><code>Es. II</code> / <code>Es. I</code> &mdash; nama unit kerja (unit baru otomatis dibuat)</li>
                        </ul>
                    </li>
                    <li>Format tanggal fleksibel: <code>12/1/2029</code>, <code>01 April 2028</code>, <code>2024-01-31</code>.</li>
                    <li>Pegawai dengan <strong>NIP sudah ada</strong> akan <strong>diperbarui</strong>, NIP baru akan ditambahkan.</li>
                    <li><code>BATAS USIA PENSIUN</code> kosong dihitung otomatis dari BUP
                        (60 th Eselon I/II &amp; Fungsional Madya, selain itu 58 th).</li>
                    <li>Baris yang tidak valid dilewati &amp; dilaporkan; baris lain tetap diproses.</li>
                </ol>
            </div>

            <div class="form-card">
                <h5><i class="bi bi-table me-2"></i>Kolom Penting</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-guide mb-0">
                        <thead><tr><th>Kolom</th><th>Wajib</th></tr></thead>
                        <tbody>
                            <tr><td><code>NAMA</code>, <code>NIP</code>, <code>JENIS KELAMIN</code></td><td class="text-danger">Ya</td></tr>
                            <tr><td><code>STATUS</code>, <code>PANGKAT</code>, <code>GOLONGAN</code></td><td class="text-muted">Tidak</td></tr>
                            <tr><td><code>Es. II</code>, <code>Es. I</code> (unit kerja)</td><td class="text-muted">Tidak</td></tr>
                            <tr><td><code>Nama Jabatan</code>, <code>Level Eselon</code>, <code>Level Fungsional</code></td><td class="text-muted">Tidak</td></tr>
                            <tr><td><code>TMT JABATAN</code>, <code>TMT GOL</code>, <code>TMT CPNS</code>, <code>TMT PNS</code></td><td class="text-muted">Tidak</td></tr>
                            <tr><td><code>KENAIKAN PANGKAT</code> (kapan naik jabatan)</td><td class="text-muted">Tidak</td></tr>
                            <tr><td><code>BATAS USIA PENSIUN</code></td><td class="text-muted">Tidak</td></tr>
                            <tr><td><code>TEMPAT LAHIR</code>, <code>TANGGAL LAHIR</code>, <code>AGAMA</code>, <code>ALAMAT</code>, <code>EMAIL</code>, <code>NO TLP</code></td><td class="text-muted">Tidak</td></tr>
                            <tr><td><code>LEVEL PENDIDIKAN</code>, <code>PENDIDIKAN TERAKHIR 1..3</code></td><td class="text-muted">Tidak</td></tr>
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

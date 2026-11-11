@extends('layouts.app')

{{-- Hanya role admin (Admin Bagian & Super Admin) yang boleh mengubah /
    menghapus baris kertas kerja peta jabatan — Biro SDM & pegawai read-only. --}}
@php($canManageKk = auth()->user()->isAdmin() || auth()->user()->isSuperAdmin())

@section('page_title', 'Dashboard Peta Jabatan')
@section('page_subtitle', 'Kertas kerja peta jabatan — kebutuhan, pemangku eksisting & kesenjangan per jabatan')

@section('content')

{{-- ================= FILTER =================
     Filter unit kerja (eselon I & tingkat balai, beserta seluruh
     turunannya) dan jenis jabatan fungsional. Kolom eksisting &
     kesenjangan kertas kerja mengikuti filter ini. --}}
<div class="filter-card mb-4">
    <form method="GET" action="{{ route('modules.peta-jabatan') }}">
        <div class="row g-2">
            <div class="col-lg-4 col-md-4 col-12">
                <label>Unit Kerja (Eselon I)</label>
                <select name="es1" class="form-select form-select-sm">
                    <option value="">Semua Unit Kerja</option>
                    @foreach ($filterOptions['es1List'] as $unit)
                        <option value="{{ $unit->id }}" {{ (string) ($filters['es1'] ?? '') === (string) $unit->id ? 'selected' : '' }}>
                            {{ $unit->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-4 col-md-4 col-12">
                <label>Unit Kerja Balai</label>
                <select name="balai" class="form-select form-select-sm">
                    <option value="">Semua Balai</option>
                    @foreach ($filterOptions['balaiList'] as $unit)
                        <option value="{{ $unit->id }}" {{ (string) ($filters['balai'] ?? '') === (string) $unit->id ? 'selected' : '' }}>
                            {{ $unit->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-4 col-md-4 col-12">
                <label>Jabatan Fungsional</label>
                <select name="jabatan" class="form-select form-select-sm">
                    <option value="">Semua Jenis Jabatan</option>
                    @foreach ($filterOptions['jabatanList'] as $name)
                        <option value="{{ $name }}" {{ ($filters['jabatan'] ?? '') === $name ? 'selected' : '' }}>
                            {{ $name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 d-flex gap-2 justify-content-end">
                <a href="{{ route('modules.peta-jabatan') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </a>
                <button class="btn btn-osdmrb btn-sm px-4">
                    <i class="bi bi-search"></i> Terapkan
                </button>
            </div>
        </div>
    </form>
</div>

{{-- ================= RINGKASAN STATISTIK =================
     Angka mengikuti kertas kerja + filter aktif. --}}
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
            <div>
                <span>Pemangku Jabatan Eksisting</span>
                <h2>{{ number_format($summary['existing']) }}</h2>
                <small>dihitung dari data pegawai aktif</small>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon accent"><i class="bi bi-clipboard2-check"></i></div>
            <div>
                <span>Kebutuhan Pegawai</span>
                <h2>{{ number_format($summary['required']) }}</h2>
                <small>total formasi pada kertas kerja</small>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-graph-down-arrow"></i></div>
            <div>
                <span>Total Kekurangan</span>
                <h2>{{ number_format($summary['shortage']) }}</h2>
                <small>kesenjangan jumlah belum terpenuhi</small>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon success"><i class="bi bi-diagram-3"></i></div>
            <div>
                <span>Jabatan Terpetakan</span>
                <h2>{{ number_format($summary['rows']) }}</h2>
                <small>baris jabatan &amp; jenjang kertas kerja</small>
            </div>
        </div>
    </div>
</div>

{{-- ================= GRAFIK EKSISTING vs KEBUTUHAN ================= --}}
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="chart-card h-100">
            <h5><i class="bi bi-bar-chart-line me-2"></i>Jabatan Fungsional — Eksisting vs Kebutuhan</h5>
            <div class="chart-wrap" style="height:420px"><canvas id="petaJabatanChart"></canvas></div>
            <small class="text-muted d-block mt-2">12 jenis jabatan fungsional dengan jumlah pegawai eksisting terbanyak sesuai filter.</small>
        </div>
    </div>

    {{-- ================= REKOMENDASI & STRATEGI ================= --}}
    <div class="col-lg-5">
        <div class="chart-card h-100">
            <h5><i class="bi bi-lightbulb me-2"></i>Rekomendasi</h5>
            <p class="small text-muted">
                Kolom <strong>Pemangku Jabatan Eksisting</strong> dan <strong>Kesenjangan Jumlah</strong> dihitung
                <strong>langsung dari data pegawai aktif</strong> mengikuti filter unit kerja, sehingga selalu mutakhir.
                Atribut kertas kerja (dukungan lintas program, intervensi 5 T, kualifikasi pendidikan, kompetensi,
                kebutuhan, alternatif pemenuhan &amp; rekomendasi utama) dikelola melalui import Excel.
            </p>
            <h5 class="mt-4"><i class="bi bi-rocket-takeoff me-2"></i>Strategi Tindak Lanjut</h5>
            <ul class="small text-muted mb-0">
                <li class="mb-2">Prioritaskan jabatan dengan kesenjangan terbesar untuk pengusulan formasi CPNS/PPPK.</li>
                <li class="mb-2">Perhatikan jabatan dengan pemangku tunggal untuk perencanaan suksesi &amp; regenerasi.</li>
                <li class="mb-2">Manfaatkan kolom Alternatif Penataan/Pemenuhan sebagai rencana mutasi &amp; pengembangan.</li>
                <li class="mb-0">Selenggarakan diklat fungsional sesuai kebutuhan peningkatan jenjang jabatan.</li>
            </ul>
        </div>
    </div>
</div>

{{-- ================= TABEL KERTAS KERJA PETA JABATAN =================
     Format persis KERTAS KERJA EXCEL — 10 kolom + No. Kolom eksisting &
     kesenjangan dihitung langsung dari data pegawai (fallback: angka
     snapshot berkas bila jabatan belum ada pemangkunya di data pegawai). --}}
<div class="table-card mb-4">
    <div class="card-header-custom flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Kertas Kerja Peta Jabatan</h5>
        <div class="d-flex gap-2 flex-wrap">
            @if (auth()->user()->isPrivileged())
                <a href="{{ route('modules.peta-jabatan.template') }}" class="btn btn-sm btn-outline-secondary" data-no-loader>
                    <i class="bi bi-file-earmark-arrow-down"></i> Template
                </a>
                <button class="btn btn-sm btn-outline-osdmrb" data-bs-toggle="modal" data-bs-target="#importKertasKerjaModal">
                    <i class="bi bi-file-earmark-arrow-up"></i> Import Excel
                </button>
            @endif
            <a href="{{ route('modules.peta-jabatan.export', array_filter($filters)) }}"
               class="btn btn-sm btn-outline-osdmrb" data-no-loader>
                <i class="bi bi-file-earmark-excel"></i> Ekspor Excel
            </a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle peta-jabatan-kk">
            <thead>
                <tr>
                    <th class="text-center" style="width:44px">No</th>
                    <th style="min-width:200px">Jabatan dan Jenjang</th>
                    <th style="min-width:190px">Dukungan Lintas Program</th>
                    <th style="min-width:130px">Intervensi Program 5 T</th>
                    <th style="min-width:230px">Kualifikasi Pendidikan</th>
                    <th style="min-width:230px">Kompetensi</th>
                    <th class="text-center" style="width:95px">Kebutuhan</th>
                    <th class="text-center" style="width:95px">Pemangku Jabatan Eksisting</th>
                    <th class="text-center" style="width:110px">Kesenjangan Jumlah</th>
                    <th style="min-width:230px">Alternatif Penataan/Pemenuhan</th>
                    <th style="min-width:230px">Rekomendasi Utama</th>
                    @if ($canManageKk)<th class="text-center" style="width:90px">Aksi</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($kertasKerja as $row)
                    <tr class="{{ $row->outside ? 'table-warning' : '' }}">
                        <td class="text-center text-muted">{{ $loop->iteration }}</td>
                        <td class="fw-semibold">
                            {{ $row->jenis }}
                            @if ($row->jenjang)
                                <span class="badge bg-primary-subtle text-primary-emphasis ms-1">{{ $row->jenjang }}</span>
                            @endif
                            @if ($row->outside)
                                <div class="small text-warning-emphasis mt-1">
                                    <i class="bi bi-exclamation-triangle"></i> Belum dipetakan di kertas kerja
                                </div>
                            @endif
                        </td>
                        <td class="small"><div class="kk-text">{{ $row->cross_program ?: '-' }}</div></td>
                        <td class="small"><div class="kk-text">{{ $row->program_intervention ?: '-' }}</div></td>
                        <td class="small"><div class="kk-text">{{ $row->education_qualification ?: '-' }}</div></td>
                        <td class="small"><div class="kk-text">{{ $row->competency ?: '-' }}</div></td>
                        <td class="text-center fw-semibold">{{ $row->required !== null ? number_format($row->required) : '-' }}</td>
                        <td class="text-center fw-semibold">
                            {{ number_format($row->existing) }}
                            @if ($row->existing_from_snapshot)
                                <div class="small text-muted fw-normal">data kertas kerja</div>
                            @endif
                        </td>
                        <td class="text-center">
                            @if ($row->gap === null)
                                <span class="text-muted">-</span>
                            @elseif ($row->gap < 0)
                                <span class="badge bg-danger-subtle text-danger-emphasis">{{ number_format($row->gap) }}</span>
                            @elseif ($row->gap > 0)
                                <span class="badge bg-info-subtle text-info-emphasis">+{{ number_format($row->gap) }}</span>
                            @else
                                <span class="badge bg-success-subtle text-success-emphasis">0</span>
                            @endif
                        </td>
                        <td class="small"><div class="kk-text">{{ $row->fulfillment_alternative ?: '-' }}</div></td>
                        <td class="small"><div class="kk-text">{{ $row->main_recommendation ?: '-' }}</div></td>
                        @if ($canManageKk)
                            <td class="text-center">
                                @if ($row->id)
                                    <div class="d-flex gap-1 justify-content-center">
                                        <button type="button" class="btn btn-sm btn-outline-osdmrb" title="Ubah baris"
                                                data-bs-toggle="modal" data-bs-target="#editKertasKerjaModal"
                                                data-kk-action="{{ route('modules.peta-jabatan.update', $row->id) }}"
                                                data-kk-name="{{ $row->name }}"
                                                data-kk-cross="{{ $row->cross_program }}"
                                                data-kk-intervensi="{{ $row->program_intervention }}"
                                                data-kk-kualifikasi="{{ $row->education_qualification }}"
                                                data-kk-kompetensi="{{ $row->competency }}"
                                                data-kk-kebutuhan="{{ $row->required }}"
                                                data-kk-alternatif="{{ $row->fulfillment_alternative }}"
                                                data-kk-rekomendasi="{{ $row->main_recommendation }}">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <form action="{{ route('modules.peta-jabatan.destroy', $row->id) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Hapus baris kertas kerja &quot;{{ $row->name }}&quot;?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="Hapus baris"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-muted" title="Baris di luar kertas kerja — tidak dapat diubah">-</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManageKk ? 12 : 11 }}" class="text-center text-muted py-4">
                            @if ($hasKertasKerja)
                                Tidak ada baris kertas kerja untuk filter ini.
                            @else
                                Belum ada kertas kerja peta jabatan.
                                @if (auth()->user()->isPrivileged())
                                    <br>Muat berkas <strong>KERTAS KERJA EXCEL</strong> melalui tombol
                                    <strong>Import Excel</strong> di atas.
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-3 py-2 small text-muted border-top">
        <i class="bi bi-info-circle me-1"></i>
        Kolom <strong>Pemangku Jabatan Eksisting</strong> &amp; <strong>Kesenjangan Jumlah</strong> dihitung langsung
        dari data pegawai aktif sesuai filter (kesenjangan = eksisting &minus; kebutuhan);
        tanda <span class="badge bg-danger-subtle text-danger-emphasis">merah</span> berarti kekurangan.
    </div>
</div>

{{-- ================= TABEL SEBARAN PER UNIT KERJA ================= --}}
<div class="table-card mb-4">
    <div class="card-header-custom">
        <h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Detail Sebaran per Unit Kerja</h5>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th class="text-center" style="width:44px">No</th>
                    <th>Jenis Jabatan Fungsional</th>
                    <th>Unit Kerja</th>
                    <th>Jenjang Terisi</th>
                    <th class="text-center">Jumlah Pegawai</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($table as $row)
                    <tr>
                        <td class="text-center text-muted">{{ $table->firstItem() + $loop->iteration - 1 }}</td>
                        <td class="fw-semibold">{{ $row->name }}</td>
                        <td>{{ $row->unit_name }}</td>
                        <td>
                            @if (empty($row->levels))
                                <small class="text-muted">-</small>
                            @else
                                @foreach ($row->levels as $level => $count)
                                    <span class="badge bg-primary-subtle text-primary-emphasis me-1">{{ $level }} &middot; {{ $count }}</span>
                                @endforeach
                            @endif
                        </td>
                        <td class="text-center fw-semibold">{{ number_format($row->existing_count) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            Belum ada data pegawai jabatan fungsional untuk filter ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $table->links() }}</div>
</div>

{{-- ================= MODAL IMPORT KERTAS KERJA ================= --}}
<div class="modal fade" id="importKertasKerjaModal" tabindex="-1" aria-hidden="true"
     aria-labelledby="importKertasKerjaModalTitle">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('modules.peta-jabatan.import') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="importKertasKerjaModalTitle">
                        <i class="bi bi-file-earmark-arrow-up me-2"></i>Import Kertas Kerja Peta Jabatan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">
                        Unggah berkas Excel kertas kerja peta jabatan (format 11 kolom — dapat diunduh
                        melalui tombol <strong>Template</strong>). Posisi judul kolom dikenali otomatis.
                    </p>
                    <div class="mb-2 small">
                        Kolom yang dimuat: <span class="badge bg-secondary-subtle text-secondary-emphasis me-1">Jabatan dan Jenjang</span><span class="badge bg-secondary-subtle text-secondary-emphasis me-1">Dukungan Lintas Program</span><span class="badge bg-secondary-subtle text-secondary-emphasis me-1">Intervensi Program 5 T</span><span class="badge bg-secondary-subtle text-secondary-emphasis me-1">Kualifikasi Pendidikan</span><span class="badge bg-secondary-subtle text-secondary-emphasis me-1">Kompetensi</span><span class="badge bg-secondary-subtle text-secondary-emphasis me-1">Kebutuhan</span><span class="badge bg-secondary-subtle text-secondary-emphasis me-1">Pemangku jabatan eksisiting</span><span class="badge bg-secondary-subtle text-secondary-emphasis me-1">Kesenjangan Jumlah</span><span class="badge bg-secondary-subtle text-secondary-emphasis me-1">Alternatif Penataan/Pemenuhan</span><span class="badge bg-secondary-subtle text-secondary-emphasis">Rekomendasi Utama</span>
                    </div>
                    <input type="file" name="file" class="form-control form-control-sm" accept=".xlsx,.xls,.csv" required>
                    <div class="alert alert-warning small mt-3 mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Import <strong>mengganti seluruh data</strong> kertas kerja sebelumnya. Kolom
                        Pemangku Jabatan Eksisting pada berkas hanya menjadi fallback — halaman tetap
                        menghitungnya langsung dari data pegawai aktif.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-osdmrb px-4">
                        <i class="bi bi-upload"></i> Muat Berkas
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ================= MODAL UBAH BARIS KERTAS KERJA (khusus role admin) ================= --}}
@if ($canManageKk)
    <div class="modal fade" id="editKertasKerjaModal" tabindex="-1" aria-hidden="true"
         aria-labelledby="editKertasKerjaModalTitle">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" id="formEditKertasKerja">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title" id="editKertasKerjaModalTitle">
                            <i class="bi bi-pencil-square me-2"></i>Ubah Baris Kertas Kerja
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Jabatan dan Jenjang <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control form-control-sm" maxlength="200" required>
                            <div class="form-text">Tulis lengkap beserta jenjang, mis. "Analis Kepegawaian Ahli Muda".</div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Dukungan Lintas Program</label>
                                <textarea name="cross_program" rows="3" class="form-control form-control-sm"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Intervensi Program 5 T</label>
                                <textarea name="program_intervention" rows="3" class="form-control form-control-sm"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Kualifikasi Pendidikan</label>
                                <textarea name="education_qualification" rows="3" class="form-control form-control-sm"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Kompetensi</label>
                                <textarea name="competency" rows="3" class="form-control form-control-sm"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Kebutuhan</label>
                                <input type="number" name="required_count" min="0" max="65535" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-8 d-flex align-items-end">
                                <div class="alert alert-info small mb-0 py-2 w-100">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Kolom <strong>Pemangku Jabatan Eksisting</strong> &amp; <strong>Kesenjangan Jumlah</strong>
                                    dihitung otomatis dari data pegawai aktif — tidak diubah manual.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Alternatif Penataan/Pemenuhan</label>
                                <textarea name="fulfillment_alternative" rows="3" class="form-control form-control-sm"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Rekomendasi Utama</label>
                                <textarea name="main_recommendation" rows="3" class="form-control form-control-sm"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-sm btn-osdmrb px-4">
                            <i class="bi bi-save"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

<style>
    /* Teks panjang kertas kerja: dibatasi 3 baris, klik untuk bentangkan */
    .peta-jabatan-kk .kk-text {
        white-space: pre-line;
        max-height: 4.1em;
        overflow: hidden;
        cursor: pointer;
        position: relative;
    }
    .peta-jabatan-kk .kk-text.kk-open { max-height: none; }
</style>

@endsection

@push('scripts')
{{-- Chart.js lokal: bebas dari blokir/ gangguan CDN. --}}
<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        /* ---- teks panjang kertas kerja: klik untuk bentangkan / lipat ---- */
        document.querySelectorAll('.peta-jabatan-kk .kk-text').forEach(function (el) {
            el.title = 'Klik untuk bentangkan / lipat';
            el.addEventListener('click', function () {
                el.classList.toggle('kk-open');
            });
        });

        /* ---- modal ubah baris kertas kerja: isi form sesuai tombol yang diklik ---- */
        var editModal = document.getElementById('editKertasKerjaModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function (event) {
                var btn = event.relatedTarget;
                var form = editModal.querySelector('#formEditKertasKerja');

                form.action = btn.getAttribute('data-kk-action');
                form.querySelector('[name="name"]').value = btn.getAttribute('data-kk-name') || '';
                form.querySelector('[name="cross_program"]').value = btn.getAttribute('data-kk-cross') || '';
                form.querySelector('[name="program_intervention"]').value = btn.getAttribute('data-kk-intervensi') || '';
                form.querySelector('[name="education_qualification"]').value = btn.getAttribute('data-kk-kualifikasi') || '';
                form.querySelector('[name="competency"]').value = btn.getAttribute('data-kk-kompetensi') || '';
                form.querySelector('[name="required_count"]').value = btn.getAttribute('data-kk-kebutuhan') || '';
                form.querySelector('[name="fulfillment_alternative"]').value = btn.getAttribute('data-kk-alternatif') || '';
                form.querySelector('[name="main_recommendation"]').value = btn.getAttribute('data-kk-rekomendasi') || '';
            });
        }

        const el = document.getElementById('petaJabatanChart');
        if (!el) return;

        /* pengaman: bila Chart gagal dimuat, tampilkan pesan */
        if (typeof Chart === 'undefined') {
            el.closest('.chart-wrap').innerHTML =
                '<div class="text-muted small text-center py-5">Pustaka grafik (Chart.js) gagal dimuat. Muat ulang halaman.</div>';
            return;
        }

        Chart.defaults.font = { family: "'Segoe UI', sans-serif", size: 11 };

        const themePrimary = @js(\App\Models\Setting::get('theme_primary', '#163d4f'));
        const chart = @json($chart->all());

        new Chart(el, {
            type: 'bar',
            data: {
                labels: chart.map((r) => r.label),
                datasets: [
                    {
                        label: 'Pegawai Eksisting',
                        data: chart.map((r) => r.existing),
                        backgroundColor: themePrimary,
                        borderRadius: 4
                    },
                    {
                        label: 'Kebutuhan (Kertas Kerja)',
                        data: chart.map((r) => r.required),
                        backgroundColor: '#e8a13c',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                animation: { duration: 400 },
                scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
                plugins: {
                    legend: { display: true, position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 12 } },
                    tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + c.parsed.x } }
                }
            }
        });
    });
</script>
@endpush

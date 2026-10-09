@extends('layouts.app')

@section('page_title', 'Analisis Jabatan Fungsional')
@section('page_subtitle', 'Pemetaan formasi & pemangku jabatan fungsional tertentu')

@section('content')

<!-- ================= RINGKASAN ================= -->
<div class="row g-3 mb-4">
    <div class="col-lg-4 col-md-6 col-6">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-clipboard2-data"></i></div>
            <div>
                <span>Jenis Jabatan</span>
                <h2>{{ number_format($totalJabatan) }}</h2>
                <small>fungsional tertentu</small>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 col-6">
        <div class="stat-card">
            <div class="stat-icon success"><i class="bi bi-person-check"></i></div>
            <div>
                <span>Total Pemangku</span>
                <h2>{{ number_format($totalPemangku) }}</h2>
                <small>pegawai aktif</small>
            </div>
        </div>
    </div>
    <!--<div class="col-lg-3 col-md-6 col-6">-->
    <!--    <div class="stat-card">-->
    <!--        <div class="stat-icon danger"><i class="bi bi-person-x"></i></div>-->
    <!--        <div>-->
    <!--            <span>Formasi Kosong</span>-->
    <!--            <h2>{{ number_format($jabatanKosong) }}</h2>-->
    <!--            <small>belum terisi</small>-->
    <!--        </div>-->
    <!--    </div>-->
    <!--</div>-->
    <div class="col-lg-4 col-md-6 col-6">
        <div class="stat-card">
            <div class="stat-icon accent"><i class="bi bi-bar-chart-steps"></i></div>
            <div>
                <span>Rasio Isi</span>
                <h2>{{ $totalJabatan > 0 ? number_format($totalPemangku / $totalJabatan, 1) : 0 }}</h2>
                <small>pemangku / jenis</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- ================= TABEL JABATAN ================= -->
    <div class="col-lg-7">
        <div class="table-card h-100">
            <div class="card-header-custom">
                <h5 class="mb-0"><i class="bi bi-list-columns me-2"></i>Daftar Jabatan Fungsional</h5>
                <span class="badge bg-primary-subtle text-primary">{{ $positions->total() }} jenis</span>
            </div>

            {{-- Filter nama jabatan & jenjang (Catatan 30 Sept 2026) --}}
            <form method="GET" class="px-3 pt-3">
                <div class="row g-2">
                    <div class="col-12 col-sm-6">
                        <input type="text" name="nama" class="form-control form-control-sm"
                               placeholder="Cari nama jabatan..." value="{{ request('nama') }}">
                    </div>
                    <div class="col-12 col-sm-4">
                        <select name="jenjang" class="form-select form-select-sm">
                            <option value="">Semua Jenjang</option>
                            @foreach ($jenjangOptions as $code => $label)
                                <option value="{{ $code }}" {{ request('jenjang') === $code ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    {{-- layar kecil: tombol menumpuk full-width agar mudah di-tap --}}
                    <div class="col-12 col-sm-2 d-flex gap-1 filter-actions">
                        <button class="btn btn-osdmrb btn-sm flex-fill" title="Terapkan"><i class="bi bi-search"></i></button>
                        <a href="{{ route('modules.analisis-jabatan') }}" class="btn btn-outline-secondary btn-sm" title="Reset"><i class="bi bi-x-lg"></i></a>
                    </div>
                </div>
            </form>
            <br>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:44px">No</th>
                            <th>Kode</th>
                            <th>Nama Jabatan</th>
                            <th>Jenjang</th>
                            <th class="text-center">Jumlah Pegawai</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($positions as $position)
                            <tr>
                                <td class="text-center text-muted">{{ $positions->firstItem() + $loop->iteration - 1 }}</td>
                                <td><code>{{ $position->code }}</code></td>
                                <td class="fw-semibold">{{ $position->name }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $position->jobLevel?->name ?? '-' }}</span></td>
                                <td class="text-center">
                                    {{-- Catatan 7 Okt 2026: pemangku diganti JUMLAH pegawai —
                                         angka bisa diklik utk memunculkan daftar pegawai --}}
                                    @if (($position->holders_all ?? []) !== [])
                                        <button type="button"
                                                class="btn btn-sm btn-osdmrb d-inline-flex align-items-center gap-1 holders-btn"
                                                data-bs-toggle="modal" data-bs-target="#daftarPegawaiModal"
                                                data-jabatan="{{ $position->name }}"
                                                data-holders='@json($position->holders_all)'
                                                title="Lihat daftar pegawai pemangku jabatan">
                                            <i class="bi bi-people-fill"></i> {{ $position->holders_count }} pegawai
                                        </button>
                                    @else
                                        {{ $position->holders_count }}
                                    @endif
                                </td>
                                <td>
                                    @if ($position->holders_count === 0)
                                        <span class="badge bg-danger-subtle text-danger">Kosong</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success">Terisi</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada jabatan fungsional yang cocok dengan filter (jabatan kosong tidak ditampilkan).</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3 px-3 pb-2">
                {{ $positions->links() }}
            </div>
        </div>
    </div>

    <!-- ================= DISTRIBUSI JENJANG ================= -->
    <div class="col-lg-5">
        <div class="chart-card h-100">
            <h5><i class="bi bi-bar-chart-steps me-2"></i>Distribusi Jenjang Fungsional</h5>

            @forelse ($jenjang as $row)
                @php
                    $max = $jenjang->max('total') ?: 1;
                    $pct = (int) round($row->total / $max * 100);
                    
                    $labelMap = [
                        'pertama'  => 'Fungsional Ahli Pertama',
                        'muda'     => 'Fungsional Ahli Muda',
                        'madya'    => 'Fungsional Ahli Madya',
                        'penyelia' => 'Fungsional Penyelia',
                        'terampil' => 'Fungsional Terampil',
                    ];

                    $displayLabel = $labelMap[strtolower(trim($row->label))] ?? $row->label;
                @endphp
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="fw-semibold">{{ $displayLabel }}</span>
                        <span class="text-muted">{{ $row->total }} pegawai</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar" style="width: {{ $pct }}%; background: var(--osdmrb-primary);"></div>
                    </div>
                </div>
            @empty
                <p class="text-center text-muted py-5 mb-0">Belum ada data jenjang fungsional pegawai.</p>
            @endforelse
        </div>
    </div>
</div>

<!-- ================= CATATAN ANALISIS (selebar full) ================= -->
<div class="chart-card mt-3">
    <h5><i class="bi bi-info-circle me-2"></i>Catatan Analisis</h5>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="d-flex gap-2 h-100">
                <i class="bi bi-search fs-5" style="color: var(--osdmrb-primary);"></i>
                <p class="small text-muted mb-0">Formasi kosong menjadi bahan <em>needs analysis</em> pengadaan CPNS/PPPK tahun berikutnya.</p>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="d-flex gap-2 h-100">
                <i class="bi bi-bar-chart-line fs-5" style="color: var(--osdmrb-primary);"></i>
                <p class="small text-muted mb-0">Kesenjangan jenjang (piramida) perlu ditinjau untuk keseimbangan karier fungsional.</p>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="d-flex gap-2 h-100">
                <i class="bi bi-clock-history fs-5" style="color: var(--osdmrb-primary);"></i>
                <p class="small text-muted mb-0">Pemangku diidentifikasi dari <em>nama jabatan</em> pada data pegawai aktif dan riwayat jabatan aktif — seluruh jenis jabatan fungsional di data pegawai otomatis dikenali.</p>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="daftarPegawaiModal" tabindex="-1" aria-hidden="true"
     aria-labelledby="daftarPegawaiModalTitle">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="daftarPegawaiModalTitle">Daftar Pegawai</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-0">
                <ul class="list-group list-group-flush holders-list"></ul>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    /* Popup daftar pegawai pemangku jabatan — diisi dari data atribut tombol
       jumlah pegawai (Catatan 7 Okt 2026). */
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('daftarPegawaiModal');
        if (!modal) return;

        modal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;

            var holders = [];
            try { holders = JSON.parse(btn.dataset.holders || '[]'); } catch (e) {}

            modal.querySelector('.modal-title').textContent =
                'Daftar Pegawai — ' + (btn.dataset.jabatan || '');

            var list = modal.querySelector('.holders-list');
            list.innerHTML = '';

            holders.forEach(function (p) {
                var li = document.createElement('li');
                li.className = 'list-group-item d-flex align-items-center gap-2';

                var initials = (p.name || '?').split(' ').map(function (w) { return w.charAt(0); }).slice(0, 2).join('').toUpperCase();
                var avatar = document.createElement('span');
                avatar.className = 'holder-avatar';
                avatar.textContent = initials;
                li.appendChild(avatar);

                var body = document.createElement('div');
                body.className = 'min-width-0';

                var nameRow = document.createElement('div');
                if (p.id) {
                    var a = document.createElement('a');
                    a.href = '{{ url('/employees') }}/' + p.id;
                    a.className = 'fw-semibold text-decoration-none';
                    a.textContent = p.name;
                    nameRow.appendChild(a);
                } else {
                    nameRow.className = 'fw-semibold';
                    nameRow.textContent = p.name;
                }
                body.appendChild(nameRow);

                if (p.unit) {
                    var unitRow = document.createElement('small');
                    unitRow.className = 'text-muted d-block text-truncate';
                    unitRow.textContent = p.unit;
                    body.appendChild(unitRow);
                }

                li.appendChild(body);
                list.appendChild(li);
            });

            if (! holders.length) {
                var empty = document.createElement('li');
                empty.className = 'list-group-item text-center text-muted py-4';
                empty.textContent = 'Belum ada pegawai pemangku jabatan ini.';
                list.appendChild(empty);
            }
        });
    });
</script>
@endpush

@extends('layouts.app')

@section('page_title', 'Diklat & Pengembangan Kompetensi')
@section('page_subtitle', 'Program pelatihan & pengembangan kompetensi aparatur')

@section('content')

@php($canManage = auth()->user()->isPrivileged())

{{-- ================= JENIS DIKLAT ================= --}}
<div class="row g-3 mb-4">
    @foreach ($jenis as $item)
        <div class="col-xl-3 col-md-6">
            <div class="rb-card h-100">
                <div class="rb-icon mb-2"><i class="bi {{ $item['icon'] }}"></i></div>
                <h6 class="fw-bold mb-1">{{ $item['name'] }}</h6>
                <p class="small text-muted mb-0">{{ $item['desc'] }}</p>
            </div>
        </div>
    @endforeach
</div>

{{-- ================= RIWAYAT DIKLAT ================= --}}
<div class="row g-3">
    <div class="col-lg-8">
        <div class="table-card h-100">
            <div class="card-header-custom">
                <h5 class="mb-0"><i class="bi bi-journal-check me-2"></i>Riwayat Diklat Pegawai ({{ $trainings->total() }})</h5>

                @if ($canManage)
                    <button class="btn btn-sm btn-osdmrb" data-bs-toggle="modal" data-bs-target="#modalTambahDiklat">
                        <i class="bi bi-plus-lg"></i> Tambah
                    </button>
                @endif
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Pegawai</th>
                            <th>Nama Diklat / Seminar / Pelatihan</th>
                            <th>Jenis</th>
                            <th>Lingkup</th>
                            <th>Penyelenggara</th>
                            <th class="text-center">Tahun</th>
                            <th class="text-center">Sertifikat</th>
                            @if ($canManage)<th class="text-center" style="width: 60px;">Aksi</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($trainings as $training)
                            <tr>
                                <td class="fw-semibold">{{ $training->employee?->name ?? '-' }}<br>
                                    <small class="text-muted">{{ $training->employee?->nip }}</small>
                                </td>
                                <td>{{ $training->name }}
                                    @if ($training->certificate_number)
                                        <br><small class="text-muted">No. {{ $training->certificate_number }}</small>
                                    @endif
                                </td>
                                <td><span class="badge bg-{{ $training->type_badge }}">{{ $training->type_label }}</span></td>
                                <td>
                                    <span class="badge {{ $training->scope === \App\Models\EmployeeTraining::SCOPE_ABROAD ? 'bg-danger' : 'bg-secondary bg-opacity-50' }}">
                                        {{ $training->scope_label }}
                                    </span>
                                </td>
                                <td>{{ $training->organizer ?? '-' }}</td>
                                <td class="text-center">{{ $training->year ?? '-' }}</td>
                                <td class="text-center">
                                    @if ($training->has_file)
                                        <a href="{{ route('modules.diklat.download', $training) }}" class="btn btn-sm btn-outline-osdmrb"
                                           data-no-loader title="Unduh sertifikat">
                                            <i class="bi bi-download"></i> <span class="d-lg-none">Unduh</span>
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                @if ($canManage)
                                    <td class="text-center">
                                        <form action="{{ route('modules.diklat.destroy', $training) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Hapus riwayat diklat ini?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canManage ? 8 : 7 }}" class="text-center py-5">
                                    <i class="bi bi-mortarboard text-muted" style="font-size: 2.4rem;"></i>
                                    <p class="text-muted mt-2 mb-1"><strong>Belum ada data diklat</strong></p>
                                    <p class="text-muted small mb-0">Data riwayat pelatihan pegawai akan ditampilkan di sini.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($trainings->hasPages())
                <div class="mt-3">{{ $trainings->links() }}</div>
            @endif
        </div>
    </div>

    <!-- ================= INFO PENGEMBANGAN ================= -->
    <div class="col-lg-4">
        <div class="chart-card h-100">
            <h5><i class="bi bi-lightbulb me-2"></i>Rencana Pengembangan</h5>
            <ul class="small text-muted ps-3 mb-4">
                <li class="mb-2">Integrasi data diklat dengan <strong>MyASN</strong>.</li>
                <li class="mb-2">Analisis <strong>training needs analysis</strong> (TNA) berbasis hasil analisis jabatan.</li>
                <li class="mb-2">Pencatatan sertifikat &amp; penghitungan <strong>jam pelatihan (JP)</strong> per pegawai.</li>
                <li>Notifikasi diklat wajib yang belum diikuti pegawai.</li>
            </ul>

            <h6 class="fw-bold"><i class="bi bi-calendar-event me-2"></i>Agenda Terdekat</h6>
            <div class="timeline">
                <div class="timeline-item">
                    <strong class="d-block small">PKT Bagi Pejabat Administrasi</strong>
                    <span class="text-muted small">Jadwal menunggu penetapan penyelenggara (LAN RI).</span>
                </div>
                <div class="timeline-item muted">
                    <strong class="d-block small">Diklat Fungsional Analis SDM</strong>
                    <span class="text-muted small">Menunggu kuota peserta dari BPSDM.</span>
                </div>
                <div class="timeline-item muted">
                    <strong class="d-block small">Pelatihan Teknis Kearsipan</strong>
                    <span class="text-muted small">Usulan tersampaikan ke Sekretariat Jenderal.</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ================= MODAL TAMBAH RIWAYAT DIKLAT ================= --}}
@if ($canManage)
    <div class="modal fade" id="modalTambahDiklat" tabindex="-1" aria-labelledby="modalTambahDiklatLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <form method="POST" action="{{ route('modules.diklat.store') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTambahDiklatLabel">
                            <i class="bi bi-plus-circle me-2"></i>Tambah Riwayat Diklat Pegawai
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="diklatEmployee">Pegawai Peserta <span class="text-danger">*</span></label>
                                <select name="employee_id" id="diklatEmployee" class="form-select @error('employee_id') is-invalid @enderror" required>
                                    <option value="">- Pilih Pegawai -</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}" {{ old('employee_id') == $employee->id ? 'selected' : '' }}>
                                            {{ $employee->name }} — {{ $employee->nip }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="diklatName">Nama Diklat <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="diklatName" class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name') }}" placeholder="mis. Pelatihan Teknis Kearsipan" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="diklatType">Jenis</label>
                                <select name="type" id="diklatType" class="form-select @error('type') is-invalid @enderror">
                                    @foreach (\App\Models\EmployeeTraining::TYPES as $value => $label)
                                        <option value="{{ $value }}" {{ old('type', 'TEKNIS') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="diklatScope">Lingkup Penyelenggaraan</label>
                                <select name="scope" id="diklatScope" class="form-select @error('scope') is-invalid @enderror">
                                    @foreach (\App\Models\EmployeeTraining::scopeOptions() as $value => $label)
                                        <option value="{{ $value }}" {{ old('scope', \App\Models\EmployeeTraining::SCOPE_DOMESTIC) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('scope')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="diklatOrganizer">Penyelenggara</label>
                                <input type="text" name="organizer" id="diklatOrganizer" class="form-control @error('organizer') is-invalid @enderror"
                                       value="{{ old('organizer') }}" placeholder="mis. LAN RI / BPSDM">
                                @error('organizer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="diklatYear">Tahun</label>
                                <input type="number" name="year" id="diklatYear" class="form-control @error('year') is-invalid @enderror"
                                       value="{{ old('year', now()->year) }}" min="1900" max="2100">
                                @error('year')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="diklatStart">Tanggal Mulai</label>
                                <input type="date" name="start_date" id="diklatStart" class="form-control @error('start_date') is-invalid @enderror"
                                       value="{{ old('start_date') }}">
                                @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="diklatEnd">Tanggal Selesai</label>
                                <input type="date" name="end_date" id="diklatEnd" class="form-control @error('end_date') is-invalid @enderror"
                                       value="{{ old('end_date') }}">
                                @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="diklatHours">Jam Pelatihan (JP)</label>
                                <input type="number" name="hours" id="diklatHours" class="form-control @error('hours') is-invalid @enderror"
                                       value="{{ old('hours') }}" min="0" placeholder="mis. 40">
                                @error('hours')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="diklatCert">Nomor Sertifikat</label>
                                <input type="text" name="certificate_number" id="diklatCert" class="form-control @error('certificate_number') is-invalid @enderror"
                                       value="{{ old('certificate_number') }}" placeholder="mis. 1234/LAN/2026">
                                @error('certificate_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="diklatFile">Sertifikat (PDF / JPG, maks. 10 MB)</label>
                                <input type="file" name="file" id="diklatFile" accept=".pdf,.jpg,.jpeg,.png"
                                       class="form-control @error('file') is-invalid @enderror">
                                @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-osdmrb" data-loader-text="Menyimpan riwayat diklat">
                            <i class="bi bi-save me-1"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@endsection

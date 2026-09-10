@extends('layouts.app')

@section('page_title', 'Pengajuan Surat')
@section('page_subtitle', 'Formulir layanan persuratan kepegawaian')

@section('content')

<div class="form-narrow">
<div class="row g-3">
    <div class="col-lg-12">

        <div class="form-card mb-3">
            <h5><i class="bi bi-file-earmark-plus me-2"></i>Formulir Pengajuan Surat</h5>

            <div class="alert alert-info small py-2">
                <i class="bi bi-info-circle me-1"></i>
                Alur layanan: <strong>Pengajuan</strong> &rarr; <strong>Verifikasi Admin</strong> &rarr;
                <strong>Persetujuan &amp; Terbit Nomor</strong> &rarr; <strong>Cetak PDF</strong>.
            </div>

            <form method="POST" action="{{ route('letters.store') }}">
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Jenis Surat <span class="text-danger">*</span></label>
                        <select name="letter_type_id" id="letter_type" class="form-select" required>
                            <option value="">- Pilih Jenis Surat -</option>
                            @foreach ($letterTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if (auth()->user()->isPrivileged() && $employees->isNotEmpty())
                        <div class="col-md-6">
                            <label class="form-label">Pegawai Pemohon (untuk pegawai lain)</label>
                            <select name="employee_id" class="form-select">
                                <option value="">- Pegawai sendiri -</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->name }} — {{ $employee->nip }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="col-md-6">
                        <label class="form-label">Perihal / Judul Surat <span class="text-danger">*</span></label>
                        <input type="text" name="subject" class="form-control" required
                               placeholder="mis. Surat Tugas Kegiatan Rapat Koordinasi">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Tanggal Surat <span class="text-danger">*</span></label>
                        <input type="date" name="letter_date" class="form-control" required
                               value="{{ old('letter_date', $letter->letter_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Tempat Kegiatan / Acara</label>
                        <input type="text" name="meta[place]" class="form-control" placeholder="mis. Jakarta"
                               value="{{ old('meta.place') }}">
                    </div>

                    <div class="col-md-3 col-6">
                        <label class="form-label">Tanggal Mulai</label>
                        <input type="date" name="meta[start_date]" class="form-control" value="{{ old('meta.start_date') }}">
                    </div>

                    <div class="col-md-3 col-6">
                        <label class="form-label">Tanggal Selesai</label>
                        <input type="date" name="meta[end_date]" class="form-control" value="{{ old('meta.end_date') }}">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Keperluan / Dasar <span class="text-danger">*</span></label>
                        <textarea name="purpose" rows="4" class="form-control" required
                                  placeholder="Jelaskan keperluan / dasar permohonan surat...">{{ old('purpose') }}</textarea>
                    </div>
                </div>

                <div class="form-footer">
                    <span class="form-footer-info">
                        <i class="bi bi-info-circle me-1"></i>pengajuan diverifikasi admin sebelum nomor diterbitkan
                    </span>
                    <a href="{{ route('letters.index') }}" class="btn btn-outline-secondary">Batal</a>
                    <button type="submit" class="btn btn-osdmrb px-4">
                        <i class="bi bi-send"></i> Kirim Pengajuan
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>
</div>

@endsection

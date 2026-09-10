@extends('layouts.app')

@section('page_title', $employee->exists ? 'Ubah Data Pegawai' : 'Tambah Pegawai')
@section('page_subtitle', 'Formulir data kepegawaian')

@section('content')

<div class="form-narrow">
<form method="POST"
      action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}"
      enctype="multipart/form-data">
    @csrf
    @if ($employee->exists)
        @method('PUT')
    @endif

    <div class="row g-3">

        <!-- ================= IDENTITAS ================= -->
        <div class="col-lg-8">
            <div class="form-card mb-3">
                <h5><i class="bi bi-person-vcard me-2"></i>Identitas Pegawai</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">NIP / ID Pegawai <span class="text-danger">*</span></label>
                        <input type="text" name="nip" class="form-control" value="{{ old('nip', $employee->nip) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $employee->name) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jenis Pegawai</label>
                        <select name="employee_type" class="form-select">
                            <option value="asn" {{ old('employee_type', $employee->employee_type ?? 'asn') === 'asn' ? 'selected' : '' }}>ASN (PNS / CPNS / PPPK)</option>
                            <option value="non_asn" {{ old('employee_type', $employee->employee_type ?? 'asn') === 'non_asn' ? 'selected' : '' }}>Non ASN</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Kategori Non ASN</label>
                        <input type="text" name="category" class="form-control" list="categoryList"
                               value="{{ old('category', $employee->category) }}" placeholder="Pramubakti / Security / ...">
                        <datalist id="categoryList">
                            <option value="Pramubakti"></option>
                            <option value="Security"></option>
                            <option value="Cleaning Service"></option>
                        </datalist>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                        <select name="gender" class="form-select" required>
                            <option value="L" {{ old('gender', $employee->gender) === 'L' ? 'selected' : '' }}>Laki-Laki</option>
                            <option value="P" {{ old('gender', $employee->gender) === 'P' ? 'selected' : '' }}>Perempuan</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Agama</label>
                        <select name="religion" class="form-select">
                            <option value="">- Pilih -</option>
                            @foreach (['Islam', 'Kristen', 'Katholik', 'Hindu', 'Buddha', 'Konghucu'] as $religion)
                                <option value="{{ $religion }}" {{ old('religion', $employee->religion) === $religion ? 'selected' : '' }}>{{ $religion }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tempat Lahir</label>
                        <input type="text" name="birth_place" class="form-control" value="{{ old('birth_place', $employee->birth_place) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tanggal Lahir</label>
                        <input type="date" name="birth_date" class="form-control" value="{{ old('birth_date', $employee->birth_date?->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $employee->email) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">No. Telepon</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone', $employee->phone) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Alamat</label>
                        <textarea name="address" rows="2" class="form-control">{{ old('address', $employee->address) }}</textarea>
                    </div>
                </div>
            </div>

            <!-- ================= KEPEGAWAIAN ================= -->
            <div class="form-card mb-3">
                <h5><i class="bi bi-briefcase me-2"></i>Data Kepegawaian</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Status Kepegawaian</label>
                        <select name="employment_status_id" class="form-select">
                            <option value="">- Pilih -</option>
                            @foreach ($statusList as $status)
                                <option value="{{ $status->id }}" {{ old('employment_status_id', $employee->employment_status_id) == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Golongan / Pangkat</label>
                        <select name="rank_id" class="form-select">
                            <option value="">- Pilih -</option>
                            @foreach ($rankList as $rank)
                                <option value="{{ $rank->id }}" {{ old('rank_id', $employee->rank_id) == $rank->id ? 'selected' : '' }}>{{ $rank->code }} {{ $rank->name ? '- ' . $rank->name : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Unit Kerja</label>
                        <select name="unit_id" class="form-select">
                            <option value="">- Pilih -</option>
                            @foreach ($unitList as $unit)
                                <option value="{{ $unit->id }}" {{ old('unit_id', $employee->unit_id) == $unit->id ? 'selected' : '' }}>{{ $unit->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jabatan (Riwayat)</label>
                        <select name="position_id" class="form-select">
                            <option value="">- Pilih -</option>
                            @foreach ($positionList as $position)
                                <option value="{{ $position->id }}" {{ old('position_id', $employee->currentPosition?->position_id) == $position->id ? 'selected' : '' }}>
                                    {{ $position->name }} ({{ $position->positionType?->name }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Nama Jabatan (Teks)</label>
                        <input type="text" name="position_name" class="form-control" value="{{ old('position_name', $employee->position_name) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Eselon (Struktural)</label>
                        <select name="eselon" class="form-select">
                            <option value="">- Bukan Struktural -</option>
                            @foreach (['II', 'III', 'IV'] as $eselon)
                                <option value="{{ $eselon }}" {{ old('eselon', $employee->eselon) === $eselon ? 'selected' : '' }}>Eselon {{ $eselon }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Level Fungsional</label>
                        <input type="text" name="functional_level" class="form-control" placeholder="mis. Fungsional Madya"
                               value="{{ old('functional_level', $employee->functional_level) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">TMT Jabatan</label>
                        <input type="date" name="tmt_jabatan" class="form-control" value="{{ old('tmt_jabatan', $employee->tmt_jabatan?->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">TMT Golongan</label>
                        <input type="date" name="tmt_golongan" class="form-control" value="{{ old('tmt_golongan', $employee->tmt_golongan?->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">TMT CPNS</label>
                        <input type="date" name="tmt_cpns" class="form-control" value="{{ old('tmt_cpns', $employee->tmt_cpns?->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">TMT PNS</label>
                        <input type="date" name="tmt_pns" class="form-control" value="{{ old('tmt_pns', $employee->tmt_pns?->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Kenaikan Pangkat / Jabatan</label>
                        <input type="date" name="next_promotion_date" class="form-control"
                               value="{{ old('next_promotion_date', $employee->next_promotion_date?->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Batas Usia Pensiun</label>
                        <input type="date" name="retirement_date" class="form-control" value="{{ old('retirement_date', $employee->retirement_date?->format('Y-m-d')) }}">
                        @if ($employee->computed_retirement_date)
                            <div class="form-text">Perhitungan BUP: {{ $employee->computed_retirement_date->translatedFormat('d F Y') }} ({{ $employee->bup }} tahun)</div>
                        @endif
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                                   {{ old('is_active', $employee->is_active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Pegawai Aktif</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= PENDIDIKAN & LAINNYA ================= -->
        <div class="col-lg-4 form-col-start">
            <div class="form-card mb-3">
                <h5><i class="bi bi-mortarboard me-2"></i>Pendidikan</h5>
                <div class="mb-3">
                    <label class="form-label">Tingkat Pendidikan Terakhir</label>
                    <select name="education_level_id" class="form-select">
                        <option value="">- Pilih -</option>
                        @foreach ($educationList as $education)
                            <option value="{{ $education->id }}" {{ old('education_level_id', $employee->education_level_id) == $education->id ? 'selected' : '' }}>{{ $education->name }}</option>
                        @endforeach
                    </select>
                </div>
                @foreach ([1 => 'Pendidikan Terakhir 1 (S1)', 2 => 'Pendidikan 2 (S2)', 3 => 'Pendidikan 3 (S3)'] as $i => $label)
                    @php
                        $defaults = $educationDefaults[$i] ?? ['campus' => '', 'major' => '', 'custom' => ''];
                        $selCampus = old("education_{$i}_campus", $defaults['campus']);
                        $isCustom = $selCampus === 'custom';
                    @endphp
                    <div class="mb-3">
                        <label class="form-label">{{ $label }}</label>
                        <div class="row g-2">
                            <div class="col-md-7">
                                <select name="education_{{ $i }}_campus" class="form-select education-campus-select"
                                        data-custom-target="educationCustom{{ $i }}">
                                    <option value="">- Pilih Kampus -</option>
                                    @foreach ($campusList as $campus)
                                        <option value="{{ $campus->id }}" {{ (string) $selCampus === (string) $campus->id ? 'selected' : '' }}>
                                            {{ $campus->name }}@if ($campus->city) &mdash; {{ $campus->city }}@endif
                                        </option>
                                    @endforeach
                                    <option value="custom" {{ $isCustom ? 'selected' : '' }}>-- Isi Manual --</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <input type="text" name="education_{{ $i }}_major" class="form-control"
                                       placeholder="Jurusan (opsional)" value="{{ old("education_{$i}_major", $defaults['major']) }}">
                            </div>
                        </div>
                        <input type="text" name="education_{{ $i }}_custom" id="educationCustom{{ $i }}"
                               class="form-control mt-2 {{ $isCustom ? '' : 'd-none' }}"
                               placeholder="Tulis nama kampus/institusi & jurusan manual"
                               value="{{ old("education_{$i}_custom", $defaults['custom']) }}">
                    </div>
                @endforeach
            </div>

            <div class="form-card">
                <h5><i class="bi bi-file-earmark-text me-2"></i>Administrasi</h5>
                <div class="mb-3">
                    <label class="form-label">NPWP</label>
                    <input type="text" name="npwp" class="form-control" value="{{ old('npwp', $employee->npwp) }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">No. Karpeg</label>
                    <input type="text" name="karpeg" class="form-control" value="{{ old('karpeg', $employee->karpeg) }}">
                </div>
            </div>
        </div>

        <!-- ================= AKSI ================= -->
        <div class="col-12">
            <div class="form-footer">
                <span class="form-footer-info">
                    <i class="bi bi-info-circle me-1"></i>kolom bertanda <span class="text-danger">*</span> wajib diisi
                </span>
                <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary">Batal</a>
                <button type="submit" class="btn btn-osdmrb px-4">
                    <i class="bi bi-save"></i> {{ $employee->exists ? 'Simpan Perubahan' : 'Simpan Pegawai' }}
                </button>
            </div>
        </div>

    </div>
</form>
</div>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // toggle input manual pendidikan saat dropdown kampus diganti
        document.querySelectorAll('.education-campus-select').forEach(function (select) {
            select.addEventListener('change', function () {
                var target = document.getElementById(this.dataset.customTarget);

                if (! target) return;

                target.classList.toggle('d-none', this.value !== 'custom');

                if (this.value === 'custom') {
                    target.focus();
                }
            });
        });
    });
</script>
@endpush

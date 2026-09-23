@extends('layouts.app')

@section('page_title', $employee->exists ? 'Ubah Pegawai Non ASN' : 'Tambah Pegawai Non ASN')
@section('page_subtitle', 'Data pegawai non ASN — security, cleaning service, pramubakti, dll')

@section('content')

<form method="POST"
      action="{{ $employee->exists ? route('employees.non-asn.update', $employee) : route('employees.non-asn.store') }}"
      class="form-narrow">
    @csrf
    @if ($employee->exists)
        @method('PUT')
    @endif

    <div class="row g-3">

        <div class="col-lg-8">
            <div class="form-card mb-3">
                <h5><i class="bi bi-person-badge me-2"></i>Data Pokok</h5>

                @if ($errors->any())
                    <div class="alert alert-danger small py-2">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                               value="{{ old('name', $employee->name) }}" required placeholder="Nama lengkap pegawai">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">ID Pegawai</label>
                        <input type="text" name="nip" class="form-control" maxlength="30"
                               value="{{ old('nip', $employee->nip) }}" placeholder="Kosongkan = dibuat otomatis">
                        <small class="text-muted">Contoh: <code>PB.060</code>, <code>SEC-KASNAN</code>. Kode unik dipakai
                            untuk import ulang dari Excel.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="category" class="form-control {{ $errors->has('category') ? 'is-invalid' : '' }}"
                               list="kategoriList" value="{{ old('category', $employee->category) }}" required
                               placeholder="Pilih / ketik kategori">
                        <datalist id="kategoriList">
                            @foreach ($categories as $category)
                                <option value="{{ $category }}"></option>
                            @endforeach
                        </datalist>
                        <small class="text-muted">Security, Cleaning Service, Pramubakti, atau kategori lain.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jenis Kelamin</label>
                        <select name="gender" class="form-select">
                            <option value="">-</option>
                            <option value="L" {{ old('gender', $employee->gender) === 'L' ? 'selected' : '' }}>Laki-laki</option>
                            <option value="P" {{ old('gender', $employee->gender) === 'P' ? 'selected' : '' }}>Perempuan</option>
                        </select>
                    </div>

                    <div class="col-md-7">
                        <label class="form-label">Jabatan</label>
                        <input type="text" name="position_name" class="form-control" list="jabatanList"
                               value="{{ old('position_name', $employee->position_name) }}"
                               placeholder="Mis. Koordinator, Pamdal, Chief, Secwan">
                        <datalist id="jabatanList">
                            <option value="Koordinator"></option>
                            <option value="Pamdal"></option>
                            <option value="Chief"></option>
                            <option value="Wadanru"></option>
                            <option value="Secwan"></option>
                        </datalist>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Unit Kerja Penempatan</label>
                        <select name="unit_id" class="form-select">
                            <option value="">-</option>
                            @foreach ($unitList as $unit)
                                <option value="{{ $unit->id }}"
                                        {{ (string) old('unit_id', $employee->unit_id) === (string) $unit->id ? 'selected' : '' }}>
                                    {{ $unit->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-card mb-3">
                <h5><i class="bi bi-person-vcard me-2"></i>Kontak (Opsional)</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">No. HP / Telepon</label>
                        <input type="tel" name="phone" class="form-control" maxlength="30"
                               value="{{ old('phone', $employee->phone) }}" placeholder="08xxxxxxxxxx">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control"
                               value="{{ old('email', $employee->email) }}" placeholder="nama@email.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Kemampuan Berenang</label>
                        <select name="swimming_skill" class="form-select">
                            <option value="">- Pilih -</option>
                            <option value="bisa" {{ old('swimming_skill', $employee->swimming_skill) === 'bisa' ? 'selected' : '' }}>Bisa Berenang</option>
                            <option value="tidak" {{ old('swimming_skill', $employee->swimming_skill) === 'tidak' ? 'selected' : '' }}>Tidak Bisa Berenang</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Kemampuan Bahasa Inggris</label>
                        <select name="english_skill" class="form-select">
                            <option value="">- Pilih -</option>
                            @foreach (\App\Models\Employee::ENGLISH_SKILLS as $value => $label)
                                <option value="{{ $value }}" {{ old('english_skill', $employee->english_skill) === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tanggal Lahir</label>
                        <input type="date" name="birth_date" class="form-control"
                               value="{{ old('birth_date', $employee->birth_date?->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Alamat</label>
                        <input type="text" name="address" class="form-control" maxlength="1000"
                               value="{{ old('address', $employee->address) }}" placeholder="Alamat domisili">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="form-card mb-3">
                <h5><i class="bi bi-sliders me-2"></i>Status</h5>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1"
                           id="is_active" {{ old('is_active', $employee->is_active ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Pegawai aktif bekerja</label>
                </div>
                <small class="text-muted d-block mb-3">
                    Pegawai non-aktif tetap tersimpan namun tidak dihitung dalam statistik dashboard.
                </small>
                <button type="submit" class="btn btn-osdmrb w-100 mb-2">
                    <i class="bi bi-check-lg"></i> {{ $employee->exists ? 'Simpan Perubahan' : 'Simpan Pegawai' }}
                </button>
                <a href="{{ route('employees.non-asn') }}" class="btn btn-outline-secondary w-100">Kembali</a>
            </div>
        </div>

    </div>
</form>

@endsection

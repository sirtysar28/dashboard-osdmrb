@extends('layouts.app')

@section('page_title', 'Ajukan Cuti')
@section('page_subtitle', 'Formulir Permohonan Cuti ASN dan PPPK')

@section('content')

<div class="form-narrow">
<form method="POST" action="{{ route('leaves.store') }}">
    @csrf
    @if (auth()->user()->isPrivileged())
        <div class="form-card mb-3" id="employeePicker" data-employees="{{ $employees }}">
            <h5><i class="bi bi-person-plus me-2"></i>Pengajuan Untuk Pegawai</h5>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Pilih Pegawai <span class="text-danger">*</span></label>
                    <div class="position-relative">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="employeeSearchInput" autocomplete="off"
                                   placeholder="Ketik nama / NIP pegawai..." aria-describedby="employeeSelectedText">
                        </div>
                        <div class="list-group position-absolute w-100 shadow"
                             id="employeeResults" style="z-index:1050; display:none; max-height:260px; overflow-y:auto; border-radius:10px"></div>
                        <input type="hidden" name="employee_id" id="employeeIdInput"
                               value="{{ old('employee_id', auth()->user()->employee_id) }}">
                    </div>
                    <div class="form-text" id="employeeSelectedText"></div>
                    <small class="text-muted d-block mt-1">Terisi otomatis dengan pegawai yang sedang login — ubah hanya bila mengajukan untuk pegawai lain.</small>
                </div>
            </div>
        </div>
    @endif

    {{-- ================= BAGIAN I : DATA PEGAWAI ================= --}}
    <div class="form-card mb-3">
        <h5><i class="bi bi-1-circle-fill me-2"></i>DATA PEGAWAI</h5>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Nama</label>
                <input type="text" class="form-control" id="fNama" readonly
                       value="{{ old('employee_id') ? null : auth()->user()->employee?->name }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">NIP</label>
                <input type="text" class="form-control" id="fNip" readonly
                       value="{{ old('employee_id') ? null : auth()->user()->employee?->nip }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Jabatan</label>
                <input type="text" class="form-control" id="fJabatan" readonly
                       value="{{ old('employee_id') ? null : auth()->user()->employee?->position_name }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Masa Kerja</label>
                <input type="text" class="form-control" id="fMasaKerja" readonly
                       value="{{ old('employee_id') ? null : auth()->user()->employee?->masa_kerja }}">
            </div>
            <div class="col-md-12">
                <label class="form-label">Unit Kerja (Eselon II)</label>
                <input type="text" class="form-control" id="fUnit" readonly
                       value="{{ old('employee_id') ? null : auth()->user()->employee?->unit?->name }}">
            </div>
        </div>
    </div>

    {{-- ================= BAGIAN II : JENIS CUTI YANG DIAMBIL ================= --}}
    <div class="form-card mb-3">
        <h5><i class="bi bi-2-circle-fill me-2"></i>JENIS CUTI YANG DIAMBIL <span class="text-danger">*</span></h5>
        <p class="small text-muted mb-3">Pilih salah satu dengan memberi tanda centang (v).</p>
        <div class="row g-2">
            @foreach (\App\Models\LeaveRequest::typeOptions() as $value => $label)
                <div class="col-md-6">
                    <label class="form-check cuti-check d-flex align-items-center gap-2 border rounded-3 px-3 py-2">
                        <input class="form-check-input m-0" type="radio" name="type" value="{{ $value }}"
                               {{ old('type') === $value ? 'checked' : '' }} required>
                        <span class="form-check-label">{{ $label }}</span>
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ================= BAGIAN III : ALASAN CUTI ================= --}}
    <div class="form-card mb-3">
        <h5><i class="bi bi-3-circle-fill me-2"></i>ALASAN CUTI <span class="text-danger">*</span></h5>
        <textarea name="reason" rows="3" class="form-control"
                  placeholder="Diisi alasan pengajuan cuti...">{{ old('reason') }}</textarea>
    </div>

    {{-- ================= BAGIAN IV : LAMANYA CUTI ================= --}}
    <div class="form-card mb-3">
        <h5><i class="bi bi-4-circle-fill me-2"></i>LAMANYA CUTI</h5>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Selama</label>
                <div class="input-group">
                    <input type="text" id="totalDays" class="form-control text-center fw-bold" readonly value="-">
                    <span class="input-group-text">hari</span>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Mulai Tanggal <span class="text-danger">*</span></label>
                <input type="date" name="start_date" id="startDate" class="form-control" required
                       value="{{ old('start_date', $leave->start_date?->format('Y-m-d')) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">s/d <span class="text-danger">*</span></label>
                <input type="date" name="end_date" id="endDate" class="form-control" required
                       value="{{ old('end_date', $leave->end_date?->format('Y-m-d')) }}">
            </div>
        </div>
    </div>

    {{-- ================= BAGIAN V : CATATAN CUTI ================= --}}
    @php($balanceYear = $balances['year'] ?? (int) now()->format('Y'))
    <div class="form-card mb-3">
        <h5><i class="bi bi-5-circle-fill me-2"></i>CATATAN CUTI</h5>
        <p class="small text-muted mb-2">
            Nominal sisa cuti tahunan kolom N&ndash;2 / N&ndash;1 / N <strong>terisi otomatis dari sistem</strong>
            (hak {{ \App\Models\LeaveRequest::ANNUAL_ENTITLEMENT }} hari/tahun dikurangi cuti tahunan yang pernah diajukan).
            @if ($isPrivileged)
                Sebagai Admin / Biro SDM (HRD), nominal dapat disunting dan <strong>keterangan cuti dapat diisi</strong>.
            @endif
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-2 small text-center">
                <thead>
                    <tr class="table-light">
                        <th rowspan="2" class="align-middle">Jenis Cuti</th>
                        <th colspan="3">Cuti Tahunan</th>
                        <th rowspan="2" class="align-middle">Keterangan</th>
                    </tr>
                    <tr class="table-light">
                        <th>N&ndash;2<br><small class="text-muted fw-normal">{{ $balanceYear - 2 }}</small></th>
                        <th>N&ndash;1<br><small class="text-muted fw-normal">{{ $balanceYear - 1 }}</small></th>
                        <th>N<br><small class="text-muted fw-normal">{{ $balanceYear }}</small></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-start">Cuti Tahunan <small class="text-muted">(sisa hari)</small></td>
                        <td>
                            <input type="number" min="0" max="366" name="annual_n2" id="annualN2" class="form-control form-control-sm text-center"
                                   value="{{ old('annual_n2', $balances['n2'] ?? null) }}" {{ $isPrivileged ? '' : 'readonly' }} placeholder="-">
                        </td>
                        <td>
                            <input type="number" min="0" max="366" name="annual_n1" id="annualN1" class="form-control form-control-sm text-center"
                                   value="{{ old('annual_n1', $balances['n1'] ?? null) }}" {{ $isPrivileged ? '' : 'readonly' }} placeholder="-">
                        </td>
                        <td>
                            <input type="number" min="0" max="366" name="annual_n" id="annualN" class="form-control form-control-sm text-center fw-bold"
                                   value="{{ old('annual_n', $balances['n'] ?? null) }}" {{ $isPrivileged ? '' : 'readonly' }} placeholder="-">
                        </td>
                        <td rowspan="6" class="align-middle">
                            @if ($isPrivileged)
                                <textarea name="leave_note" rows="4" class="form-control form-control-sm"
                                          placeholder="Keterangan catatan cuti (diisi Admin / Biro SDM)...">{{ old('leave_note') }}</textarea>
                            @else
                                <span class="text-muted small">Diisi oleh pejabat yang menangani bidang kepegawaian<br>(Admin / Biro SDM).</span>
                            @endif
                        </td>
                    </tr>
                    <tr><td class="text-start">Cuti Besar</td><td colspan="3" class="text-muted">&ndash;</td></tr>
                    <tr><td class="text-start">Cuti Sakit</td><td colspan="3" class="text-muted">&ndash;</td></tr>
                    <tr><td class="text-start">Cuti Melahirkan</td><td colspan="3" class="text-muted">&ndash;</td></tr>
                    <tr><td class="text-start">Cuti Karena Alasan Penting</td><td colspan="3" class="text-muted">&ndash;</td></tr>
                    <tr><td class="text-start">Cuti di Luar Tanggungan Negara</td><td colspan="3" class="text-muted">&ndash;</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ================= BAGIAN VI : ALAMAT SELAMA MENJALANKAN CUTI ================= --}}
    <div class="form-card mb-3">
        <h5><i class="bi bi-6-circle-fill me-2"></i>ALAMAT SELAMA MENJALANKAN CUTI</h5>
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Alamat</label>
                <textarea name="address_during_leave" rows="2" class="form-control"
                          placeholder="Diisi alamat pegawai berada di saat cuti...">{{ old('address_during_leave') }}</textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">Telepon</label>
                <input type="text" name="phone_during_leave" class="form-control" maxlength="30"
                       value="{{ old('phone_during_leave') }}" placeholder="No. Tlp/HP yang dapat dihubungi">
            </div>
        </div>
        <hr>
        <div class="text-end">
            <p class="mb-1 text-muted">Hormat saya,</p>
            <p class="mb-0 fw-bold" id="fTtdNama">{{ old('employee_id') ? null : auth()->user()->employee?->name }}</p>
            <p class="mb-0 small text-muted">NIP. <span id="fTtdNip">{{ old('employee_id') ? null : auth()->user()->employee?->nip }}</span></p>
        </div>
    </div>

    <div class="form-footer">
        <span class="form-footer-info">
            <i class="bi bi-info-circle me-1"></i>kolom bertanda <span class="text-danger">*</span> wajib diisi
        </span>
        <a href="{{ route('leaves.index') }}" class="btn btn-outline-secondary">Batal</a>
        <button type="submit" class="btn btn-osdmrb px-4">
            <i class="bi bi-send"></i> Kirim Pengajuan
        </button>
    </div>
</form>
</div>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        /* ---------- Pencarian & autofill pegawai (Bagian I) ---------- */
        var picker   = document.getElementById('employeePicker');
        var input    = document.getElementById('employeeSearchInput');
        var results  = document.getElementById('employeeResults');
        var hidden   = document.getElementById('employeeIdInput');
        var selected = document.getElementById('employeeSelectedText');

        var employees = [];
        if (picker) {
            try { employees = JSON.parse(picker.dataset.employees); } catch (e) {}
        }

        var selectedId = hidden && hidden.value ? parseInt(hidden.value, 10) : null;

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function findEmployee(id) {
            return employees.find(function (x) { return x.id === id; }) || null;
        }

        /* isi Bagian I + blok "Hormat saya" (input & teks) + sisa cuti tahunan (Bagian V) */
        function fill(id) {
            var e = findEmployee(id);

            var values = {
                fNama: e ? e.name : '',
                fNip: e ? e.nip : '',
                fJabatan: e ? (e.position_name || '-') : '',
                fMasaKerja: e ? e.masa_kerja : '',
                fUnit: e ? (e.unit_name || '-') : '',
                fTtdNama: e ? e.name : '-',
                fTtdNip: e ? e.nip : '-',
            };

            Object.keys(values).forEach(function (key) {
                var el = document.getElementById(key);
                if (!el) return;

                if (el.tagName === 'INPUT') {
                    el.value = values[key];
                } else {
                    el.textContent = values[key];
                }
            });

            /* sisa cuti tahunan N-2 / N-1 / N otomatis dari sistem per pegawai */
            ['annualN2', 'annualN1', 'annualN'].forEach(function (key, i) {
                var el = document.getElementById(key);
                if (!el || el.readOnly) return; // pegawai biasa: nilai server (miliknya)

                var bal = e && e.balances ? e.balances[['n2', 'n1', 'n'][i]] : null;
                el.value = bal === null || bal === undefined ? '' : bal;
            });
        }

        function renderSelected() {
            var e = findEmployee(selectedId);

            if (e) {
                input.value = e.name;
                selected.innerHTML = '<i class="bi bi-person-check-fill text-success me-1"></i>' +
                    escapeHtml(e.name) + ' — ' + escapeHtml(e.nip);
            } else {
                selected.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>Belum memilih pegawai.</span>';
            }
        }

        function renderResults(keyword) {
            var q = keyword.trim().toLowerCase();

            var matches = employees.filter(function (e) {
                if (!q) return true;
                return e.name.toLowerCase().indexOf(q) !== -1
                    || (e.nip || '').toLowerCase().indexOf(q) !== -1;
            });

            results.innerHTML = matches.length
                ? matches.slice(0, 50).map(function (e) {
                    return '<button type="button" class="list-group-item list-group-item-action py-2" data-id="' + e.id + '">' +
                           '<i class="bi bi-person me-1 text-secondary"></i><strong>' + escapeHtml(e.name) + '</strong>' +
                           '<small class="d-block text-muted" style="font-size:11px">' + escapeHtml(e.nip) +
                           (e.unit_name ? ' · ' + escapeHtml(e.unit_name) : '') + '</small></button>';
                }).join('')
                : '<div class="list-group-item text-muted small py-2">Pegawai tidak ditemukan.</div>';

            results.style.display = 'block';
        }

        if (picker) {
            fill(selectedId);
            renderSelected();

            input.addEventListener('focus', function () { renderResults(input.value); });

            input.addEventListener('input', function () {
                // mengetik ulang = batal pilihan lama (mencegah salah pegawai)
                selectedId = null;
                hidden.value = '';
                fill(null);
                renderResults(input.value);
                selected.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>Belum memilih pegawai.</span>';
            });

            results.addEventListener('click', function (event) {
                var item = event.target.closest('[data-id]');
                if (!item) return;

                selectedId = parseInt(item.dataset.id, 10);
                hidden.value = selectedId;

                fill(selectedId);
                renderSelected();
                results.style.display = 'none';
            });

            document.addEventListener('click', function (event) {
                if (!picker.contains(event.target)) results.style.display = 'none';
            });
        } else {
            // pegawai biasa: data pegawai sendiri (server-side) sudah terisi
        }

        /* ---------- Hitung otomatis "Selama ... hari" (Bagian IV) ---------- */
        var start = document.getElementById('startDate');
        var end = document.getElementById('endDate');
        var total = document.getElementById('totalDays');

        function update() {
            if (!start.value || !end.value) {
                total.value = '-';
                return;
            }

            var days = Math.round((new Date(end.value) - new Date(start.value)) / 86400000) + 1;
            total.value = days > 0 ? days : '!';
        }

        start.addEventListener('change', update);
        end.addEventListener('change', update);
        update();
    });
</script>
@endpush

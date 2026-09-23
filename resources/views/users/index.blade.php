@extends('layouts.app')

@section('page_title', 'Manajemen Pengguna')
@section('page_subtitle', 'Akun admin instansi & user pegawai')

@section('content')

<div class="row g-3">
    <div class="col-lg-4 form-col-start">
        <div class="chart-card">
            <h5><i class="bi bi-person-plus me-2"></i>Tambah Pengguna</h5>
            <form method="POST" action="{{ route('users.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Nama</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required minlength="8">
                </div>
                <div class="mb-3">
                    <label class="form-label">Data Pegawai (opsional)</label>
                    <div class="position-relative" id="employeePicker" data-employees="{{ $employees->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'nip' => $e->nip])->values() }}">
                        <input type="text" class="form-control" id="employeeSearchInput" autocomplete="off"
                               placeholder="Cari nama / NIP pegawai..." aria-describedby="employeeSelectedText">
                        <div class="list-group position-absolute w-100 shadow"
                             id="employeeResults" style="z-index:1050; display:none; max-height:260px; overflow-y:auto; border-radius:10px"></div>
                        <input type="hidden" name="employee_id" id="employeeIdInput" value="{{ old('employee_id') }}">
                        <div class="form-text" id="employeeSelectedText">- Tidak terhubung -</div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Peran</label>
                    <select name="role" class="form-select">
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-osdmrb w-100">Simpan Pengguna</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="table-card">
            <div class="card-header-custom export-btn">
                <h5 class="mb-0">Daftar Pengguna ({{ $users->total() }})</h5>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" data-no-loader href="{{ route('users.export', ['format' => 'xlsx']) }}">
                                <i class="bi bi-file-earmark-excel text-success"></i> Excel (.xlsx)
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" data-no-loader href="{{ route('users.export', ['format' => 'pdf']) }}">
                                <i class="bi bi-file-earmark-pdf text-danger"></i> PDF
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>Nama</th><th>Email</th><th>Pegawai</th><th>Peran</th><th>Status</th><th class="text-center">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td class="fw-semibold">{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td class="small">{{ $user->employee?->name ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $user->isAdmin() ? 'bg-danger' : ($user->isBiroSdm() ? 'bg-warning text-dark' : 'bg-primary') }}">
                                        {{ $user->role_label }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $user->is_active ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $user->is_active ? 'Aktif' : 'Non-aktif' }}
                                    </span>
                                </td>
                                <td class="text-center text-nowrap">
                                    <button class="btn btn-sm btn-outline-osdmrb" title="Ubah"
                                            data-bs-toggle="modal" data-bs-target="#editUser{{ $user->id }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ route('users.destroy', $user) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Hapus pengguna ini?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" {{ $user->id === auth()->id() ? 'disabled' : '' }}>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $users->links() }}</div>
        </div>
    </div>
</div>

{{-- Modals ubah pengguna --}}
@foreach ($users as $user)
    <div class="modal fade" id="editUser{{ $user->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 14px">
                <form method="POST" action="{{ route('users.update', $user) }}">
                    @csrf
                    @method('PUT')

                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" style="font-size: 15px; color: #143647">
                            <i class="bi bi-pencil-square me-1"></i> Ubah Pengguna
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required value="{{ old('name', $user->name) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required value="{{ old('email', $user->email) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password Baru</label>
                            <input type="password" name="password" class="form-control" minlength="8" placeholder="Kosongkan bila tidak diubah">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Data Pegawai</label>
                            <select name="employee_id" class="form-select">
                                <option value="">- Tidak terhubung -</option>
                                @foreach ($employees->merge($user->employee ? collect([$user->employee]) : collect())->unique('id') as $employee)
                                    <option value="{{ $employee->id }}" {{ old('employee_id', $user->employee_id) == $employee->id ? 'selected' : '' }}>
                                        {{ $employee->name }} — {{ $employee->nip }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Peran</label>
                            <select name="role" class="form-select">
                                @foreach ($roles as $value => $label)
                                    <option value="{{ $value }}" {{ old('role', $user->isSuperAdmin() ? 'super_admin' : ($user->isAdmin() ? 'admin' : ($user->isBiroSdm() ? 'biro_sdm' : 'pegawai'))) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="u_active_{{ $user->id }}"
                                   {{ old('is_active', $user->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="u_active_{{ $user->id }}">Akun aktif</label>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-osdmrb btn-sm px-4">
                            <i class="bi bi-save"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

@endsection

@push('scripts')
<script>
    /* ============ PENCARIAN PEGAWAI SAAT TAMBAH PENGGUNA ============ */
    document.addEventListener('DOMContentLoaded', function () {
        var picker   = document.getElementById('employeePicker');
        if (!picker) return;

        var input    = document.getElementById('employeeSearchInput');
        var results  = document.getElementById('employeeResults');
        var hidden   = document.getElementById('employeeIdInput');
        var selected = document.getElementById('employeeSelectedText');

        var employees = [];
        try { employees = JSON.parse(picker.dataset.employees); } catch (e) {}

        var selectedId = hidden.value ? parseInt(hidden.value, 10) : null;

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function renderSelected() {
            var employee = employees.find(function (e) { return e.id === selectedId; });

            if (employee) {
                selected.innerHTML = '<i class="bi bi-person-check-fill text-success me-1"></i>' +
                    escapeHtml(employee.name) + ' — ' + escapeHtml(employee.nip);
            } else if (selectedId) {
                selected.textContent = 'Pegawai #' + selectedId + ' terpilih.';
            } else {
                selected.textContent = '- Tidak terhubung -';
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
                           '<small class="d-block text-muted" style="font-size:11px">' + escapeHtml(e.nip) + '</small>' +
                           '</button>';
                }).join('')
                : '<div class="list-group-item text-muted small py-2">Pegawai tidak ditemukan.</div>';

            results.style.display = 'block';
        }

        input.addEventListener('focus', function () { renderResults(input.value); });

        input.addEventListener('input', function () {
            // mengetik ulang = batalkan pilihan sebelumnya
            selectedId = null;
            hidden.value = '';
            renderSelected();
            renderResults(input.value);
        });

        results.addEventListener('click', function (event) {
            var item = event.target.closest('[data-id]');
            if (!item) return;

            selectedId = parseInt(item.dataset.id, 10);
            hidden.value = selectedId;

            var employee = employees.find(function (e) { return e.id === selectedId; });
            input.value = employee ? employee.name : '';

            results.style.display = 'none';
            renderSelected();
        });

        document.addEventListener('click', function (event) {
            if (!picker.contains(event.target)) results.style.display = 'none';
        });

        renderSelected();
    });
</script>
@endpush

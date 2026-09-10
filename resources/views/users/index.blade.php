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
                    <select name="employee_id" class="form-select">
                        <option value="">- Tidak terhubung -</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->name }} — {{ $employee->nip }}</option>
                        @endforeach
                    </select>
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

@extends('layouts.app')

@section('page_title', 'Profil Saya')
@section('page_subtitle', 'Kelola informasi akun')

@section('content')

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="chart-card mb-3">
            <h5><i class="bi bi-person me-2"></i>Informasi Akun</h5>

            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PATCH')

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nama</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-osdmrb px-4"><i class="bi bi-save"></i> Simpan</button>
                </div>
            </form>
        </div>

        <div class="chart-card mb-3">
            <h5><i class="bi bi-key me-2"></i>Ganti Password</h5>
            <p class="small text-muted">
                Password minimal 8 karakter. Gunakan kombinasi yang mudah Anda ingat namun sulit ditebak.
            </p>

            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Password Saat Ini</label>
                    <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" required autocomplete="current-password">
                    @error('current_password')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Password Baru</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required minlength="8" autocomplete="new-password">
                        @error('password')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Konfirmasi Password Baru</label>
                        <input type="password" name="password_confirmation" class="form-control" required minlength="8" autocomplete="new-password">
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-osdmrb px-4"><i class="bi bi-shield-lock"></i> Ubah Password</button>
                </div>
            </form>
        </div>

        <div class="chart-card">
            <h5><i class="bi bi-person-x me-2"></i>Hapus Akun</h5>
            <p class="small text-muted">
                Menghapus akun akan menghapus seluruh data secara permanen. Pastikan Anda yakin.
            </p>
            <form method="POST" action="{{ route('profile.destroy') }}" onsubmit="return confirm('Yakin hapus akun ini?')">
                @csrf
                @method('DELETE')
                <input type="password" name="password" class="form-control mb-2" placeholder="Konfirmasi password" required>
                <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> Hapus Akun</button>
            </form>
        </div>
    </div>
</div>

@endsection

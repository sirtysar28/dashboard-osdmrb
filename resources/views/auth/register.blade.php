<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="mb-3">
            <label class="form-label small fw-semibold">Nama Lengkap</label>
            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required autofocus>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold">Email</label>
            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autocomplete="username">
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold">Password</label>
            <input type="password" name="password" class="form-control" required autocomplete="new-password">
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold">Konfirmasi Password</label>
            <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-osdmrb w-100 py-2 fw-semibold">
            <i class="bi bi-person-plus me-1"></i> Daftar
        </button>
    </form>

    <p class="text-center small text-muted mt-3 mb-0">
        Sudah punya akun? <a href="{{ route('login') }}" class="text-decoration-none">Masuk</a>
    </p>
</x-guest-layout>

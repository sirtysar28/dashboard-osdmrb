<x-guest-layout>
    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="mb-3">
            <label class="form-label small fw-semibold">Email</label>
            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
        </div>

        <button type="submit" class="btn btn-osdmrb w-100 py-2 fw-semibold">
            <i class="bi bi-envelope-arrow-up me-1"></i> Kirim Link Reset
        </button>
    </form>

    <p class="text-center small text-muted mt-3 mb-0">
        <a href="{{ route('login') }}" class="text-decoration-none">Kembali ke halaman masuk</a>
    </p>
</x-guest-layout>

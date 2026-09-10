<x-guest-layout>
    <div class="alert alert-warning small py-2">Ini adalah area aman. Konfirmasi password Anda untuk melanjutkan.</div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label small fw-semibold">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-osdmrb w-100 py-2 fw-semibold">Konfirmasi</button>
    </form>
</x-guest-layout>

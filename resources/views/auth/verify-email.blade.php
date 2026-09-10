<x-guest-layout>
    <div class="alert alert-info small py-2">
        Terima kasih! Verifikasi email Anda melalui tautan yang telah dikirim.
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-success small py-2">Tautan verifikasi baru telah dikirim.</div>
    @endif

    <div class="d-grid gap-2">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button class="btn btn-osdmrb w-100 py-2 fw-semibold">Kirim Ulang Tautan Verifikasi</button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn-outline-secondary w-100">Keluar</button>
        </form>
    </div>
</x-guest-layout>

<x-guest-layout>

    <div class="alert alert-info py-2 small mb-3">
        <i class="bi bi-shield-lock me-1"></i>
        <strong>Info:</strong> setiap pendaftaran akun baru harus diverifikasi &amp; disetujui
        terlebih dahulu oleh Administrator sebelum dapat digunakan untuk login.
        Gunakan email instansi/aktif agar pemberitahuan persetujuan terkirim.
    </div>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        {{-- honeypot anti-bot: tersembunyi dari manusia, bot spam biasanya mengisinya --}}
        <div class="mb-3" style="position:absolute; left:-9999px; top:-9999px;" aria-hidden="true">
            <label for="website">Website</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold">Nama Lengkap</label>
            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required autofocus>
            @error('name')
                <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold">Email</label>
            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required autocomplete="username">
            @error('email')
                <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold">Password</label>
            <input type="password" name="password" class="form-control" required autocomplete="new-password">
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold">Konfirmasi Password</label>
            <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
        </div>

        {{-- CAPTCHA HURUF (sama seperti form login) --}}
        <div class="mb-3">
            <label for="captcha" class="form-label small fw-semibold">Verifikasi Keamanan</label>
            <div class="captcha-box" id="captchaBox" role="group" aria-label="Kode captcha huruf">
                <span class="captcha-code" id="captchaCode" aria-hidden="true">
                    @foreach (str_split($captchaCode) as $char)
                        <span>{{ $char }}</span>
                    @endforeach
                </span>
                <button type="button" class="btn captcha-refresh" id="refreshCaptcha"
                        title="Ganti kode" aria-label="Ganti kode captcha">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
            <input type="text" id="captcha" name="captcha" class="form-control captcha-input mt-2"
                   autocomplete="off" spellcheck="false" required
                   placeholder="Ketik huruf di atas" aria-describedby="captchaHelp">
            <div id="captchaHelp" class="form-text small text-muted">
                Ketik {{ strlen($captchaCode) }} huruf yang tampil di atas (huruf besar/kecil diabaikan).
            </div>
            @error('captcha')
                <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-osdmrb w-100 py-2 fw-semibold">
            <i class="bi bi-person-plus me-1"></i> Daftar
        </button>
    </form>

    <p class="text-center small text-muted mt-3 mb-0">
        Sudah punya akun? <a href="{{ route('login') }}" class="text-decoration-none">Masuk</a>
    </p>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                /* ---------- Refresh captcha huruf (form register) ---------- */
                var refreshButton  = document.getElementById('refreshCaptcha');
                var captchaCodeEl  = document.getElementById('captchaCode');
                var captchaBox     = document.getElementById('captchaBox');
                var captchaInput   = document.getElementById('captcha');

                if (!refreshButton) return;

                function renderCode(code) {
                    captchaCodeEl.innerHTML = '';

                    code.split('').forEach(function (char) {
                        var span = document.createElement('span');
                        span.textContent = char;
                        captchaCodeEl.appendChild(span);
                    });
                }

                refreshButton.addEventListener('click', function () {
                    var icon = refreshButton.querySelector('i');

                    icon.classList.add('captcha-spinning');
                    refreshButton.disabled = true;

                    fetch("{{ route('captcha.refresh', ['for' => 'register']) }}", {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    })
                        .then(function (response) { return response.json(); })
                        .then(function (data) {
                            if (data.code) {
                                renderCode(data.code);
                                captchaBox.classList.remove('captcha-invalid');
                            }
                            captchaInput.value = '';
                            captchaInput.focus();
                        })
                        .catch(function () {})
                        .finally(function () {
                            icon.classList.remove('captcha-spinning');
                            refreshButton.disabled = false;
                        });
                });
            });
        </script>
    @endpush
</x-guest-layout>

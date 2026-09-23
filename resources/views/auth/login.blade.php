<x-guest-layout>
    <form method="POST" action="{{ route('login') }}" class="login-form" id="loginForm" novalidate>
        @csrf

        {{-- EMAIL --}}
        <div class="mb-3">
            <label for="email" class="form-label small fw-semibold">Email</label>
            <div class="input-group auth-input-group">
                <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                <input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}"
                       required autofocus autocomplete="username" placeholder="nama@instansi.go.id">
            </div>
            @error('email')
                <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>
            @enderror
        </div>

        {{-- PASSWORD + INTIP --}}
        <div class="mb-2">
            <label for="password" class="form-label small fw-semibold">Password</label>
            <div class="input-group auth-input-group">
                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                <input type="password" id="password" name="password" class="form-control" required
                       autocomplete="current-password" placeholder="Masukkan password">
                <button class="btn btn-outline-secondary password-toggle" type="button" id="togglePassword"
                        aria-label="Tampilkan password" aria-pressed="false" tabindex="-1">
                    <i class="bi bi-eye-fill" id="togglePasswordIcon"></i>
                </button>
            </div>
            @error('password')
                <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label small" for="remember">Ingat saya</label>
            </div>
            @if (Route::has('password.request'))
                <a class="small text-decoration-none fw-semibold" href="{{ route('password.request') }}">Lupa password?</a>
            @endif
        </div>

        {{-- CAPTCHA HURUF --}}
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

        <button type="submit" class="btn btn-auth-submit" id="loginSubmitBtn">
            <span class="btn-spinner me-1"></span>
            <span class="btn-label"><i class="bi bi-box-arrow-in-right me-1"></i> Masuk</span>
        </button>
    </form>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            /* ---------- Intip password ---------- */
            var passwordField = document.getElementById('password');
            var toggleButton  = document.getElementById('togglePassword');
            var toggleIcon    = document.getElementById('togglePasswordIcon');

            toggleButton.addEventListener('click', function () {
                var isHidden = passwordField.type === 'password';

                passwordField.type = isHidden ? 'text' : 'password';

                toggleIcon.classList.toggle('bi-eye-fill', !isHidden);
                toggleIcon.classList.toggle('bi-eye-slash-fill', isHidden);

                toggleButton.setAttribute('aria-label', isHidden ? 'Sembunyikan password' : 'Tampilkan password');
                toggleButton.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
            });

            /* ---------- Refresh captcha huruf ---------- */
            var refreshButton  = document.getElementById('refreshCaptcha');
            var captchaCodeEl  = document.getElementById('captchaCode');
            var captchaBox     = document.getElementById('captchaBox');
            var captchaInput   = document.getElementById('captcha');

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

                fetch('{{ route('captcha.refresh') }}', {
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

            /* ---------- Spinner saat submit login ---------- */
            var loginForm = document.getElementById('loginForm');
            var submitBtn = document.getElementById('loginSubmitBtn');

            loginForm.addEventListener('submit', function (event) {
                // cegah submit ganda
                if (submitBtn.disabled) {
                    event.preventDefault();
                    return;
                }

                if (!loginForm.checkValidity()) {
                    event.preventDefault();
                    captchaBox.classList.add('captcha-invalid');
                    setTimeout(function () { captchaBox.classList.remove('captcha-invalid'); }, 600);
                    return;
                }

                submitBtn.disabled = true;
                submitBtn.classList.add('loading');
                submitBtn.querySelector('.btn-label').innerHTML =
                    '<span class="loader-dots">' +
                        '<span></span><span></span><span></span>' +
                    '</span>&nbsp; Memverifikasi...';

                window.piShowLoader('Sedang memverifikasi & masuk ke dashboard...');
            });

            @if ($errors->has('captcha'))
                captchaBox.classList.add('captcha-invalid');
                setTimeout(function () { captchaBox.classList.remove('captcha-invalid'); }, 1200);
            @endif
        });
    </script>
    @endpush
</x-guest-layout>

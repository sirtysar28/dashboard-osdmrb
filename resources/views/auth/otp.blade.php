<x-guest-layout>
    <form method="POST" action="{{ route('login.otp.verify') }}" class="login-form" id="otpForm" novalidate>
        @csrf

        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge rounded-pill" style="background:rgba(232, 161, 60,.18);color:#e8a13c;">
                <i class="bi bi-shield-lock-fill me-1"></i> Lapisan Keamanan 2
            </span>
            <small class="text-body-secondary">Verifikasi OTP</small>
        </div>

        <h6 class="fw-bold mb-3">Kode OTP telah dikirim ke email Anda</h6>

        @if (session('status'))
            <div class="alert alert-success py-2 small">
                <i class="bi bi-check-circle me-1"></i> {{ session('status') }}
            </div>
        @endif

        <p class="small text-body-secondary">
            Masukkan 6 digit kode yang dikirim ke
            <strong>{{ $maskedEmail }}</strong> untuk menyelesaikan login.
            Kode berlaku <strong>10 menit</strong>.
        </p>

        {{-- kode OTP tampil saat mailer masih mode log (belum diset SMTP) --}}
        @if (session('otp_debug_code'))
            <div class="alert alert-warning py-2 small">
                <i class="bi bi-info-circle me-1"></i>
                Mode pengembangan (SMTP belum diatur di Pengaturan) &mdash; kode OTP Anda:
                <strong class="fs-6 tracking-widest">{{ session('otp_debug_code') }}</strong>
            </div>
        @endif

        <div class="mb-3">
            <label for="otp" class="form-label small fw-semibold">Kode OTP</label>
            <input type="text" id="otp" name="otp" class="form-control otp-input text-center"
                   inputmode="numeric" pattern="[0-9]*" maxlength="6"
                   autocomplete="one-time-code" required autofocus
                   placeholder="••••••" style="letter-spacing:.6em;font-size:22px;font-weight:700;">
            @error('otp')
                <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-auth-submit" id="otpSubmitBtn">
            <span class="btn-spinner me-1"></span>
            <span class="btn-label"><i class="bi bi-shield-check me-1"></i> Verifikasi &amp; Masuk</span>
        </button>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <button type="submit" form="otpCancelForm" class="btn btn-sm btn-link text-decoration-none px-0 text-secondary">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke login
            </button>

            <button type="submit" form="otpResendForm" id="otpResendBtn"
                    class="btn btn-sm btn-link text-decoration-none px-0 fw-semibold"
                    {{ $resendable ? '' : 'disabled' }}>
                <i class="bi bi-arrow-clockwise me-1"></i>
                <span id="otpResendLabel">{{ $resendable ? 'Kirim ulang kode' : 'Kirim ulang ('.max(0, (int) ($resendIn ?? 0)).'s)' }}</span>
            </button>
        </div>
    </form>

    <form method="POST" action="{{ route('login.otp.resend') }}" id="otpResendForm" class="d-none" data-no-loader>
        @csrf
    </form>

    <form method="POST" action="{{ route('login.otp.cancel') }}" id="otpCancelForm" class="d-none" data-no-loader>
        @csrf
    </form>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var otpInput = document.getElementById('otp');
            var form = document.getElementById('otpForm');
            var submitBtn = document.getElementById('otpSubmitBtn');

            /* ---------- Countdown tombol kirim ulang OTP ---------- */
            var resendBtn = document.getElementById('otpResendBtn');
            var resendLabel = document.getElementById('otpResendLabel');
            var remaining = {{ max(0, (int) ($resendIn ?? 0)) }};

            if (resendBtn && remaining > 0) {
                var timer = setInterval(function () {
                    remaining--;

                    if (remaining <= 0) {
                        clearInterval(timer);
                        resendBtn.disabled = false;
                        resendLabel.textContent = 'Kirim ulang kode';
                    } else {
                        resendLabel.textContent = 'Kirim ulang (' + remaining + 's)';
                    }
                }, 1000);
            }

            // hanya angka
            otpInput.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
            });

            form.addEventListener('submit', function (event) {
                if (submitBtn.disabled) {
                    event.preventDefault();
                    return;
                }

                if (otpInput.value.length !== 6) {
                    event.preventDefault();
                    otpInput.focus();
                    return;
                }

                submitBtn.disabled = true;
                submitBtn.classList.add('loading');
                submitBtn.querySelector('.btn-label').innerHTML =
                    '<span class="loader-dots"><span></span><span></span><span></span></span>&nbsp; Memverifikasi...';

                window.piShowLoader('Memverifikasi kode OTP...');
            });
        });
    </script>
    @endpush
</x-guest-layout>

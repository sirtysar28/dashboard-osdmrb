<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login', [
            'captchaCode' => $this->generateCaptcha(),
        ]);
    }

    /**
     * Generate a random letter captcha and store the answer in session.
     *
     * Hanya huruf (tanpa angka & karakter ambigu seperti I/O) agar mudah dibaca.
     * Panjang kode SELALU 5 karakter (konsisten, tidak acak 5-6).
     */
    private function generateCaptcha(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; // tanpa I & O agar tidak ambigu
        $length = 5;

        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        session(['login_captcha' => $code]);

        return $code;
    }

    /**
     * Refresh the login captcha (AJAX).
     */
    public function refreshCaptcha(): JsonResponse
    {
        return response()->json([
            'code' => $this->generateCaptcha(),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     *
     * Layer 1 : email + password + captcha.
     * Layer 2 : kode OTP yang dikirim ke email (bila diaktifkan di Pengaturan).
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // validasi captcha sudah dijalankan otomatis oleh LoginRequest (injection)

        // ---- Layer 1: kredensial ----
        $request->ensureIsNotRateLimited();

        $user = User::where('email', $request->string('email')->lower())->first();

        $credentialsValid = $user
            && $user->is_active
            && Auth::validate($request->only('email', 'password'));

        if (! $credentialsValid) {
            \Illuminate\Support\Facades\RateLimiter::hit($request->throttleKey());

            AuditLog::record(AuditLog::EVENT_LOGIN_FAILED, 'auth', 'Percobaan login gagal untuk '.$request->string('email'), user: $user);

            session(['login_captcha' => null]);

            return back()->withErrors(['email' => 'Kredensial tidak sesuai catatan kami.'])->onlyInput('email');
        }

        \Illuminate\Support\Facades\RateLimiter::clear($request->throttleKey());
        $request->session()->forget('login_captcha');

        // ---- Layer 2: OTP via email ----
        if (Setting::bool('otp_enabled', true)) {
            $this->sendOtpToUser($user, $request);

            $request->session()->put('otp_user_id', $user->id);
            $request->session()->put('otp_remember', $request->boolean('remember'));
            $request->session()->put('otp_attempts', 0);

            AuditLog::record(AuditLog::EVENT_OTP, 'auth', 'Layer 1 lolos, kode OTP dikirim ke email '.$user->email, user: $user);

            return redirect()->route('login.otp');
        }

        // ---- OTP nonaktif: langsung masuk ----
        return $this->completeLogin($user, $request, $request->boolean('remember'));
    }

    /**
     * Kirim kode OTP ke email pengguna.
     */
    public function sendOtpToUser(User $user, Request $request): void
    {
        $code = $user->generateOtp();

        Notifier::send(
            to: $user->email,
            type: 'otp',
            title: 'Kode Verifikasi Login (OTP)',
            greeting: 'Halo '.$user->name,
            lines: [
                'Seseorang (mungkin Anda) baru saja memasukkan email & password yang benar untuk masuk ke aplikasi.',
                'Gunakan kode OTP berikut untuk menyelesaikan proses login. Kode berlaku selama 10 menit.',
                'Jangan bagikan kode ini kepada siapa pun, termasuk pihak yang mengaku sebagai admin.',
            ],
            fields: ['Kode OTP' => $code, 'Email Akun' => $user->email, 'Waktu' => now()->setTimezone(config('app.timezone'))->format('d F Y H:i')],
        );

        // Bila mailer masih 'log' (belum disetting SMTP), tampilkan kode di layar
        // agar proses UAT/pengembangan tetap bisa berjalan.
        if (config('mail.default') === 'log' && Setting::bool('smtp_enabled') === false) {
            session()->flash('otp_debug_code', $code);
        }
    }

    /**
     * Finalisasi login (dipakai jalur langsung maupun setelah OTP).
     */
    public function completeLogin(User $user, Request $request, bool $remember): RedirectResponse
    {
        Auth::login($user, $remember);

        $request->session()->regenerate();

        AuditLog::record(AuditLog::EVENT_LOGIN, 'auth', 'Login berhasil');

        // notifikasi email "login baru"
        Notifier::send(
            to: $user->email,
            type: 'login',
            title: 'Notifikasi Login Baru',
            greeting: 'Halo '.$user->name,
            lines: [
                'Akun Anda baru saja berhasil masuk ke aplikasi Dashboard Biro OSDMRB.',
                'Apabila ini bukan Anda, segera ubah password dan hubungi Administrator Utama.',
            ],
            fields: [
                'Waktu' => now()->setTimezone(config('app.timezone'))->format('d F Y H:i'),
                'Alamat IP' => $request->ip(),
                'Peramban' => substr((string) $request->userAgent(), 0, 120) ?: '-',
            ],
            actionUrl: route('profile.edit'),
            actionText: 'Kelola Profil',
        );

        return redirect()->intended(
            route($user->isPrivileged() ? 'dashboard' : 'home', absolute: false)
        );
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        AuditLog::record(AuditLog::EVENT_LOGOUT, 'auth', 'Logout dari aplikasi');

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}

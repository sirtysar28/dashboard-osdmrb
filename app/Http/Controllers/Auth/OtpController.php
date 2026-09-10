<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Layer ke-2 proses login: verifikasi kode OTP yang dikirim ke email.
 */
class OtpController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    /**
     * Tampilkan form input kode OTP.
     */
    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('otp_user_id')) {
            return redirect()->route('login');
        }

        $user = User::find($request->session()->get('otp_user_id'));

        if (! $user) {
            $request->session()->forget(['otp_user_id', 'otp_remember', 'otp_attempts']);

            return redirect()->route('login')->withErrors(['email' => 'Sesi verifikasi tidak valid. Silakan login ulang.']);
        }

        $resendIn = $this->resendCountdown($user);

        return view('auth.otp', [
            'maskedEmail' => $this->maskEmail($user->email),
            'resendable' => $resendIn === 0,
            'resendIn' => $resendIn,
        ]);
    }

    /**
     * Sisa detik sebelum tombol "kirim ulang" boleh ditekan (0 = sudah boleh).
     */
    private function resendCountdown(User $user): int
    {
        if (! $user->otp_sent_at) {
            return 0;
        }

        $resendAt = $user->otp_sent_at->copy()->addSeconds(60);

        return $resendAt->isFuture() ? max(1, (int) ceil(now()->diffInRealSeconds($resendAt))) : 0;
    }

    /**
     * Verifikasi kode OTP & finalisasi login.
     */
    public function verify(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('otp_user_id');
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'Sesi verifikasi tidak valid. Silakan login ulang.']);
        }

        $request->validate([
            'otp' => ['required', 'digits:6'],
        ], [
            'otp.required' => 'Kode OTP wajib diisi.',
            'otp.digits' => 'Kode OTP harus 6 digit angka.',
        ]);

        $attempts = (int) $request->session()->get('otp_attempts', 0) + 1;
        $request->session()->put('otp_attempts', $attempts);

        if (! $user->verifyOtp((string) $request->input('otp'))) {
            AuditLog::record(AuditLog::EVENT_OTP, 'auth', 'Kode OTP salah', user: $user);

            if ($attempts >= self::MAX_ATTEMPTS) {
                $user->clearOtp();
                $request->session()->forget(['otp_user_id', 'otp_remember', 'otp_attempts']);

                return redirect()->route('login')->withErrors(['email' => 'Terlalu banyak percobaan kode OTP yang salah. Silakan login ulang.']);
            }

            return back()->withErrors(['otp' => 'Kode OTP tidak sesuai atau sudah kedaluwarsa. Sisa percobaan: '.(self::MAX_ATTEMPTS - $attempts)]);
        }

        $remember = (bool) $request->session()->pull('otp_remember', false);
        $request->session()->forget(['otp_user_id', 'otp_attempts']);

        $user->clearOtp();

        AuditLog::record(AuditLog::EVENT_OTP, 'auth', 'Verifikasi OTP berhasil', user: $user);

        return app(AuthenticatedSessionController::class)->completeLogin($user, $request, $remember);
    }

    /**
     * Kirim ulang kode OTP (dibatasi 1x per menit).
     */
    public function resend(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('otp_user_id');
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->otpResendAllowed()) {
            $seconds = $this->resendCountdown($user);

            return back()->withErrors(['otp' => $seconds > 0
                ? "Tunggu {$seconds} detik lagi sebelum meminta kode baru."
                : 'Mohon tunggu sebentar sebelum meminta kode baru.']);
        }

        app(AuthenticatedSessionController::class)->sendOtpToUser($user, $request);

        return back()->with('status', 'Kode OTP baru telah dikirim ke email Anda.');
    }

    /**
     * Batalkan proses OTP & kembali ke halaman login.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(['otp_user_id', 'otp_remember', 'otp_attempts']);

        return redirect()->route('login');
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $visible = min(2, strlen($name));
        $masked = substr($name, 0, $visible).str_repeat('*', max(0, strlen($name) - $visible));

        $domainParts = explode('.', $domain);
        $tld = array_pop($domainParts);
        $maskedDomain = str_repeat('*', max(0, strlen(implode('.', $domainParts)))).'.'.$tld;

        return $masked.'@'.$maskedDomain;
    }
}

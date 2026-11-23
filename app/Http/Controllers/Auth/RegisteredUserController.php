<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register', [
            // kode captcha huruf — key session khusus register (register_captcha)
            'captchaCode' => app(AuthenticatedSessionController::class)->generateCaptcha('register'),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * AKUN BARU TIDAK LANGSUNG AKTIF — berstatus "pending" dan harus
     * disetujui terlebih dahulu oleh Admin Bagian / Administrator Utama
     * melalui menu Manajemen Pengguna (anti-spam pendaftar liar).
     * Pendaftar juga TIDAK otomatis login setelah mendaftar.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],

            // captcha huruf — sama seperti form login, huruf besar/kecil diabaikan.
            // Kode lama dibuang setiap percobaan agar tidak bisa dipakai ulang.
            'captcha' => [
                'required',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $expected = session('register_captcha');

                    if ($expected === null || strtoupper(trim((string) $value)) !== strtoupper((string) $expected)) {
                        session(['register_captcha' => null]);

                        $fail('Kode verifikasi huruf tidak sesuai. Silakan periksa kembali dan coba lagi.');
                    }
                },
            ],

            // honeypot anti-bot: field tersembunyi yang wajib tetap kosong.
            // Terisi = hampir pasti bot spam -> validasi gagal.
            'website' => ['prohibited'],
        ]);

        // kode captcha sekali pakai — bersihkan setelah lolos validasi
        session(['register_captcha' => null]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_active' => false, // baru aktif setelah disetujui admin
            'registration_status' => User::REGISTRATION_PENDING,
        ]);

        // user baru otomatis menjadi pegawai (sekadar peran dasar —
        // tetap tidak bisa login sebelum disetujui admin)
        $user->assignRole('pegawai');

        event(new Registered($user));

        \App\Models\AuditLog::record(
            \App\Models\AuditLog::EVENT_REGISTER,
            'auth',
            'Registrasi akun baru (menunggu persetujuan admin)',
            user: $user,
        );

        // konfirmasi ke pendaftar: pendaftarannya diterima & sedang diverifikasi
        \App\Services\Notifier::send(
            to: $user->email,
            type: 'register',
            title: 'Pendaftaran Akun Diterima — Menunggu Persetujuan',
            greeting: 'Halo '.$user->name,
            lines: [
                'Terima kasih telah mendaftar pada aplikasi Dashboard Biro OSDMRB.',
                'Akun Anda saat ini BERSTATUS MENUNGGU PERSETUJUAN Administrator. Demi keamanan, setiap pendaftaran diverifikasi terlebih dahulu oleh tim kami.',
                'Anda akan menerima email pemberitahuan kembali begitu akun Anda disetujui dan dapat digunakan untuk login.',
            ],
            fields: [
                'Nama' => $user->name,
                'Email' => $user->email,
                'Waktu Mendaftar' => now()->setTimezone(config('app.timezone'))->format('d F Y H:i'),
                'Status' => 'Menunggu Persetujuan Admin',
            ],
        );

        // notifikasi email ke Administrator Utama untuk segera meninjau
        \App\Services\Notifier::notifyAdmins(
            type: 'register',
            title: 'Pendaftaran Akun Baru Menunggu Persetujuan',
            greeting: 'Halo Administrator Utama',
            lines: [
                'Ada pengguna baru yang mendaftar pada aplikasi Dashboard Biro OSDMRB.',
                'Akun TIDAK DAPAT LOGIN sampai Anda menyetujui pendaftaran ini. Silakan tinjau pada Manajemen Pengguna, lalu pilih Setujui atau Tolak.',
            ],
            fields: [
                'Nama' => $user->name,
                'Email' => $user->email,
                'Waktu' => now()->setTimezone(config('app.timezone'))->format('d F Y H:i'),
                'Status' => 'Menunggu Persetujuan',
            ],
            actionUrl: route('users.index', ['status' => 'pending']),
            actionText: 'Tinjau Pendaftar Baru',
        );

        // TIDAK ada Auth::login() — pendaftar harus menunggu persetujuan admin
        return redirect()
            ->route('login')
            ->with('status', 'Pendaftaran berhasil! Akun Anda menunggu persetujuan Administrator sebelum dapat digunakan untuk login. Silakan cek email Anda secara berkala.');
    }
}

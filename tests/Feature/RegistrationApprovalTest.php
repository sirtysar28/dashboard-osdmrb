<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Alur persetujuan akun pendaftar mandiri:
 * daftar -> pending (tidak bisa login) -> disetujui admin -> bisa login,
 * serta penolakan pendaftaran dan proteksi honeypot anti-spam.
 */
class RegistrationApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        // buka halaman register dulu agar captcha tersimpan di session
        $this->get('/register');

        return $this->post('/register', array_merge([
            'name' => 'Budi Pendaftar',
            'email' => 'budi@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'captcha' => session('register_captcha'),
        ], $overrides));
    }

    /** Buka halaman login (membangkitkan captcha di session) lalu submit kredensial. */
    private function attemptLogin(string $email = 'budi@example.com', string $password = 'Password123!'): \Illuminate\Testing\TestResponse
    {
        $this->get('/login');

        return $this->post('/login', [
            'email' => $email,
            'password' => $password,
            'captcha' => session('login_captcha'),
        ]);
    }

    /** Pendaftar baru harus berstatus pending, non-aktif, dan tidak otomatis login. */
    public function test_registrasi_menghasilkan_akun_pending_tanpa_login(): void
    {
        Role::firstOrCreate(['name' => 'pegawai']);

        $response = $this->register();

        $response->assertRedirect(route('login'));

        $user = User::where('email', 'budi@example.com')->first();
        $this->assertNotNull($user, 'Akun harus tersimpan.');
        $this->assertTrue($user->isPendingApproval());
        $this->assertFalse($user->is_active);
        $this->assertTrue($user->hasRole('pegawai'));
        $this->assertNull($user->approved_at);
        $this->assertGuest(); // tidak boleh auto-login setelah mendaftar
    }

    private function makeAdmin(): User
    {
        Role::firstOrCreate(['name' => 'admin']);

        $admin = User::create([
            'name' => 'Admin Bagian',
            'email' => 'admin@example.com',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
            'registration_status' => User::REGISTRATION_APPROVED,
            'approved_at' => now(),
        ]);
        $admin->assignRole('admin');

        return $admin;
    }

    /** Akun pending tidak bisa login walau kredensial benar. */
    public function test_akun_pending_tidak_bisa_login(): void
    {
        Role::firstOrCreate(['name' => 'pegawai']);
        $this->register();

        $response = $this->attemptLogin();

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /** Akun pending bisa disetujui admin, lalu bisa login. */
    public function test_admin_menyetujui_lalu_akun_bisa_login(): void
    {
        Role::firstOrCreate(['name' => 'pegawai']);
        $this->register();
        $user = User::where('email', 'budi@example.com')->first();

        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)
            ->post(route('users.approve', $user));

        // keluar dari sesi admin sebelum mencoba login sebagai pendaftar
        $this->post('/logout');

        $response->assertRedirect();
        $user->refresh();

        $this->assertTrue($user->registration_status === User::REGISTRATION_APPROVED);
        $this->assertTrue($user->is_active);
        $this->assertEquals($admin->id, $user->approved_by);
        $this->assertNotNull($user->approved_at);

        // OTP dinonaktifkan agar login langsung selesai
        \App\Models\Setting::set('otp_enabled', false);

        $login = $this->attemptLogin();

        $login->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    /** Akun yang ditolak tetap tersimpan namun tidak bisa login. */
    public function test_admin_menolak_pendaftaran(): void
    {
        Role::firstOrCreate(['name' => 'pegawai']);
        $this->register();
        $user = User::where('email', 'budi@example.com')->first();

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('users.reject', $user), ['reason' => 'bukan pegawai'])
            ->assertRedirect();

        $user->refresh();
        $this->assertTrue($user->isRejected());
        $this->assertFalse($user->is_active);
        $this->assertNotNull($user->fresh());
    }

    /** Akun buatan admin langsung berstatus approved. */
    public function test_akun_buatan_admin_langsung_disetujui(): void
    {
        Role::firstOrCreate(['name' => 'pegawai']);

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Akun Baru',
                'email' => 'baru@example.com',
                'password' => 'Password123!',
                'role' => 'pegawai',
            ])
            ->assertRedirect();

        $baru = User::where('email', 'baru@example.com')->first();
        $this->assertTrue($baru->registration_status === User::REGISTRATION_APPROVED);
        $this->assertNotNull($baru->approved_at);
    }

    /** Honeypot terisi (indikasi bot) => pendaftaran ditolak. */
    public function test_honeypot_memblokir_bot(): void
    {
        Role::firstOrCreate(['name' => 'pegawai']);
        $this->register(['website' => 'http://spam.example']);

        $this->assertDatabaseMissing('users', ['email' => 'budi@example.com']);
    }

    /** Captcha salah => pendaftaran ditolak dan akun tidak tersimpan. */
    public function test_captcha_salah_memblokir_pendaftaran(): void
    {
        Role::firstOrCreate(['name' => 'pegawai']);
        $this->register(['captcha' => 'ZZZZZ']);

        $this->assertDatabaseMissing('users', ['email' => 'budi@example.com']);
    }

    /** Submit register tanpa sempat membuka form (session captcha kosong) => ditolak. */
    public function test_daftar_tanpa_buka_form_ditolak(): void
    {
        Role::firstOrCreate(['name' => 'pegawai']);

        $this->post('/register', [
            'name' => 'Bot Langsung',
            'email' => 'bot@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'captcha' => 'ABCDE',
        ])->assertSessionHasErrors('captcha');

        $this->assertDatabaseMissing('users', ['email' => 'bot@example.com']);
    }
}

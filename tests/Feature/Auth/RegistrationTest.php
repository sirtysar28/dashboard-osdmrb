<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        // registrasi memberi role "pegawai" -> pastikan role tersedia
        \Spatie\Permission\Models\Role::create(['name' => 'pegawai']);

        // buka form dulu agar captcha tersimpan di session
        $this->get('/register');

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'captcha' => session('register_captcha'),
        ]);

        // akun baru TIDAK otomatis login — menunggu persetujuan admin
        $this->assertGuest();
        $response->assertRedirect(route('login', absolute: false));

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->isPendingApproval());
        $this->assertFalse($user->is_active);
    }
}

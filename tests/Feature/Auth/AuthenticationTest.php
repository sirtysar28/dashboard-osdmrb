<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        // buka halaman login agar captcha huruf tersimpan di session
        $this->get('/login');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'captcha' => session('login_captcha'),
        ]);

        $this->assertAuthenticated();
        // user factory tanpa role = pegawai -> diarahkan ke beranda
        $response->assertRedirect(route('home', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->get('/login');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'captcha' => session('login_captcha'),
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}

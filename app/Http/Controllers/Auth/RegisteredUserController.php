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
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_active' => true,
        ]);

        // user baru otomatis menjadi pegawai
        $user->assignRole('pegawai');

        event(new Registered($user));

        \App\Models\AuditLog::record(\App\Models\AuditLog::EVENT_REGISTER, 'auth', 'Registrasi akun baru', user: $user);

        // notifikasi email ke Administrator Utama
        \App\Services\Notifier::notifyAdmins(
            type: 'register',
            title: 'Registrasi Akun Baru',
            greeting: 'Halo Administrator Utama',
            lines: [
                'Ada pengguna baru yang mendaftar pada aplikasi Dashboard Biro OSDMRB.',
                'Akun baru otomatis mendapat peran Pegawai — silakan tinjau dan hubungkan datanya dengan data pegawai.',
            ],
            fields: [
                'Nama' => $user->name,
                'Email' => $user->email,
                'Waktu' => now()->setTimezone(config('app.timezone'))->format('d F Y H:i'),
            ],
            actionUrl: route('users.index'),
            actionText: 'Buka Manajemen Pengguna',
        );

        Auth::login($user);

        return redirect()->route('home');
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        \App\Models\AuditLog::record(\App\Models\AuditLog::EVENT_PASSWORD, 'auth', 'Password diubah oleh pengguna');

        // notifikasi email konfirmasi ganti password
        \App\Services\Notifier::send(
            to: $request->user()->email,
            type: 'password',
            title: 'Password Akun Berhasil Diubah',
            greeting: 'Halo '.$request->user()->name,
            lines: [
                'Password akun Dashboard Biro OSDMRB Anda baru saja berhasil diubah.',
                'Apabila Anda tidak merasa mengubahnya, segera hubungi Administrator Utama.',
            ],
            fields: [
                'Waktu' => now()->setTimezone(config('app.timezone'))->format('d F Y H:i'),
                'Alamat IP' => $request->ip(),
            ],
            actionUrl: route('profile.edit'),
            actionText: 'Kelola Profil',
        );

        return back()->with('status', 'password-updated');
    }
}

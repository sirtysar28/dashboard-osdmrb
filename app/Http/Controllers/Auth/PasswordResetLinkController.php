<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Pastikan pengiriman email benar-benar terkonfigurasi: bila mailer masih
        // 'log' dan SMTP belum diaktifkan di Pengaturan, tautan reset tidak akan
        // pernah sampai — sampaikan apa adanya alih-alih diam (link "hilang").
        if (config('mail.default') === 'log' && ! Setting::bool('smtp_enabled')) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Pengiriman email belum dikonfigurasi. Silakan hubungi Administrator Utama untuk mengaktifkan SMTP pada menu Pengaturan &gt; SMTP &amp; Notifikasi.']);
        }

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}

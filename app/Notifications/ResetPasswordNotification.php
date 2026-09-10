<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Services\Notifier;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Email reset password dengan desain HTML aplikasi.
 *
 * Menggantikan notifikasi bawaan Laravel agar pengiriman memakai SMTP
 * yang dikonfigurasi Administrator Utama di menu Pengaturan (bukan
 * mailer bawaan .env yang bisa saja masih 'log' — penyebab tautan
 * reset password tidak pernah sampai ke email pengguna).
 */
class ResetPasswordNotification extends ResetPassword
{
    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        // terapkan SMTP dari Pengaturan sebelum email dibangun & dikirim
        Notifier::applySmtpConfig();

        $resetUrl = $this->resetUrl($notifiable);
        $expires = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        $viewData = [
            'type' => 'reset',
            'title' => 'Permintaan Reset Password',
            'greeting' => 'Halo '.$notifiable->name,
            'lines' => [
                'Kami menerima permintaan reset password untuk akun Anda pada aplikasi Dashboard Biro OSDMRB.',
                'Klik tombol di bawah untuk mengatur password baru. Tautan berlaku '.$expires.' menit dan hanya dapat digunakan satu kali.',
                'Jika Anda tidak pernah meminta reset password, abaikan email ini — password Anda tidak akan berubah.',
            ],
            'fields' => [
                'Email Akun' => $notifiable->getEmailForPasswordReset(),
                'Waktu Diminta' => now()->setTimezone(config('app.timezone'))->format('d F Y H:i'),
            ],
            'actionUrl' => $resetUrl,
            'actionText' => 'Atur Password Baru',
            'primary' => Setting::get('theme_primary', '#163d4f'),
            'accent' => Setting::get('theme_accent', '#e8a13c'),
            'appName' => config('app.name', 'Dashboard Biro OSDMRB'),
            'year' => now()->year,
        ];

        return (new MailMessage)
            ->subject('[Dashboard Biro OSDMRB] Permintaan Reset Password')
            ->view('emails.app-notification', $viewData);
    }

    /**
     * URL halaman reset password (dipakai tombol aksi pada email).
     *
     * @param  mixed  $notifiable
     */
    protected function resetUrl($notifiable): string
    {
        if (static::$createUrlCallback) {
            return call_user_func(static::$createUrlCallback, $notifiable, $this->token);
        }

        return route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}

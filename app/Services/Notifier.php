<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Notifikasi email aplikasi dengan SMTP yang dikonfigurasi dari menu Pengaturan.
 *
 * Macam notifikasi (bisa dinyalakan/dimatikan per jenis oleh super admin):
 * - login      : notifikasi login baru ke pemilik akun
 * - password   : konfirmasi ganti password ke pemilik akun
 * - register   : pemberitahuan registrasi akun baru ke admin
 * - sop        : pemberitahuan pengajuan/unggah dokumen SOP ke admin
 * - letter     : pemberitahuan pengajuan surat ke admin
 *
 * Semua email memakai template HTML yang mengikuti desain aplikasi.
 */
class Notifier
{
    /**
     * Terapkan konfigurasi SMTP dari Pengaturan ke runtime Laravel.
     */
    public static function applySmtpConfig(): void
    {
        if (! Setting::bool('smtp_enabled')) {
            return; // gunakan konfigurasi bawaan .env
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'host' => (string) Setting::get('smtp_host', config('mail.mailers.smtp.host')),
            'port' => (int) Setting::get('smtp_port', 587),
            'encryption' => (string) Setting::get('smtp_encryption', 'tls'),
            'username' => (string) Setting::get('smtp_username'),
            'password' => (string) Setting::get('smtp_password'),
            'timeout' => null,
            'local_domain' => parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost',
        ]);
        Config::set('mail.from.address', (string) Setting::get('smtp_from_address', config('mail.from.address')));
        Config::set('mail.from.name', (string) Setting::get('smtp_from_name', config('mail.from.name')));
    }

    /**
     * Kirim notifikasi HTML ke satu email.
     *
     * @param string      $type    jenis notifikasi (login|password|register|sop|letter|otp|test)
     * @param string      $title   judul email
     * @param string      $greeting sapaan pembuka
     * @param array       $lines   paragraf isi
     * @param array       $fields  pasangan label => nilai (tabel ringkas)
     * @param string|null $actionUrl  tombol aksi
     * @param string|null $actionText label tombol aksi
     */
    public static function send(
        string $to,
        string $type,
        string $title,
        string $greeting,
        array $lines = [],
        array $fields = [],
        ?string $actionUrl = null,
        ?string $actionText = null,
    ): bool {
        // notifikasi ke admin bergantung toggle; notifikasi ke pengirim tetap dikirim
        $toggleMap = [
            'login' => 'notify_login',
            'password' => 'notify_password',
            'register' => 'notify_register',
            'sop' => 'notify_sop',
            'letter' => 'notify_letter',
            'leave' => 'notify_leave',
        ];

        if (isset($toggleMap[$type]) && ! Setting::bool($toggleMap[$type])) {
            return false;
        }

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        self::applySmtpConfig();

        $mailable = new \App\Mail\AppNotification(
            $type, $title, $greeting, $lines, $fields, $actionUrl, $actionText
        );

        try {
            Mail::to($to)->send($mailable);

            return true;
        } catch (\Throwable $e) {
            Log::warning("Gagal mengirim notifikasi email ({$type}) ke {$to}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Kirim notifikasi ke semua Administrator Utama
     * (atau satu email tujuan khusus bila diisi di Pengaturan).
     */
    public static function notifyAdmins(
        string $type,
        string $title,
        string $greeting,
        array $lines = [],
        array $fields = [],
        ?string $actionUrl = null,
        ?string $actionText = null,
    ): void {
        $recipients = [];

        $custom = trim((string) Setting::get('notify_recipient'));

        if ($custom !== '') {
            $recipients = array_filter(array_map('trim', explode(',', $custom)), 'filter_var');
        } else {
            $recipients = User::role('super_admin')->where('is_active', true)->pluck('email')->all();
        }

        foreach (array_filter($recipients) as $email) {
            self::send($email, $type, $title, $greeting, $lines, $fields, $actionUrl, $actionText);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Pengaturan aplikasi — HANYA Administrator Utama (super admin).
 *
 * 1. /pengaturan/tampilan  : visibilitas menu sidebar + tema warna & desain
 * 2. /pengaturan/pengumuman: pengumuman (gambar + running text) dashboard
 * 3. /pengaturan/smtp      : SMTP & notifikasi email
 */
class SettingsController extends Controller
{
    /* ===================== TAMPILAN & MENU ===================== */

    public function appearance(): View
    {
        return view('settings.appearance', [
            'menus' => [
                ['key' => 'letters', 'label' => 'Layanan Persuratan', 'desc' => 'Menu Persuratan, Ajukan Surat & Jenis Surat', 'icon' => 'bi-envelope-paper'],
                ['key' => 'archives', 'label' => 'Kearsipan & Upload Dokumen', 'desc' => 'Menu Kearsipan, Peminjaman Arsip & upload dokumen', 'icon' => 'bi-archive'],
                ['key' => 'reformasi_birokrasi', 'label' => 'Reformasi Birokrasi', 'desc' => 'Modul Reformasi Birokrasi', 'icon' => 'bi-arrow-repeat'],
                ['key' => 'manajemen_talenta', 'label' => 'Manajemen Talenta', 'desc' => 'Modul Manajemen Talenta', 'icon' => 'bi-stars'],
                ['key' => 'diklat', 'label' => 'Diklat & Pengembangan', 'desc' => 'Modul Diklat & Pengembangan Kompetensi', 'icon' => 'bi-mortarboard'],
            ],
            'theme' => [
                'primary' => Setting::get('theme_primary', '#163d4f'),
                'primary_dark' => Setting::get('theme_primary_dark', '#0e2a37'),
                'primary_light' => Setting::get('theme_primary_light', '#2b5f78'),
                'accent' => Setting::get('theme_accent', '#e8a13c'),
                'sidebar' => Setting::get('theme_sidebar', '#10222d'),
            ],
        ]);
    }

    public function updateAppearance(Request $request)
    {
        $validated = $request->validate([
            'menus' => ['nullable', 'array'],
            'menus.*' => ['in:0,1'],
            'otp_enabled' => ['nullable', 'boolean'],
            'theme_primary' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme_primary_dark' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme_primary_light' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme_accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme_sidebar' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        // simpan visibilitas menu
        foreach (['letters', 'archives', 'reformasi_birokrasi', 'manajemen_talenta', 'diklat'] as $menu) {
            Setting::set("menu_{$menu}", $request->input("menus.{$menu}") === '1');
        }

        // simpan tema
        foreach (['theme_primary', 'theme_primary_dark', 'theme_primary_light', 'theme_accent', 'theme_sidebar'] as $color) {
            if ($request->filled($color)) {
                Setting::set($color, $request->input($color));
            }
        }

        AuditLog::record(AuditLog::EVENT_UPDATE, 'pengaturan', 'Memperbarui pengaturan tampilan & menu');

        return redirect()->route('settings.appearance')->with('success', 'Pengaturan tampilan berhasil disimpan.');
    }

    /* ===================== PENGUMUMAN ===================== */

    public function announcements(): View
    {
        return view('settings.announcements', [
            'announcements' => Announcement::with('author')->latest()->paginate(10),
        ]);
    }

    public function storeAnnouncement(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'max:255'],
            'running_text' => ['nullable', 'max:1000'],
            'link_url' => ['nullable', 'url', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:1024', 'dimensions:max_width=3000,max_height=3000'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'title.required' => 'Judul pengumuman wajib diisi.',
            'image.image' => 'Berkas harus berupa gambar (jpg/png/webp/gif).',
            'image.max' => 'Ukuran gambar maksimal 1 MB.',
            'image_url.url' => 'URL gambar tidak valid (harus diawali http/https).',
        ]);

        /* Gambar kartu: gunakan berkas upload (prioritas) ATAU URL gambar eksternal */
        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('announcements', 'public');
        } elseif (! empty($validated['image_url'])) {
            $validated['image_path'] = $validated['image_url'];
        }

        $validated['is_active'] = $request->boolean('is_active');
        $validated['created_by'] = $request->user()->id;

        unset($validated['image'], $validated['image_url']);

        Announcement::create($validated);

        AuditLog::record(AuditLog::EVENT_CREATE, 'pengumuman', 'Menambah pengumuman: '.$validated['title']);

        return back()->with('success', 'Pengumuman berhasil ditambahkan.');
    }

    public function updateAnnouncement(Request $request, Announcement $announcement)
    {
        $validated = $request->validate([
            'title' => ['required', 'max:255'],
            'running_text' => ['nullable', 'max:1000'],
            'link_url' => ['nullable', 'url', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:1024', 'dimensions:max_width=3000,max_height=3000'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
            'remove_image' => ['nullable', 'boolean'],
        ]);

        /* Gambar kartu hanya diganti bila: ada berkas upload baru, dicentang
           "hapus gambar", atau diisi URL gambar eksternal. */
        $replacing = $request->hasFile('image')
            || $request->boolean('remove_image')
            || ! empty($validated['image_url']);

        if ($replacing && $announcement->image_path && $announcement->is_local_image) {
            Storage::disk('public')->delete($announcement->image_path);
        }

        if ($request->hasFile('image')) {
            /* ganti dengan berkas upload baru */
            $validated['image_path'] = $request->file('image')->store('announcements', 'public');
        } elseif ($request->boolean('remove_image')) {
            $validated['image_path'] = null;
        } elseif (! empty($validated['image_url'])) {
            /* ganti dengan URL gambar eksternal */
            $validated['image_path'] = $validated['image_url'];
        } else {
            unset($validated['image_path']);
        }

        $validated['is_active'] = $request->boolean('is_active');
        unset($validated['image'], $validated['remove_image'], $validated['image_url']);

        $announcement->update($validated);

        AuditLog::record(AuditLog::EVENT_UPDATE, 'pengumuman', 'Mengubah pengumuman: '.$announcement->title);

        return back()->with('success', 'Pengumuman berhasil diperbarui.');
    }

    public function destroyAnnouncement(Announcement $announcement)
    {
        if ($announcement->is_local_image) {
            Storage::disk('public')->delete($announcement->image_path);
        }

        AuditLog::record(AuditLog::EVENT_DELETE, 'pengumuman', 'Menghapus pengumuman: '.$announcement->title);

        $announcement->delete();

        return back()->with('success', 'Pengumuman berhasil dihapus.');
    }

    /* ===================== SMTP & NOTIFIKASI ===================== */

    public function smtp(): View
    {
        $settings = collect([
            'smtp_enabled', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username',
            'smtp_password', 'smtp_from_address', 'smtp_from_name',
            'notify_login', 'notify_password', 'notify_register', 'notify_sop', 'notify_letter',
            'notify_recipient', 'otp_enabled',
        ])
            ->mapWithKeys(fn ($key) => [$key => Setting::get($key)])
            ->all();

        return view('settings.smtp', $settings);
    }

    public function updateSmtp(Request $request)
    {
        $validated = $request->validate([
            'smtp_enabled' => ['nullable', 'boolean'],
            'smtp_host' => ['nullable', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['nullable', 'in:tls,ssl,none'],
            'smtp_username' => ['nullable', 'max:255'],
            'smtp_password' => ['nullable', 'max:255'],
            'smtp_from_address' => ['nullable', 'email', 'max:255'],
            'smtp_from_name' => ['nullable', 'max:255'],
            'notify_login' => ['nullable', 'boolean'],
            'notify_password' => ['nullable', 'boolean'],
            'notify_register' => ['nullable', 'boolean'],
            'notify_sop' => ['nullable', 'boolean'],
            'notify_letter' => ['nullable', 'boolean'],
            'notify_recipient' => ['nullable', 'max:255'],
            'otp_enabled' => ['nullable', 'boolean'],
            'test_email' => ['nullable', 'email'],
        ]);

        $testEmail = $validated['test_email'] ?? null;
        unset($validated['test_email']);

        // password kosong = pertahankan yang lama
        if (empty($validated['smtp_password'])) {
            unset($validated['smtp_password']);
        }

        foreach (['smtp_enabled', 'notify_login', 'notify_password', 'notify_register', 'notify_sop', 'notify_letter', 'otp_enabled'] as $toggle) {
            $validated[$toggle] = $request->boolean($toggle);
        }

        Setting::setMany($validated);

        AuditLog::record(AuditLog::EVENT_UPDATE, 'pengaturan', 'Memperbarui pengaturan SMTP & notifikasi');

        // kirim email percobaan bila diminta
        if ($testEmail) {
            $ok = Notifier::send(
                to: $testEmail,
                type: 'test',
                title: 'Email Percobaan (Test SMTP)',
                greeting: 'Halo Administrator Utama',
                lines: [
                    'Ini adalah email percobaan dari pengaturan SMTP Dashboard Biro OSDMRB.',
                    'Bila email ini sampai ke kotak masuk Anda, konfigurasi SMTP sudah benar.',
                ],
                fields: [
                    'Host' => Setting::get('smtp_host'),
                    'Port' => Setting::get('smtp_port'),
                    'Enkripsi' => Setting::get('smtp_encryption'),
                    'Dikirim' => now()->setTimezone(config('app.timezone'))->format('d F Y H:i'),
                ],
                actionUrl: url('/'),
                actionText: 'Buka Dashboard',
            );

            return back()->with($ok ? 'success' : 'error', $ok
                ? 'Pengaturan SMTP disimpan & email percobaan berhasil dikirim ke '.$testEmail.'.'
                : 'Pengaturan SMTP disimpan, namun email percobaan GAGAL dikirim. Periksa kembali konfigurasi.');
        }

        return back()->with('success', 'Pengaturan SMTP & notifikasi berhasil disimpan.');
    }
}

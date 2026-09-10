<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * Fitur update 6 September 2026:
 * - Tabel settings        : konfigurasi menu, tema, SMTP & notifikasi (super admin)
 * - Tabel announcements   : pengumuman (gambar + running text) di dashboard
 * - Tabel audit_logs      : log aktivitas + statistik pengunjung
 * - Tabel surveys         : survei masukan & saran aplikasi
 * - Kolom employees       : employee_type (asn / non_asn), kategori & kenaikan jabatan
 * - Kolom users           : OTP 2-layer login (kode + masa berlaku)
 * - Role super_admin      : Administrator Utama
 */
return new class extends Migration
{
    public function up(): void
    {
        /* ================= SETTINGS ================= */
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        /* ================= PENGUMUMAN ================= */
        if (! Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('running_text')->nullable();   // teks berjalan (marquee)
                $table->string('image_path')->nullable();   // banner gambar
                $table->string('link_url')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        /* ================= AUDIT LOG ================= */
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('user_name')->nullable();      // snapshot nama (aman bila user dihapus)
                $table->string('event', 50);                   // visit | login | logout | create | update | delete | ...
                $table->string('module', 100)->nullable();
                $table->string('description')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->string('url')->nullable();
                $table->timestamps();

                $table->index(['event', 'created_at']);
                $table->index(['user_id', 'created_at']);
            });
        }

        /* ================= SURVEI ================= */
        if (! Schema::hasTable('surveys')) {
            Schema::create('surveys', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('user_name')->nullable();
                $table->unsignedTinyInteger('rating');        // 1 - 5 bintang
                $table->text('message')->nullable();          // masukan & saran
                $table->timestamps();
            });
        }

        /* ================= EMPLOYEES ================= */
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'employee_type')) {
                $table->string('employee_type', 20)->default('asn')->index()->after('nip');
            }
            if (! Schema::hasColumn('employees', 'category')) {
                $table->string('category', 100)->nullable()->after('employee_type');
            }
            if (! Schema::hasColumn('employees', 'next_promotion_date')) {
                $table->date('next_promotion_date')->nullable()->after('tmt_golongan');
            }
        });

        /* ================= USERS (OTP) ================= */
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'otp_code')) {
                $table->string('otp_code', 10)->nullable();
            }
            if (! Schema::hasColumn('users', 'otp_expires_at')) {
                $table->timestamp('otp_expires_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'otp_sent_at')) {
                $table->timestamp('otp_sent_at')->nullable();
            }
        });

        /* ================= ROLE SUPER ADMIN ================= */
        Role::firstOrCreate(['name' => 'super_admin']);

        // Akun admin yang sudah ada otomatis menjadi Administrator Utama
        // (mendapat role super_admin di samping role lama).
        $superRole = Role::where('name', 'super_admin')->first();

        if ($superRole) {
            $adminRoleIds = DB::table('roles')->where('name', 'admin')->pluck('id');

            $adminUserIds = DB::table('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->whereIn('role_id', $adminRoleIds)
                ->pluck('model_id')
                ->unique();

            foreach ($adminUserIds as $userId) {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $superRole->id,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $userId,
                ]);
            }
        }

        /* ================= DEFAULT SETTINGS ================= */
        $defaults = [
            // visibilitas menu sidebar
            'menu_letters' => '0',              // layanan persuratan (sementara tidak digunakan)
            'menu_archives' => '0',             // kearsipan & upload dokumen (sementara tidak digunakan)
            'menu_reformasi_birokrasi' => '0',
            'menu_manajemen_talenta' => '0',
            'menu_diklat' => '0',

            // keamanan login (default nonaktif — aktifkan dari Pengaturan > SMTP & Notifikasi)
            'otp_enabled' => '0',

            // tema tampilan
            'theme_primary' => '#43538f',
            'theme_primary_dark' => '#2f3f7c',
            'theme_primary_light' => '#5366aa',
            'theme_accent' => '#f0a44b',
            'theme_sidebar' => '#1e2547',

            // SMTP & notifikasi email
            'smtp_enabled' => '0',
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_from_address' => 'no-reply@kementrans.go.id',
            'smtp_from_name' => 'Dashboard Biro OSDMRB',
            'notify_login' => '1',
            'notify_password' => '1',
            'notify_register' => '1',
            'notify_sop' => '1',
            'notify_letter' => '1',
            'notify_recipient' => '',          // email tujuan notifikasi admin (kosong = semua super admin)
        ];

        foreach ($defaults as $key => $value) {
            DB::table('settings')->insertOrIgnore(['key' => $key, 'value' => $value]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('surveys');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('settings');

        Schema::table('employees', function (Blueprint $table) {
            foreach (['employee_type', 'category', 'next_promotion_date'] as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach (['otp_code', 'otp_expires_at', 'otp_sent_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Role::where('name', 'super_admin')->delete();
    }
};

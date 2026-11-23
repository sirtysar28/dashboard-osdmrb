<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persetujuan akun pendaftar mandiri (anti-spam).
 *
 * Sebelumnya akun yang mendaftar lewat halaman /register langsung aktif
 * dan otomatis login. Sekarang akun baru berstatus "pending" dan harus
 * disetujui admin (Admin Bagian / Administrator Utama) lebih dulu:
 * - registration_status : pending | approved | rejected
 * - approved_at         : waktu persetujuan (null bila belum/tidak disetujui)
 * - approved_by         : admin yang menyetujui
 *
 * Default 'approved' agar akun yang sudah ada (termasuk buatan admin)
 * tidak terdampak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('registration_status', 20)->default('approved')->after('is_active');
            $table->timestamp('approved_at')->nullable()->after('registration_status');
            $table->foreignId('approved_by')->nullable()
                ->constrained('users')->nullOnDelete()
                ->after('approved_at');
            $table->index('registration_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['registration_status']);
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['registration_status', 'approved_at']);
        });
    }
};

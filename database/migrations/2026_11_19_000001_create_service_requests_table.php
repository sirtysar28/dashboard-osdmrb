<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PENGAJUAN LAYANAN KEPEGAWAIAN — Catatan Masukan 19 Nov 2026.
 *
 * Menu baru pada grup "Layanan Kepegawaian": pengajuan layanan kepegawaian
 * bagi pegawai ASN (Kenaikan Gaji Berkala, Kenaikan Pangkat, Pemberhentian
 * dan Pensiun, Tugas / Izin Belajar, Pengembangan Kompetensi).
 *
 * Form: Unit Kerja, Nama yang mengajukan, Jenis Layanan, upload Nota Dinas
 * (berkas) dan link Bukti Dukung. Daftar pengajuan dibatasi 10 baris per
 * halaman (pagination).
 *
 * Alur status sederhana:
 *   PENDING (Diajukan) -> DIPROSES (Diproses Biro) -> SELESAI
 *   Dapat ditolak (DITOLAK) selama belum selesai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('unit_id')->constrained();          // unit kerja pengaju
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('service_type', 40);                   // jenis layanan (lihat model ServiceRequest)

            $table->string('nota_path');                          // berkas Nota Dinas (disk public)
            $table->string('nota_name')->nullable();              // nama asli berkas saat diunggah
            $table->string('supporting_link', 500)->nullable();   // link Bukti Dukung

            $table->string('status', 20)->default('PENDING');     // PENDING | DIPROSES | SELESAI | DITOLAK
            $table->text('note')->nullable();                     // catatan pemrosesan/penolakan
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};

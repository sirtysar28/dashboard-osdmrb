<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembaruan 7 November 2026 — Point 4 (Peta Jabatan):
 *
 * Halaman Peta Jabatan kini menampilkan KERTAS KERJA lengkap sesuai berkas
 * "KERTAS KERJA EXCEL.xlsx" dengan kolom: Jabatan dan Jenjang, Dukungan Lintas
 * Program, Intervensi Program 5 T, Kualifikasi Pendidikan, Kompetensi,
 * Kebutuhan, Pemangku Jabatan Eksisting, Kesenjangan Jumlah, Alternatif
 * Penataan/Pemenuhan, dan Rekomendasi Utama.
 *
 * Atribut kertas kerja (dukungan, intervensi, kualifikasi, kompetensi,
 * kebutuhan, alternatif, rekomendasi) diisi melalui import Excel, sedangkan
 * "Pemangku Jabatan Eksisting" & "Kesenjangan Jumlah" tetap dihitung LANGSUNG
 * dari data pegawai aktif (prinsip 23 Okt 2026: selalu sinkron); snapshot
 * angka pada berkas hanya dipakai sebagai fallback bila jabatan belum
 * terpetakan di data pegawai.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('position_maps')) {
            return;
        }

        Schema::create('position_maps', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);                        // Jabatan dan Jenjang (nama lengkap)
            $table->text('cross_program')->nullable();          // Dukungan Lintas Program
            $table->text('program_intervention')->nullable();   // Intervensi Program 5 T
            $table->text('education_qualification')->nullable(); // Kualifikasi Pendidikan
            $table->text('competency')->nullable();             // Kompetensi
            $table->unsignedSmallInteger('required_count')->default(0);  // Kebutuhan
            $table->unsignedSmallInteger('existing_count')->default(0);  // Pemangku jabatan eksisting (snapshot berkas)
            $table->integer('gap_count')->default(0);           // Kesenjangan Jumlah (snapshot berkas)
            $table->text('fulfillment_alternative')->nullable(); // Alternatif Penataan/Pemenuhan
            $table->text('main_recommendation')->nullable();    // Rekomendasi Utama
            $table->unsignedInteger('sort_order')->default(0);  // urutan baris kertas kerja
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('position_maps');
    }
};

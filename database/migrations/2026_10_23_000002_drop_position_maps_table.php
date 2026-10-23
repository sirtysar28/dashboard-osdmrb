<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan Masukan 23 Oktober 2026:
 *
 * Modul "Dashboard Peta Jabatan" kini menghitung datanya LANGSUNG dari
 * data pegawai eksisting (tabel employees) — tidak lagi melalui import
 * Excel. Tabel position_maps beserta alur importnya dihapus karena
 * tidak digunakan lagi (jumlah pegawai per jenis jabatan fungsional
 * selalu sinkon dengan basis data kepegawaian).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('position_maps');
    }

    public function down(): void
    {
        if (Schema::hasTable('position_maps')) {
            return;
        }

        Schema::create('position_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->nullable()
                ->constrained('units')->nullOnDelete(); // null = seluruh kementerian
            $table->string('name', 180);                 // nama jabatan fungsional
            $table->unsignedSmallInteger('existing_count')->default(0); // pegawai existing
            $table->unsignedSmallInteger('required_count')->default(0); // kebutuhan (formasi)
            $table->string('note', 255)->nullable();     // keterangan/rekomendasi (opsional)
            $table->timestamps();

            $table->index(['name']);
            $table->index(['unit_id']);
        });
    }
};

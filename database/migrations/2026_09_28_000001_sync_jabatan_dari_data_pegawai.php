<?php

use Illuminate\Database\Migrations\Migration;
use App\Services\JabatanSyncService;
use Illuminate\Support\Facades\Schema;

/**
 * Pembaruan fitur 28 September 2026 (catatan masukan 28 Sept):
 *
 * MENU ANALIS JABATAN FUNGSIONAL & STRUKTURAL —
 * sebelumnya analisis hanya mengenali 10 jenis jabatan fungsional yang ada di
 * master dan status pemangku jabatan struktural selalu kosong. Migrasi ini
 * memindahkan SEMUA nama jabatan (struktural & fungsional) yang sudah masuk di
 * data pegawai ke master jabatan (positions) serta membuat riwayat jabatan
 * aktif (employee_positions) bagi pegawai yang belum punya, sehingga:
 *  1. Analis Jabatan Fungsional mengidentifikasi semua jenis jabatan fungsional
 *     dari data pegawai (bukan hanya 10 di master).
 *  2. Analis Jabatan Struktural menampilkan pimpinan (Terisi) di tiap jabatan.
 *
 * Idempoten — aman dijalankan berulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('positions')) {
            return;
        }

        JabatanSyncService::syncFromEmployees();
    }

    public function down(): void
    {
        // tidak ada struktur tabel yang berubah — data hasil sinkronisasi dibiarkan
    }
};

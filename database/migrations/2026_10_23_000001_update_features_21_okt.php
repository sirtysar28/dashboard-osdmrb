<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan Masukan 21 Oktober 2026:
 *
 * 1. Perbaikan (penyelarasan) urutan & penamaan pilihan unit kerja eselon I,
 *    II dan III sesuai daftar struktur kementerian — termasuk penegasan
 *    tingkat balai: Balai Besar Pelatihan Yogyakarta setara Eselon II,
 *    tiga balai lainnya Eselon III.
 * 2. Tabel `position_maps` untuk modul baru "Dashboard Peta Jabatan" —
 *    pemetaan 46 jenis jabatan fungsional: jumlah pegawai existing,
 *    kebutuhan pegawai (formasi), per unit kerja. Data diisi melalui
 *    import Excel agar mudah diperbarui (visualisasi jumlah pegawai yang
 *    menduduki setiap jabatan, kebutuhan, serta rekomendasi pemenuhan).
 */
return new class extends Migration
{
    /**
     * Urutan resmi berdasarkan kode unit (mengikuti daftar struktur
     * kementerian pada Catatan Masukan 21 Okt 2026).
     */
    private const UNIT_ORDER = [
        // Eselon I
        'SETJEN' => 10,
        'DJ-EKBANG' => 20,
        'DJ-KAWASAN' => 30,
        'ITJEN' => 40,

        // Eselon II — Sekretariat Jenderal
        'BIRO-PKHM' => 11,
        'OSDMRB' => 12,
        'BIRO-ULP' => 13,
        'BIRO-KBMN' => 14,
        'BIRO-HUKUM' => 15,
        'PUS-STK' => 16,
        'PUS-PSDM' => 17,
        'PUS-DATIN' => 18,
        'STAF-AH-PKLH' => 19,
        'STAF-AH-POLHUK' => 20,

        // Eselon II — Ditjen Pengembangan Ekonomi & Pemberdayaan Masyarakat
        'SET-DJEKBANG' => 21,
        'DIT-PTPE' => 22,
        'DIT-PKET' => 23,
        'DIT-PPUT' => 24,
        'DIT-PPPU' => 25,
        'DIT-PMT' => 26,

        // Eselon II — Ditjen Pembangunan & Pengembangan Kawasan
        'SET-DJKWSN' => 31,
        'DIT-PPK' => 32,
        'DIT-PBKT' => 33,
        'DIT-FPPK' => 34,
        'DIT-PSPTS' => 35,
        'DIT-PKT' => 36,

        // Eselon II — Inspektorat Jenderal
        'SET-ITJEN' => 41,
        'ITJEN-I' => 42,
        'ITJEN-II' => 43,

        // Eselon III — bagian internal Biro OSDMRB
        'BAG-ORG' => 121,
        'BAG-SDM' => 122,
        'BAG-RB' => 123,

        // Tingkat balai — Balai Besar Yogyakarta (Eselon II) mendahului
        // balai lain (Eselon III): Pekanbaru, Banjarmasin, Denpasar.
        'BALAI-01' => 51,
        'BALAI-02' => 52,
        'BALAI-03' => 53,
        'BALAI-04' => 54,

        'KEMEN' => 1,
    ];

    public function up(): void
    {
        /* ---------- 1. Urutan & penamaan unit kerja ---------- */
        if (Schema::hasTable('units')) {
            if (! Schema::hasColumn('units', 'sort_order')) {
                Schema::table('units', function (Blueprint $table) {
                    $table->unsignedInteger('sort_order')->default(500)->after('level');
                });
            }

            foreach (self::UNIT_ORDER as $code => $order) {
                DB::table('units')->where('code', $code)->update(['sort_order' => $order]);
            }

            // penyelarasan nama unit sesuai daftar resmi 21 Okt 2026
            // (Pusat PSDM kini bermarga "Transmigrasi")
            DB::table('units')->where('code', 'PUS-PSDM')->update([
                'name' => 'Pusat Pengembangan Sumber Daya Manusia Transmigrasi',
            ]);
        }

        /* ---------- 2. Tabel peta jabatan ---------- */
        if (! Schema::hasTable('position_maps')) {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('position_maps');

        // urutan & nama unit dikembalikan ke kondisi sebelum 21 Okt 2026
        if (Schema::hasTable('units')) {
            DB::table('units')->where('code', 'PUS-PSDM')->update([
                'name' => 'Pusat Pengembangan Sumber Daya Manusia',
            ]);
        }
    }
};

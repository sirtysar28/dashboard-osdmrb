<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan Masukan 30 Sept 2026 — butir 1:
 * "Perbaikan urutan pada pilihan unit kerja eselon I, II dan III
 *  disesuaikan dengan daftar struktur kementerian" (lampiran
 *  "urutan jabatan.xlsx").
 *
 * Kolom sort_order ditambahkan ke tabel units lalu diisi urutan resmi:
 *
 * Eselon I : 1) Setjen 2) Ditjen Pengembangan Ekonomi & Pemberdayaan
 *            Masyarakat Transmigrasi 3) Ditjen Pembangunan & Pengembangan
 *            Kawasan Transmigrasi 4) Itjen.
 * Eselon II: biro/pusat Setjen (PKHM → OSDMRB → ULP → KBMN → Hukum →
 *            Pusat STK → Pusat PSDM → Pusat Datin), sekretariat ditjen,
 *            direktur & direktorat tiap ditjen, sekretariat itjen &
 *            inspektorat, lalu staf ahli.
 * Balai    : Balai Besar Yogyakarta, Pekanbaru, Banjarmasin, Denpasar.
 *
 * Unit di luar daftar (mis. hasil import) memperoleh urutan 500 sehingga
 * tetap tampil setelah unit resmi (urut nama).
 */
return new class extends Migration
{
    /** Urutan resmi berdasarkan kode unit (lihat MasterDataSeeder). */
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

        // Tingkat balai / UPT
        'BALAI-01' => 51,
        'BALAI-02' => 52,
        'BALAI-03' => 53,
        'BALAI-04' => 54,

        'KEMEN' => 1,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('units')) {
            return;
        }

        if (! Schema::hasColumn('units', 'sort_order')) {
            Schema::table('units', function (Blueprint $table) {
                $table->unsignedInteger('sort_order')->default(500)->after('level');
            });
        }

        foreach (self::UNIT_ORDER as $code => $order) {
            DB::table('units')->where('code', $code)->update(['sort_order' => $order]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('units') && Schema::hasColumn('units', 'sort_order')) {
            Schema::table('units', function (Blueprint $table) {
                $table->dropColumn('sort_order');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fitur update 8 September 2026 — bagian 2:
 *
 * Pembaruan daftar nama unit kerja sesuai file resmi "Daftar Nama Unit Kerja"
 * Kementerian Transmigrasi (SOTK):
 *
 * - Eselon I : Setjen, Itjen, Ditjen Pengembangan Ekonomi & Pemberdayaan
 *              Masyarakat Transmigrasi, Ditjen Pembangunan & Pengembangan
 *              Kawasan Transmigrasi.
 * - Eselon II: seluruh biro, pusat, sekretariat ditjen, direktorat, dan
 *              staf ahli di bawah masing-masing unit eselon I.
 * - Balai    : 4 balai pelatihan & pemberdayaan masyarakat transmigrasi
 *              (Yogyakarta, Pekanbaru, Banjarmasin, Denpasar).
 *
 * Data lama "DJ-01 / Direktorat Jenderal Pembinaan" (data contoh lama)
 * digabungkan ke unit baru DJ-EKBANG; balai lama di-rename menjadi balai
 * resmi sehingga relasi pegawai tetap tersambung.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('units')) {
            return;
        }

        // jalankan ulang seeder master data (idempotent / updateOrCreate)
        Artisan::call('db:seed', [
            '--class' => 'MasterDataSeeder',
            '--force' => true,
        ]);

        $this->mergeLegacyDitjen();
    }

    /**
     * Gabungkan unit lama DJ-01 (Direktorat Jenderal Pembinaan) ke
     * DJ-EKBANG bila masih ada — relasi pegawai dipindah lebih dulu
     * supaya tidak ada data yatim.
     */
    private function mergeLegacyDitjen(): void
    {
        $legacy = DB::table('units')->where('code', 'DJ-01')->first();
        $target = DB::table('units')->where('code', 'DJ-EKBANG')->first();

        if (! $legacy || ! $target || $legacy->id === $target->id) {
            return;
        }

        if (Schema::hasColumn('employees', 'unit_id')) {
            DB::table('employees')->where('unit_id', $legacy->id)->update(['unit_id' => $target->id]);
        }

        // anak-anak lama (balai dsb.) sudah dipindahkan seeder ke SETJEN;
        // pastikan tidak ada lagi yang menempel pada unit lama.
        DB::table('units')->where('parent_id', $legacy->id)->update(['parent_id' => $target->id]);

        DB::table('units')->where('id', $legacy->id)->delete();
    }

    public function down(): void
    {
        // pembaruan nama unit tidak dikembalikan otomatis (data master)
    }
};

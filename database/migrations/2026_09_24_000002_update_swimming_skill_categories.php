<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Update 24 September 2026 — kategori kemampuan renang pegawai diubah
 * dari 'bisa' (Bisa Berenang) / 'tidak' (Tidak Bisa Berenang)
 * menjadi 'lulus' (Lulus Ujian Renang) / 'belum' (Belum Lulus Ujian).
 *
 * Data lama dipetakan ulang; nilai NULL (belum diisi) tetap NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'swimming_skill')) {
            return;
        }

        DB::table('employees')->where('swimming_skill', 'bisa')->update(['swimming_skill' => 'lulus']);
        DB::table('employees')->where('swimming_skill', 'tidak')->update(['swimming_skill' => 'belum']);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('employees', 'swimming_skill')) {
            return;
        }

        DB::table('employees')->where('swimming_skill', 'lulus')->update(['swimming_skill' => 'bisa']);
        DB::table('employees')->where('swimming_skill', 'belum')->update(['swimming_skill' => 'tidak']);
    }
};

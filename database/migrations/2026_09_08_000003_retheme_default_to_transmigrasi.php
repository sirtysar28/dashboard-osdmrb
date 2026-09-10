<?php

namespace Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Warna default aplikasi diganti mengikuti situs Kementerian Transmigrasi
 * (transmigrasi.go.id) — primary #163d4f.
 *
 * Nilai tema di tabel settings hanya dihapus BILA masih bernilai default lama
 * (belum pernah dikustom), sehingga jatuh kembali ke default baru di kode.
 * Warna yang benar-benar dikustom admin TIDAK disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        $oldDefaults = [
            'theme_primary' => '#43538f',
            'theme_primary_dark' => '#2f3f7c',
            'theme_primary_light' => '#5366aa',
            'theme_accent' => '#f0a44b',
            'theme_sidebar' => '#1e2547',
        ];

        foreach ($oldDefaults as $key => $value) {
            DB::table('settings')
                ->where('key', $key)
                ->where('value', $value)
                ->delete();
        }
    }

    public function down(): void
    {
        // tidak ada yang perlu dikembalikan (nilai default hidup di kode)
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Update 23 September 2026 — penataan ulang master Level Jabatan & Jabatan
 * sesuai nomenklatur eselon:
 *
 * - Level struktural kini memakai Eselon I, II, III, IV
 *   (menggantikan JPT Madya / JPT Pratama / Administrator / Pengawas).
 * - Level fungsional: Ahli Pertama, Ahli Muda, Ahli Madya, Penyelia, Terampil.
 * - Pelaksana = non-eselon & non-fungsional.
 * - Master jabatan struktural dilengkapi per eselon:
 *   I  : Sekretaris Jenderal, Direktur Jenderal, Inspektur Jenderal
 *   II : Direktur, Sekretaris Ditjen, Kepala Pusat, Kepala Biro, Inspektur,
 *        Sekretaris Itjen, Kepala Balai Besar
 *   III: Kepala Bagian, Kepala Balai
 *   IV : Kepala Subbagian
 *
 * Data lama yang menunjuk level/jabatan lama dipetakan ulang, lalu baris
 * master lama yang tidak terpakai dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('job_levels') || ! Schema::hasTable('positions')) {
            return;
        }

        // 1. jalankan ulang seeder master (idempotent / updateOrCreate)
        Artisan::call('db:seed', [
            '--class' => 'MasterDataSeeder',
            '--force' => true,
        ]);

        $this->remapLegacyPositions();
        $this->remapLegacyLevels();
        $this->cleanupLegacyMaster();
    }

    /**
     * Pemegang jabatan lama dipindah ke jabatan padanannya yang baru,
     * lalu jabatan lama dihapus.
     */
    private function remapLegacyPositions(): void
    {
        // PPPK Mahir tidak lagi menjadi jenjang tersendiri -> digabung ke Terampil
        $mahir = DB::table('positions')->where('code', 'PEL-PPPK-MAHIR')->first();
        $terampil = DB::table('positions')->where('code', 'PEL-PPPK-TERAMPIL')->first();

        if ($mahir && $terampil && Schema::hasTable('employee_positions')) {
            DB::table('employee_positions')
                ->where('position_id', $mahir->id)
                ->update(['position_id' => $terampil->id]);
        }

        if ($mahir) {
            DB::table('positions')->where('id', $mahir->id)->delete();
        }

        // teks jenjang fungsional pada pegawai dinormalisasi ke
        // Pertama / Muda / Madya / Penyelia / Terampil — selain itu NULL (pelaksana)
        if (Schema::hasColumn('employees', 'functional_level')) {
            foreach ([
                ['Mahir', 'Terampil'],
                ['PPPK Terampil', 'Terampil'],
                ['Fungsional Ahli Pertama', 'Pertama'],
                ['Fungsional Pertama', 'Pertama'],
                ['Ahli Pertama', 'Pertama'],
                ['Fungsional Ahli Muda', 'Muda'],
                ['Fungsional Muda', 'Muda'],
                ['Ahli Muda', 'Muda'],
                ['Fungsional Ahli Madya', 'Madya'],
                ['Fungsional Madya', 'Madya'],
                ['Ahli Madya', 'Madya'],
                ['Fungsional Penyelia', 'Penyelia'],
            ] as [$old, $new]) {
                DB::table('employees')->where('functional_level', $old)->update(['functional_level' => $new]);
            }

            // nilai non-jenjang (mis. "PPPK Umum", "-", "Fungsional Umum", "PPPK Paruh Waktu")
            // bukan jenjang fungsional → dikosongkan (pegawai tsb. pelaksana)
            DB::table('employees')
                ->whereNotIn('functional_level', ['Pertama', 'Muda', 'Madya', 'Penyelia', 'Terampil'])
                ->whereNotNull('functional_level')
                ->update(['functional_level' => null]);
        }
    }

    /**
     * Jabatan lama yang masih memakai level lama dipetakan ke level eselon baru
     * (mis. jabatan custom buatan user dengan level Administrator/Pengawas).
     */
    private function remapLegacyLevels(): void
    {
        $map = [
            'JPT_MADYA' => 'ESELON_I',
            'JPT_PRATAMA' => 'ESELON_II',
            'ADMINISTRATOR' => 'ESELON_III',
            'PENGAWAS' => 'ESELON_IV',
            'AHLI_UTAMA' => 'AHLI_MADYA',
            'PELAKSANA_PENYELIA' => 'PENYELIA',
            'PELAKSANA_MAHIR' => 'TERAMPIL',
            'PELAKSANA_TERAMPIL' => 'TERAMPIL',
        ];

        foreach ($map as $old => $new) {
            $newLevel = DB::table('job_levels')->where('code', $new)->first();
            if ($newLevel) {
                DB::table('positions')
                    ->where('job_level_id', DB::table('job_levels')->where('code', $old)->value('id'))
                    ->update(['job_level_id' => $newLevel->id]);
            }
        }
    }

    /**
     * Hapus level lama yang sudah tidak dipakai
     * (relasi positions.job_level_id memakai nullOnDelete, aman).
     */
    private function cleanupLegacyMaster(): void
    {
        $legacy = [
            'JPT_MADYA', 'JPT_PRATAMA', 'ADMINISTRATOR', 'PENGAWAS',
            'AHLI_UTAMA', 'PELAKSANA_PENYELIA', 'PELAKSANA_MAHIR', 'PELAKSANA_TERAMPIL',
        ];

        DB::table('job_levels')->whereIn('code', $legacy)->delete();
    }

    public function down(): void
    {
        // perubahan data master tidak dikembalikan otomatis
    }
};

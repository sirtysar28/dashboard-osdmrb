<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan Masukan 7 Oktober 2026:
 *
 * "Jabatan struktural ada yg penamaannya salah dan sebaiknya dihapus
 *  yaitu Sekretaris Direktur Jenderal."
 *
 * Nama resmi jabatan tersebut adalah "Sekretaris Direktorat Jenderal"
 * (Sekretaris Ditjen) — baris master bernama salah dihapus, termasuk
 * salinan hasil sinkronisasi data pegawai bila ada. Nama jabatan pada
 * data pegawai yang ikut salah ketik ikut diluruskan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('positions')) {
            return;
        }

        // 1. Luruskan nama jabatan salah ketik pada data pegawai
        //    ("Sekretaris Direktur Jenderal" → "Sekretaris Direktorat Jenderal").
        if (Schema::hasTable('employees')) {
            DB::table('employees')
                ->where('position_name', 'like', '%Sekretaris Direktur Jenderal%')
                ->update([
                    'position_name' => DB::raw(
                        "REPLACE(position_name, 'Sekretaris Direktur Jenderal', 'Sekretaris Direktorat Jenderal')"
                    ),
                ]);
        }

        // 2. Hapus master jabatan bernama salah (baris seeder lama STR-SETDITJEN
        //    maupun salinan sinkronisasi dari data pegawai) beserta riwayat
        //    jabatan yang menunjuk kepadanya.
        $wrongIds = DB::table('positions')
            ->where('code', 'STR-SETDITJEN')
            ->orWhere('name', 'Sekretaris Direktur Jenderal')
            ->pluck('id');

        if ($wrongIds->isNotEmpty()) {
            if (Schema::hasTable('employee_positions')) {
                DB::table('employee_positions')->whereIn('position_id', $wrongIds)->delete();
            }

            DB::table('positions')->whereIn('id', $wrongIds)->delete();
        }
    }

    public function down(): void
    {
        // tidak dipulihkan — jabatan memang salah nama & sudah dihapus
    }
};

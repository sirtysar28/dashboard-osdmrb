<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pembaruan fitur 25 September 2026 (tindak lanjut catatan rapat):
 *
 * SELURUH pegawai Non ASN dimasukkan ke SEKRETARIAT JENDERAL.
 * Unit kerja Non ASN (pramubakti, security, cleaning service) tidak
 * diketahui, sehingga sebelumnya kartu Non ASN di dashboard tidak
 * berubah ketika filter eselon/balai diganti-ganti (jumlah Non ASN
 * muncul sama / tidak konsisten di semua filter).
 *
 * Dengan semua Non ASN berada di satu unit (Setjen):
 * - filter Sekretariat Jenderal  → menampilkan seluruh Non ASN;
 * - filter eselon/balai lainnya  → jumlah Non ASN berbeda (0 bila
 *   tidak ada Non ASN di unit tersebut).
 *
 * Kolom employees.gender dibuat NULLABLE — berkas daftar Non ASN
 * (pramubakti/security/cleaning service) tidak memuat jenis kelamin,
 * sehingga import gagal pada database dengan mode ketat.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('units')) {
            return;
        }

        // pastikan unit Sekretariat Jenderal (SETJEN) tersedia
        Artisan::call('db:seed', [
            '--class' => 'MasterDataSeeder',
            '--force' => true,
        ]);

        $setjen = DB::table('units')->where('code', 'SETJEN')->value('id')
            ?? DB::table('units')->where('name', 'like', 'Sekretariat Jenderal%')->value('id');

        if (Schema::hasTable('employees')) {
            if (Schema::hasColumn('employees', 'employee_type')) {
                DB::table('employees')
                    ->where('employee_type', 'non_asn')
                    ->update(['unit_id' => $setjen]);
            }

            // berkas Non ASN tanpa kolom jenis kelamin → kolom gender nullable
            if (Schema::hasColumn('employees', 'gender')) {
                Schema::table('employees', function (Blueprint $table) {
                    $table->enum('gender', ['L', 'P'])->nullable()->change();
                });
            }
        }
    }

    public function down(): void
    {
        // pembenahan data tidak dikembalikan otomatis
    }
};

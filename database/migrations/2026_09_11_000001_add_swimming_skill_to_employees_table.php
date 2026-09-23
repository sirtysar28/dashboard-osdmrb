<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kemampuan berenang pegawai (Informasi Personal).
 * Nilai: 'bisa' (Bisa Berenang) | 'tidak' (Tidak Bisa Berenang) | NULL (belum diisi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('swimming_skill', 20)->nullable()->after('religion');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('swimming_skill');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Melengkapi tabel leave_requests sesuai formulir resmi
 * "FORM CUTI KOSONG - PNS dan PPPK" Kementerian Transmigrasi RI:
 * - Bagian VI: alamat & telepon selama menjalankan cuti.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->string('address_during_leave')->nullable()->after('reason'); // Bagian VI (14)
            $table->string('phone_during_leave', 30)->nullable()->after('address_during_leave'); // Bagian VI (15)
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['address_during_leave', 'phone_during_leave']);
        });
    }
};

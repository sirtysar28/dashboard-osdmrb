<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Melengkapi Bagian V (CATATAN CUTI) formulir cuti:
 * - nominal SISA cuti tahunan per tahun N-2, N-1 dan N (otomatis dari sistem,
 *   dapat disunting oleh Admin / Biro SDM);
 * - tahun acuan N (agar label N-2/N-1/N konsisten saat dicetak);
 * - keterangan catatan cuti yang diinput oleh Admin / Biro SDM (HRD).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->smallInteger('balance_year')->nullable()->after('phone_during_leave'); // tahun N
            $table->smallInteger('annual_n2')->unsigned()->nullable()->after('balance_year'); // sisa cuti tahunan tahun N-2
            $table->smallInteger('annual_n1')->unsigned()->nullable()->after('annual_n2');   // sisa cuti tahunan tahun N-1
            $table->smallInteger('annual_n')->unsigned()->nullable()->after('annual_n1');    // sisa cuti tahunan tahun N
            $table->string('leave_note', 500)->nullable()->after('annual_n');                // keterangan (Admin/HRD)
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['balance_year', 'annual_n2', 'annual_n1', 'annual_n', 'leave_note']);
        });
    }
};

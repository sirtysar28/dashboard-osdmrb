<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengajuan cuti pegawai.
 *
 * Fitur disiapkan menunggu kepastian tanda tangan digital (ttd digital);
 * dapat diaktifkan / dinonaktifkan dari menu Pengaturan -> Tampilan & Menu
 * (kunci menu: menu_cuti, default tersembunyi).
 *
 * Workflow mengikuti layanan persuratan:
 *   Pegawai mengajukan (PENDING)
 *     -> Admin/Biro SDM memverifikasi (VERIFIED)
 *       -> Persetujuan akhir (APPROVED)
 *   Penolakan dapat terjadi pada tahap verifikasi/persetujuan (REJECTED).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->string('type', 30);              // tahunan | besar | sakit | melahirkan | alasan_penting | lainnya
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('total_days'); // jumlah hari kerja cuti (inklusive)
            $table->text('reason');                  // alasan / keterangan pengajuan

            $table->string('status', 20)->default('PENDING'); // PENDING | VERIFIED | APPROVED | REJECTED

            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('note')->nullable();        // catatan verifikasi/persetujuan/penolakan

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};

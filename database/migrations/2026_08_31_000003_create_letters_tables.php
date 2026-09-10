<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ================= MASTER JENIS SURAT ================= */
        Schema::create('letter_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('code_format', 100)->nullable();      // format nomor surat, mis. {no}/OSDMRB/{bulan_romawi}/{tahun}
            $table->text('template_body')->nullable();           // isi template surat (mendukung placeholder)
            $table->boolean('needs_verification')->default(true); // alur: diajukan -> diverifikasi -> disetujui
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        /* ================= PENGAJUAN SURAT ================= */
        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->string('number', 100)->nullable()->unique(); // nomor surat final (diisi saat disetujui)
            $table->foreignId('letter_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->text('purpose');                             // keperluan / dasar
            $table->date('letter_date')->nullable();             // tanggal surat
            $table->json('meta')->nullable();                    // data tambahan (tempat, acara, tanggal mulai-selesai, dst.)
            $table->enum('status', ['DRAFT', 'PENDING', 'VERIFIED', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('note')->nullable();                    // catatan penolakan / verifikasi
            $table->timestamps();
        });

        /* ================= LOG WORKFLOW SURAT ================= */
        Schema::create('letter_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);                        // SUBMIT / VERIFY / APPROVE / REJECT / PRINT
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_logs');
        Schema::dropIfExists('letters');
        Schema::dropIfExists('letter_types');
    }
};

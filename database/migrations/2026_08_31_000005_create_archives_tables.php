<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ================= MASTER KLASIFIKASI ARSIP ================= */
        Schema::create('archive_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();          // kode klasifikasi, mis. 830 (Kepegawaian)
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        /* ================= DOKUMEN ARSIP ================= */
        Schema::create('archives', function (Blueprint $table) {
            $table->id();
            $table->string('archive_number', 100)->unique(); // nomor arsip / kode item
            $table->string('title');
            $table->text('description')->nullable();

            $table->foreignId('archive_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete(); // pegawai terkait (arsip pribadi)
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('letter_id')->nullable()->constrained()->nullOnDelete();   // tautan surat tersetujui

            $table->enum('type', ['SURAT_MASUK', 'SURAT_KELUAR', 'SK', 'KONTRAK', 'LAPORAN', 'DOKUMEN_PEGAWAI', 'LAINNYA'])
                ->default('LAINNYA');
            $table->date('document_date')->nullable();
            $table->integer('year')->nullable();

            // kearsipan (mengacu praktik ANRI)
            $table->enum('retention', ['AKTIF', 'INAKTIF', 'MUSNAH', 'DINILAI_KEMBALI', 'PERMANEN'])->default('AKTIF');
            $table->integer('retention_years')->nullable();
            $table->date('retention_until')->nullable();      // batas simpan
            $table->string('physical_location', 150)->nullable(); // rak / boks / folder

            $table->enum('status', ['TERSEDIA', 'DIPINJAM', 'DIPINDAHKAN', 'DIMUSNAHKAN'])->default('TERSEDIA');
            $table->enum('visibility', ['PUBLIK', 'INTERNAL'])->default('PUBLIK'); // INTERNAL = hanya admin

            $table->string('file_path')->nullable();          // salinan digital
            $table->string('file_name')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        /* ================= PEMINJAMAN ARSIP ================= */
        Schema::create('archive_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archive_id')->constrained('archives')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->text('purpose');
            $table->date('loan_date');
            $table->date('due_date');
            $table->date('returned_at')->nullable();
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'RETURNED'])->default('PENDING');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_loans');
        Schema::dropIfExists('archives');
        Schema::dropIfExists('archive_categories');
    }
};

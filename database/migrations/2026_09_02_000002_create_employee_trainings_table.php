<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ================= RIWAYAT DIKLAT PEGAWAI ================= */
        Schema::create('employee_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('name');                                        // nama diklat
            $table->enum('type', ['KEPEMIMPINAN', 'TEKNIS', 'FUNGSIONAL', 'SOSIAL_KULTURAL'])->default('TEKNIS');
            $table->string('organizer')->nullable();                       // penyelenggara
            $table->year('year')->nullable();                              // tahun pelaksanaan
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('hours')->nullable();                          // jam pelatihan (JP)
            $table->string('certificate_number', 100)->nullable();         // nomor sertifikat
            $table->string('file_path')->nullable();                       // salinan sertifikat
            $table->string('file_name')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_trainings');
    }
};

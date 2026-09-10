<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ================= DOKUMEN SOP KEMENTERIAN ================= */
        Schema::create('sops', function (Blueprint $table) {
            $table->id();
            $table->string('title');                          // nama SOP
            $table->string('category', 100);                  // kategori: Pelayanan Publik, Kepegawaian, dll.
            $table->string('number', 100)->nullable();        // nomor SOP (mis. SOP-021/OSDMRB/2026)
            $table->string('unit', 150)->nullable();          // unit penyusun
            $table->year('year')->nullable();                 // tahun penetapan
            $table->enum('status', ['BERLAKU', 'DITINJAU', 'DICABUT'])->default('BERLAKU');
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();          // dokumen SOP (pdf/doc)
            $table->string('file_name')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['category', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sops');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ================= DATA PEGAWAI ================= */
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('nip', 30)->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->enum('gender', ['L', 'P']);
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('religion', 30)->nullable();
            $table->text('address')->nullable();

            $table->foreignId('employment_status_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rank_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('education_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();

            // aspek jabatan
            $table->string('eselon', 10)->nullable();          // II / III / IV untuk jabatan struktural
            $table->string('functional_level', 100)->nullable(); // Fungsional Madya, Muda, dst.
            $table->string('position_name')->nullable();       // nama jabatan saat ini (redudansi agar ringan)
            $table->date('tmt_jabatan')->nullable();
            $table->date('tmt_golongan')->nullable();
            $table->date('tmt_cpns')->nullable();
            $table->date('tmt_pns')->nullable();
            $table->date('retirement_date')->nullable();

            // pendidikan (riwayat teks)
            $table->string('education_1')->nullable();
            $table->string('education_2')->nullable();
            $table->string('education_3')->nullable();

            // administrasi lain
            $table->string('npwp', 40)->nullable();
            $table->string('karpeg', 40)->nullable();
            $table->string('photo')->nullable();
            $table->date('last_sync')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        /* ================= RIWAYAT JABATAN PEGAWAI ================= */
        Schema::create('employee_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sk_number', 100)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_positions');
        Schema::dropIfExists('employees');
    }
};

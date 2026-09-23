<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pembaruan fitur 17 September 2026:
 * 1. Penamaan konsisten: status kepegawaian "PNS" menjadi "ASN".
 * 2. Kolom kemampuan Bahasa Inggris pada data personal pegawai.
 * 3. Dukungan data Seminar / Pelatihan dalam & luar negeri
 *    (tipe baru SEMINAR & PELATIHAN + kolom lingkup penyelenggaraan).
 * 4. Tabel riwayat kenaikan pangkat pegawai (mis. III/a -> III/b).
 */
return new class extends Migration
{
    public function up(): void
    {
        /* ================= 1. PNS -> ASN ================= */
        DB::table('employment_statuses')
            ->where('code', 'PNS')
            ->update(['code' => 'ASN', 'name' => 'ASN']);

        /* ================= 2. KEMAMPUAN BAHASA INGGRIS ================= */
        Schema::table('employees', function (Blueprint $table) {
            $table->string('english_skill', 20)->nullable()->after('swimming_skill');
        });

        /* ================= 3. SEMINAR / PELATIHAN (DALAM & LUAR NEGERI) ================= */
        Schema::table('employee_trainings', function (Blueprint $table) {
            // perluasan jenis: DIKLAT lama + SEMINAR & PELATIHAN baru
            $table->string('type', 30)->default('TEKNIS')->change();
            // lingkup penyelenggaraan: dalam / luar negeri
            $table->string('scope', 20)->default('DALAM_NEGERI')->after('type');
        });

        /* ================= 4. RIWAYAT KENAIKAN PANGKAT ================= */
        Schema::create('employee_rank_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('old_rank_id')->nullable()->constrained('ranks')->nullOnDelete();
            $table->foreignId('new_rank_id')->nullable()->constrained('ranks')->nullOnDelete();
            $table->string('sk_number', 100)->nullable();   // nomor SK kenaikan pangkat
            $table->date('effective_date')->nullable();     // TMT kenaikan pangkat
            $table->string('notes', 255)->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_rank_histories');

        Schema::table('employee_trainings', function (Blueprint $table) {
            $table->dropColumn('scope');
            $table->enum('type', ['KEPEMIMPINAN', 'TEKNIS', 'FUNGSIONAL', 'SOSIAL_KULTURAL'])->default('TEKNIS')->change();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('english_skill');
        });

        DB::table('employment_statuses')
            ->where('code', 'ASN')
            ->update(['code' => 'PNS', 'name' => 'PNS']);
    }
};

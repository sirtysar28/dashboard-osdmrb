<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ================= UNIT KERJA (Eselon I -> Eselon II -> Balai) ================= */
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->enum('level', ['KEMENTERIAN', 'ES_I', 'ES_II', 'ES_III', 'BALAI', 'LAINNYA'])->default('LAINNYA');
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        /* ================= MASTER PENDIDIKAN ================= */
        Schema::create('education_levels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        /* ================= MASTER GOLONGAN / PANGKAT ================= */
        Schema::create('ranks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name')->nullable();          // nama pangkat, mis. Pembina
            $table->string('group_name')->nullable();    // golongan, mis. IV.a
            $table->boolean('is_pppk')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        /* ================= MASTER STATUS KEPEGAWAIAN ================= */
        Schema::create('employment_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->timestamps();
        });

        /* ================= MASTER LEVEL JABATAN ================= */
        Schema::create('job_levels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        /* ================= MASTER JENIS JABATAN ================= */
        Schema::create('position_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->timestamps();
        });

        /* ================= MASTER JABATAN ================= */
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_level_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
        Schema::dropIfExists('position_types');
        Schema::dropIfExists('job_levels');
        Schema::dropIfExists('employment_statuses');
        Schema::dropIfExists('ranks');
        Schema::dropIfExists('education_levels');
        Schema::dropIfExists('units');
    }
};

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    public const TYPE_ASN = 'asn';
    public const TYPE_NON_ASN = 'non_asn';

    /** Pilihan kemampuan Bahasa Inggris (data personal). */
    public const ENGLISH_SKILLS = [
        'tidak' => 'Tidak Bisa',
        'dasar' => 'Dasar',
        'menengah' => 'Menengah',
        'lanjutan' => 'Lanjutan',
    ];

    protected $fillable = [
        'nip', 'name', 'email', 'phone', 'gender', 'birth_place', 'birth_date',
        'religion', 'address', 'swimming_skill', 'english_skill',
        'employment_status_id', 'rank_id', 'education_level_id', 'unit_id',
        'eselon', 'functional_level', 'position_name', 'tmt_jabatan', 'tmt_golongan',
        'tmt_cpns', 'tmt_pns', 'retirement_date', 'next_promotion_date',
        'education_1', 'education_2', 'education_3',
        'npwp', 'karpeg', 'photo', 'last_sync', 'is_active',
        'employee_type', 'category',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'tmt_jabatan' => 'date',
            'tmt_golongan' => 'date',
            'tmt_cpns' => 'date',
            'tmt_pns' => 'date',
            'retirement_date' => 'date',
            'next_promotion_date' => 'date',
            'last_sync' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /* ================= RELATIONS ================= */

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function education(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class, 'education_level_id');
    }

    public function rank(): BelongsTo
    {
        return $this->belongsTo(Rank::class);
    }

    public function employmentStatus(): BelongsTo
    {
        return $this->belongsTo(EmploymentStatus::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(EmployeePosition::class);
    }

    public function currentPosition(): HasOne
    {
        return $this->hasOne(EmployeePosition::class)->where('is_current', true)
            ->with('position.positionType', 'position.jobLevel');
    }

    public function letters(): HasMany
    {
        return $this->hasMany(Letter::class);
    }

    /** Riwayat diklat / seminar / pelatihan pegawai. */
    public function trainings(): HasMany
    {
        return $this->hasMany(EmployeeTraining::class)->orderByDesc('year')->orderByDesc('start_date');
    }

    /** Riwayat kenaikan pangkat (mis. III/a -> III/b). */
    public function rankHistories(): HasMany
    {
        return $this->hasMany(EmployeeRankHistory::class)->orderByDesc('effective_date');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /* ================= ATTRIBUTES ================= */

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->age;
    }

    /**
     * Masa kerja "X Tahun Y Bulan" dihitung dari TMT CPNS
     * (kolom TMT PNS dipakai bila TMT CPNS kosong) — Bagian I formulir cuti.
     */
    public function getMasaKerjaAttribute(): string
    {
        $start = $this->tmt_cpns ?? $this->tmt_pns;

        if (! $start) {
            return '-';
        }

        $years = $start->diffInYears(now());
        $months = $start->copy()->addYears($years)->diffInMonths(now());

        return "{$years} Tahun {$months} Bulan";
    }

    public function getGenderLabelAttribute(): string
    {
        return $this->gender === 'P' ? 'Perempuan' : 'Laki-Laki';
    }

    public function getSwimmingSkillLabelAttribute(): string
    {
        return match ($this->swimming_skill) {
            'lulus' => 'Lulus Ujian Renang',
            'belum' => 'Belum Lulus Ujian',
            default => '-',
        };
    }

    public function getEnglishSkillLabelAttribute(): string
    {
        return self::ENGLISH_SKILLS[$this->english_skill] ?? '-';
    }

    public function getIsAsnAttribute(): bool
    {
        return $this->employee_type !== self::TYPE_NON_ASN;
    }

    public function getEmployeeTypeLabelAttribute(): string
    {
        return $this->is_asn ? 'ASN' : 'Non ASN';
    }

    /* ================= BUP (Batas Usia Pensiun) ================= */

    /**
     * Batas Usia Pensiun (BUP) menurut peraturan ASN:
     * - 60 tahun : Pejabat Pimpinan Tinggi Madya & Pratama (Eselon I dan II)
     *              serta Jabatan Fungsional Madya.
     * - Selain itu 58 tahun.
     */
    public function getBupAttribute(): ?int
    {
        if (! $this->is_asn) {
            return null;
        }

        $fungsionalMadya = $this->functional_level
            && str_contains(mb_strtolower($this->functional_level), 'madya');

        if (in_array($this->eselon, ['I', 'II']) || $fungsionalMadya) {
            return 60;
        }

        return 58;
    }

    /**
     * Tanggal BUP terhitung dari tanggal lahir (end of month sesuai ketentuan umum).
     */
    public function getComputedRetirementDateAttribute(): ?\Carbon\CarbonInterface
    {
        if (! $this->birth_date || ! $this->bup) {
            return null;
        }

        return $this->birth_date->copy()
            ->addYears($this->bup)
            ->endOfMonth();
    }

    /**
     * Tanggal pensiun efektif (kolom data bila ada, jika tidak dihitung dari BUP).
     */
    public function getEffectiveRetirementDateAttribute(): ?\Carbon\CarbonInterface
    {
        return $this->retirement_date ?? $this->computed_retirement_date;
    }

    /* ================= STATUS PENSIUN OTOMATIS ================= */

    /**
     * Pegawai dianggap SUDAH PENSIUN bila batas usia pensiunnya telah
     * terlewati — pegawai tersebut tetap tampil di dashboard, hanya
     * statusnya otomatis menjadi "Pensiun".
     */
    public function getIsRetiredAttribute(): bool
    {
        return $this->retirement_date !== null
            && $this->retirement_date->endOfDay()->isPast();
    }

    /**
     * Status kepegawaian tampilan — otomatis "Pensiun" saat BUP terlewati,
     * selain itu mengikuti master status kepegawaian (ASN/CPNS/PPPK/dll).
     */
    public function getDisplayStatusAttribute(): string
    {
        if ($this->is_retired) {
            return 'Pensiun';
        }

        return $this->employmentStatus?->name ?? '-';
    }

    /**
     * Estimasi kenaikan jabatan / pangkat berikutnya
     * (kolom data bila ada, jika tidak TMT golongan + 4 tahun).
     */
    public function getNextPromotionEstimatedAttribute(): ?\Carbon\CarbonInterface
    {
        if ($this->next_promotion_date) {
            return $this->next_promotion_date;
        }

        return $this->tmt_golongan?->copy()->addYears(4);
    }

    /**
     * Estimasi Kenaikan Gaji Berkala (KGB) berikutnya — periode 2 tahun,
     * berlaku bagi ASN (PNS, CPNS) maupun PPPK/P3K.
     * Dihitung dari TMT golongan terakhir + kelipatan 2 tahun
     * hingga mendapat tanggal yang akan datang.
     */
    public function getNextSalaryRaiseAttribute(): ?\Carbon\CarbonInterface
    {
        $tmt = $this->tmt_golongan;

        if (! $tmt) {
            return null;
        }

        $date = $tmt->copy()->addYears(2);

        while ($date->isPast()) {
            $date->addYears(2);
        }

        return $date;
    }

    public function getAgeGroupAttribute(): string
    {
        $age = $this->age;

        return match (true) {
            $age === null => 'N/A',
            $age <= 30 => '20-30',
            $age <= 40 => '31-40',
            $age <= 50 => '41-50',
            $age <= 58 => '51-58',
            default => '>58',
        };
    }
}

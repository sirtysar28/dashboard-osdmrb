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

    protected $fillable = [
        'nip', 'name', 'email', 'phone', 'gender', 'birth_place', 'birth_date',
        'religion', 'address',
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

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /* ================= ATTRIBUTES ================= */

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->age;
    }

    public function getGenderLabelAttribute(): string
    {
        return $this->gender === 'P' ? 'Perempuan' : 'Laki-Laki';
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

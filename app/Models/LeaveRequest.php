<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';

    public const TYPE_TAHUNAN = 'tahunan';
    public const TYPE_BESAR = 'besar';
    public const TYPE_SAKIT = 'sakit';
    public const TYPE_MELAHIRKAN = 'melahirkan';
    public const TYPE_ALASAN_PENTING = 'alasan_penting';
    public const TYPE_CLTN = 'cltn'; // Cuti di Luar Tanggungan Negara

    /**
     * Hak cuti tahunan per tahun (PP 11 Tahun 2017 & PP 6 Tahun 2024).
     */
    public const ANNUAL_ENTITLEMENT = 12;

    protected $fillable = [
        'employee_id', 'type', 'start_date', 'end_date', 'total_days',
        'reason', 'address_during_leave', 'phone_during_leave',
        'balance_year', 'annual_n2', 'annual_n1', 'annual_n', 'leave_note',
        'status', 'verified_by', 'verified_at', 'approved_by',
        'approved_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'verified_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /* ================= RELATIONS ================= */

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /* ================= LABELS ================= */

    public static function typeOptions(): array
    {
        return [
            self::TYPE_TAHUNAN => 'Cuti Tahunan',
            self::TYPE_BESAR => 'Cuti Besar',
            self::TYPE_SAKIT => 'Cuti Sakit',
            self::TYPE_MELAHIRKAN => 'Cuti Melahirkan',
            self::TYPE_ALASAN_PENTING => 'Cuti Karena Alasan Penting',
            self::TYPE_CLTN => 'Cuti di Luar Tanggungan Negara',
        ];
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeOptions()[$this->type] ?? $this->type;
    }

    public function getStatusLabelAttribute(): string
    {
        return [
            self::STATUS_PENDING => 'Menunggu Verifikasi',
            self::STATUS_VERIFIED => 'Terverifikasi',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_REJECTED => 'Ditolak',
        ][$this->status] ?? $this->status;
    }

    public function getStatusBadgeAttribute(): string
    {
        return [
            self::STATUS_PENDING => 'warning text-dark',
            self::STATUS_VERIFIED => 'info text-dark',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
        ][$this->status] ?? 'secondary';
    }

    /**
     * Ringkasan rentang cuti, mis. "12 s.d. 16 September 2026 (5 hari)".
     */
    public function getPeriodLabelAttribute(): string
    {
        return $this->start_date?->translatedFormat('d F Y').' s.d. '
            .$this->end_date?->translatedFormat('d F Y')
            ." ({$this->total_days} hari)";
    }

    /* ================= CATATAN CUTI (BAGIAN V) ================= */

    /**
     * SISA cuti tahunan pegawai pada tahun tertentu — dihitung otomatis
     * dari riwayat pengajuan cuti tahunan (yang tidak ditolak):
     *   sisa = hak 12 hari - hari cuti tahunan tahun tersebut.
     */
    public static function annualBalance(int $employeeId, int $year): int
    {
        $used = (int) static::query()
            ->where('employee_id', $employeeId)
            ->where('type', self::TYPE_TAHUNAN)
            ->where('status', '!=', self::STATUS_REJECTED)
            ->whereYear('start_date', $year)
            ->sum('total_days');

        return max(0, self::ANNUAL_ENTITLEMENT - $used);
    }

    /**
     * Posisi sisa cuti tahunan N-2, N-1 dan N seorang pegawai.
     *
     * @return array{year: int, n2: int, n1: int, n: int}
     */
    public static function annualBalances(int $employeeId, ?int $year = null): array
    {
        $year ??= (int) now()->format('Y');

        return [
            'year' => $year,
            'n2' => static::annualBalance($employeeId, $year - 2),
            'n1' => static::annualBalance($employeeId, $year - 1),
            'n' => static::annualBalance($employeeId, $year),
        ];
    }

    /**
     * Label tahun Bagian V, mis. [2024, 2025, 2026] (N = tahun acuan).
     */
    public function getBalanceYearsAttribute(): array
    {
        $year = $this->balance_year
            ?? $this->start_date?->format('Y')
            ?? now()->format('Y');

        return [(int) $year - 2, (int) $year - 1, (int) $year];
    }
}

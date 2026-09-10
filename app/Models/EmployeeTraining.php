<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeTraining extends Model
{
    public const TYPES = [
        'KEPEMIMPINAN' => 'Diklat Kepemimpinan',
        'TEKNIS' => 'Diklat Teknis',
        'FUNGSIONAL' => 'Diklat Fungsional',
        'SOSIAL_KULTURAL' => 'Diklat Sosial Kultural',
    ];

    protected $fillable = [
        'employee_id', 'name', 'type', 'organizer', 'year',
        'start_date', 'end_date', 'hours', 'certificate_number',
        'file_path', 'file_name', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'hours' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /* ================= RELATIONS ================= */

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /* ================= LABELS ================= */

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getTypeBadgeAttribute(): string
    {
        return match ($this->type) {
            'KEPEMIMPINAN' => 'primary',
            'TEKNIS' => 'info',
            'FUNGSIONAL' => 'success',
            'SOSIAL_KULTURAL' => 'warning',
            default => 'secondary',
        };
    }

    public function getHasFileAttribute(): bool
    {
        return (bool) $this->file_path;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchiveLoan extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_RETURNED = 'RETURNED';

    protected $fillable = [
        'archive_id', 'employee_id', 'purpose', 'loan_date', 'due_date',
        'returned_at', 'status', 'handled_by', 'note',
    ];

    protected function casts(): array
    {
        return [
            'loan_date' => 'date',
            'due_date' => 'date',
            'returned_at' => 'date',
        ];
    }

    public function archive(): BelongsTo
    {
        return $this->belongsTo(Archive::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Menunggu Persetujuan',
            self::STATUS_APPROVED => 'Dipinjam',
            self::STATUS_REJECTED => 'Ditolak',
            self::STATUS_RETURNED => 'Dikembalikan',
            default => $this->status,
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_APPROVED => 'info',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_RETURNED => 'success',
            default => 'secondary',
        };
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && $this->due_date->isPast();
    }
}

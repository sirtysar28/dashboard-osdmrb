<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sop extends Model
{
    public const CATEGORIES = [
        'Pelayanan Publik',
        'Kepegawaian',
        'Keuangan',
        'Umum & Perlengkapan',
        'Perencanaan',
        'Hukum',
    ];

    public const STATUSES = [
        'BERLAKU' => 'Berlaku',
        'DITINJAU' => 'Sedang Ditinjau',
        'DICABUT' => 'Dicabut',
    ];

    protected $fillable = [
        'title', 'category', 'number', 'unit', 'year', 'status',
        'description', 'file_path', 'file_name', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
        ];
    }

    /* ================= RELATIONS ================= */

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /* ================= LABELS ================= */

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'BERLAKU' => 'success',
            'DITINJAU' => 'warning',
            'DICABUT' => 'danger',
            default => 'secondary',
        };
    }

    public function getHasFileAttribute(): bool
    {
        return (bool) $this->file_path;
    }
}

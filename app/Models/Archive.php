<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Archive extends Model
{
    public const TYPES = [
        'SURAT_MASUK' => 'Surat Masuk',
        'SURAT_KELUAR' => 'Surat Keluar',
        'SK' => 'Surat Keputusan',
        'KONTRAK' => 'Kontrak / Perjanjian',
        'LAPORAN' => 'Laporan',
        'DOKUMEN_PEGAWAI' => 'Dokumen Pegawai',
        'LAINNYA' => 'Lainnya',
    ];

    public const RETENTIONS = [
        'AKTIF' => 'Arsip Aktif',
        'INAKTIF' => 'Arsip Inaktif',
        'MUSNAH' => 'Musnah',
        'DINILAI_KEMBALI' => 'Dinilai Kembali',
        'PERMANEN' => 'Permanen',
    ];

    public const STATUSES = [
        'TERSEDIA' => 'Tersedia',
        'DIPINJAM' => 'Dipinjam',
        'DIPINDAHKAN' => 'Dipindahkan',
        'DIMUSNAHKAN' => 'Dimusnahkan',
    ];

    protected $fillable = [
        'archive_number', 'title', 'description',
        'archive_category_id', 'employee_id', 'unit_id', 'letter_id',
        'type', 'document_date', 'year',
        'retention', 'retention_years', 'retention_until', 'physical_location',
        'status', 'visibility', 'file_path', 'file_name', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'retention_until' => 'date',
        ];
    }

    /* ================= RELATIONS ================= */

    public function category(): BelongsTo
    {
        return $this->belongsTo(ArchiveCategory::class, 'archive_category_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(ArchiveLoan::class);
    }

    public function activeLoan(): HasMany
    {
        return $this->hasMany(ArchiveLoan::class)
            ->whereIn('status', [ArchiveLoan::STATUS_PENDING, ArchiveLoan::STATUS_APPROVED]);
    }

    /* ================= LABELS ================= */

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getRetentionLabelAttribute(): string
    {
        return self::RETENTIONS[$this->retention] ?? $this->retention;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'TERSEDIA' => 'success',
            'DIPINJAM' => 'warning',
            'DIPINDAHKAN' => 'info',
            'DIMUSNAHKAN' => 'danger',
            default => 'secondary',
        };
    }

    public function getIsBorrowableAttribute(): bool
    {
        return $this->status === 'TERSEDIA';
    }

    /**
     * Buat arsip otomatis dari surat yang telah disetujui.
     */
    public static function createFromLetter(Letter $letter): ?self
    {
        if (static::where('letter_id', $letter->id)->exists()) {
            return null;
        }

        $sequence = static::whereYear('created_at', now()->year)->count() + 1;

        return static::create([
            'archive_number' => 'ARS/'.now()->format('Y').'/'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'title' => $letter->subject.' — '.$letter->employee?->name,
            'description' => 'Arsip otomatis dari persuratan: '.$letter->letterType?->name.' No. '.$letter->number,
            'employee_id' => $letter->employee_id,
            'letter_id' => $letter->id,
            'type' => 'SURAT_KELUAR',
            'document_date' => $letter->letter_date,
            'year' => $letter->letter_date?->year ?? now()->year,
            'retention' => 'AKTIF',
            'retention_years' => 5,
            'retention_until' => now()->addYears(5),
            'visibility' => 'PUBLIK',
            'uploaded_by' => $letter->approved_by,
        ]);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengajuan Layanan Kepegawaian — Catatan Masukan 19 Nov 2026.
 *
 * Pegawai ASN mengajukan layanan (KGB, kenaikan pangkat, pemberhentian &
 * pensiun, tugas/izin belajar, pengembangan kompetensi) dilengkapi Nota
 * Dinas (unggah berkas) dan link Bukti Dukung.
 */
class ServiceRequest extends Model
{
    /** @use HasFactory<\Database\Factories\ServiceRequestFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PROCESSING = 'DIPROSES';
    public const STATUS_DONE = 'SELESAI';
    public const STATUS_REJECTED = 'DITOLAK';

    public const TYPE_KGB = 'kgb';
    public const TYPE_KP = 'kenaikan_pangkat';
    public const TYPE_PENSIUN = 'pemberhentian_pensiun';
    public const TYPE_BELAJAR = 'tugas_izin_belajar';
    public const TYPE_KOMPETENSI = 'pengembangan_kompetensi';

    protected $fillable = [
        'unit_id', 'employee_id', 'service_type',
        'nota_path', 'nota_name', 'supporting_link',
        'status', 'note', 'processed_by', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    /* ================= RELATIONS ================= */

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /* ================= LABELS ================= */

    /** Jenis layanan yang tersedia (Catatan Masukan 19 Nov 2026). */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_KGB => 'Kenaikan Gaji Berkala',
            self::TYPE_KP => 'Kenaikan Pangkat',
            self::TYPE_PENSIUN => 'Pemberhentian dan Pensiun',
            self::TYPE_BELAJAR => 'Tugas / Izin Belajar',
            self::TYPE_KOMPETENSI => 'Pengembangan Kompetensi',
        ];
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeOptions()[$this->service_type] ?? $this->service_type;
    }

    public function getStatusLabelAttribute(): string
    {
        return [
            self::STATUS_PENDING => 'Diajukan',
            self::STATUS_PROCESSING => 'Diproses',
            self::STATUS_DONE => 'Selesai',
            self::STATUS_REJECTED => 'Ditolak',
        ][$this->status] ?? $this->status;
    }

    public function getStatusBadgeAttribute(): string
    {
        return [
            self::STATUS_PENDING => 'warning text-dark',
            self::STATUS_PROCESSING => 'info text-dark',
            self::STATUS_DONE => 'success',
            self::STATUS_REJECTED => 'danger',
        ][$this->status] ?? 'secondary';
    }
}

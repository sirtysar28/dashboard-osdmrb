<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Master kampus / perguruan tinggi — sumber dropdown pendidikan
 * terakhir (S1/S2/S3) pada form pegawai.
 */
class Campus extends Model
{
    public const TYPE_NEGERI = 'negeri';
    public const TYPE_SWASTA = 'swasta';
    public const TYPE_LUAR_NEGERI = 'luar_negeri';

    protected $fillable = ['name', 'city', 'type', 'sort_order'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /* ================= ATTRIBUTES ================= */

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_SWASTA => 'Swasta',
            self::TYPE_LUAR_NEGERI => 'Luar Negeri',
            default => 'Negeri',
        };
    }

    public function getTypeBadgeAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_SWASTA => 'bg-info',
            self::TYPE_LUAR_NEGERI => 'bg-secondary',
            default => 'bg-primary',
        };
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterLog extends Model
{
    protected $fillable = [
        'letter_id', 'user_id', 'action', 'from_status', 'to_status', 'note',
    ];

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'SUBMIT' => 'Pengajuan',
            'VERIFY' => 'Verifikasi',
            'APPROVE' => 'Persetujuan',
            'REJECT' => 'Penolakan',
            'PRINT' => 'Cetak',
            default => $this->action,
        };
    }
}

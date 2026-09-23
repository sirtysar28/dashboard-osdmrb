<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat kenaikan pangkat pegawai (contoh: III/a -> III/b dan seterusnya).
 */
class EmployeeRankHistory extends Model
{
    protected $fillable = [
        'employee_id', 'old_rank_id', 'new_rank_id',
        'sk_number', 'effective_date', 'notes',
    ];

    protected function casts(): array
    {
        return ['effective_date' => 'date'];
    }

    /* ================= RELATIONS ================= */

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function oldRank(): BelongsTo
    {
        return $this->belongsTo(Rank::class, 'old_rank_id');
    }

    public function newRank(): BelongsTo
    {
        return $this->belongsTo(Rank::class, 'new_rank_id');
    }

    /* ================= ATTRIBUTES ================= */

    /** Label transisi pangkat, mis. "III/a → III/b". */
    public function getTransitionLabelAttribute(): string
    {
        $from = $this->oldRank?->code ?? '-';

        if (! $this->newRank) {
            return $from;
        }

        return "{$from} → {$this->newRank->code}";
    }
}

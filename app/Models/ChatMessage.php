<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pesan / chat private antar pegawai (antar akun pengguna).
 */
class ChatMessage extends Model
{
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'message',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /* ================= RELATIONS ================= */

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /* ================= SCOPES ================= */

    /** Semua pesan dalam percakapan dua pengguna (dua arah). */
    public function scopeConversation(Builder $query, int $userA, int $userB): Builder
    {
        return $query->where(function (Builder $q) use ($userA, $userB) {
            $q->where(fn ($qq) => $qq->where('sender_id', $userA)->where('receiver_id', $userB))
                ->orWhere(fn ($qq) => $qq->where('sender_id', $userB)->where('receiver_id', $userA));
        });
    }

    /** Pesan yang belum dibaca untuk penerima tertentu. */
    public function scopeUnreadFor(Builder $query, int $userId): Builder
    {
        return $query->where('receiver_id', $userId)->whereNull('read_at');
    }

    /* ================= HELPERS ================= */

    /** Tandai pesan masuk dari lawan bicara sebagai sudah dibaca. */
    public static function markConversationRead(int $userId, int $otherUserId): void
    {
        static::unreadFor($userId)
            ->where('sender_id', $otherUserId)
            ->update(['read_at' => now()]);
    }
}

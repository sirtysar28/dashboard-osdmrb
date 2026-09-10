<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Survei masukan & saran aplikasi dari pengguna.
 */
class Survey extends Model
{
    protected $fillable = ['user_id', 'user_name', 'rating', 'message'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function stats(): array
    {
        $average = (float) static::query()->avg('rating');

        return [
            'total' => static::query()->count(),
            'average' => $average > 0 ? round($average, 1) : 0,
            'latest' => static::query()->latest()->limit(8)->get(),
            'distribution' => collect(range(1, 5))->mapWithKeys(
                fn ($star) => [$star => static::query()->where('rating', $star)->count()]
            )->all(),
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Announcement extends Model
{
    protected $fillable = [
        'title', 'running_text', 'image_path', 'link_url', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Pengumuman aktif untuk ditampilkan di dashboard (paling baru).
     */
    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->latest()->first();
    }

    /**
     * Semua pengumuman aktif (untuk slider di dashboard, paling baru dulu).
     */
    public static function activeAll(): \Illuminate\Database\Eloquent\Collection
    {
        return static::query()->where('is_active', true)->latest()->get();
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        // URL gambar eksternal (diisi lewat kolom "URL gambar" pada form)
        if (preg_match('#^https?://#i', $this->image_path)) {
            return $this->image_path;
        }

        return Storage::disk('public')->exists($this->image_path)
            ? Storage::disk('public')->url($this->image_path)
            : null;
    }

    /** Apakah gambar berasal dari berkas lokal (bukan URL eksternal)? */
    public function getIsLocalImageAttribute(): bool
    {
        return $this->image_path && ! preg_match('#^https?://#i', $this->image_path);
    }
}

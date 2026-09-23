<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan aplikasi (key-value) — dikelola oleh Administrator Utama.
 *
 * Kelas ini juga menyediakan helper statis:
 * Setting::get('key', $default), Setting::set('key', $value)
 * Setting::menuVisible('letters')  — apakah menu sidebar tampil.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = true;

    protected static bool $cacheLoaded = false;

    /* ================= HELPER STATIS ================= */

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::allCached();

        if (! array_key_exists($key, $all)) {
            return $default;
        }

        $value = $all[$key];

        return $value === null || $value === '' ? $default : $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = static::get($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => is_bool($value) ? (string) (int) $value : (string) $value],
        );

        static::flushCache();
    }

    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::query()->updateOrCreate(
                ['key' => $key],
                ['value' => is_bool($value) ? (string) (int) $value : (string) $value],
            );
        }

        static::flushCache();
    }

    /**
     * Apakah sebuah menu sidebar ditampilkan.
     * Menu yang belum punya pengaturan dianggap tampil (default true),
     * kecuali menu yang memang disembunyikan lewat default di bawah.
     */
    public static function menuVisible(string $menu): bool
    {
        // menu yang secara default disembunyikan (fitur belum digunakan)
        $hiddenByDefault = ['letters', 'archives', 'reformasi_birokrasi', 'manajemen_talenta', 'diklat', 'cuti'];

        $value = static::get("menu_{$menu}");

        if ($value === null) {
            return ! in_array($menu, $hiddenByDefault, true);
        }

        return static::bool("menu_{$menu}");
    }

    public static function flushCache(): void
    {
        Cache::forget('app.settings.all');
        static::$cacheLoaded = false;
    }

    protected static function allCached(): array
    {
        return Cache::remember('app.settings.all', now()->addMinutes(10), function () {
            try {
                return static::query()->pluck('value', 'key')->all();
            } catch (\Throwable) {
                return []; // saat migrasi belum dijalankan
            }
        });
    }
}

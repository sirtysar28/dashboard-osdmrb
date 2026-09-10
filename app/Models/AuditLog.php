<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

/**
 * Log aktivitas pengguna + statistik pengunjung aplikasi.
 */
class AuditLog extends Model
{
    public const EVENT_VISIT = 'visit';
    public const EVENT_LOGIN = 'login';
    public const EVENT_LOGOUT = 'logout';
    public const EVENT_LOGIN_FAILED = 'login_failed';
    public const EVENT_OTP = 'otp';
    public const EVENT_CREATE = 'create';
    public const EVENT_UPDATE = 'update';
    public const EVENT_DELETE = 'delete';
    public const EVENT_PASSWORD = 'password';
    public const EVENT_REGISTER = 'register';

    protected $fillable = [
        'user_id', 'user_name', 'event', 'module', 'description',
        'ip_address', 'user_agent', 'url',
    ];

    /* ================= RELATIONS ================= */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* ================= SCOPES ================= */

    public function scopeVisits(Builder $query): Builder
    {
        return $query->where('event', self::EVENT_VISIT);
    }

    /**
     * Aktivitas "pengunjung aplikasi": kunjungan halaman + keberhasilan login.
     * Login dihitung sebagai kunjungan sehingga statistik tetap terisi
     * walaupun rekaman kunjungan halaman gagal tercatat.
     */
    public function scopeVisitorActivity(Builder $query): Builder
    {
        return $query->whereIn('event', [self::EVENT_VISIT, self::EVENT_LOGIN]);
    }

    /* ================= STATISTIK PENGUNJUNG ================= */

    public static function visitorStats(): array
    {
        $activity = fn () => static::query()->visitorActivity();

        return [
            'today' => $activity()->whereDate('created_at', today())->count(),
            'yesterday' => $activity()->whereDate('created_at', today()->subDay())->count(),
            'week' => $activity()->whereBetween('created_at', [now()->startOfWeek(), now()])->count(),
            'month' => $activity()->whereBetween('created_at', [now()->startOfMonth(), now()])->count(),
            'total' => $activity()->count(),
            'uniqueToday' => $activity()->whereDate('created_at', today())->distinct()->count('user_id'),
        ];
    }

    /* ================= HELPER ================= */

    public static function record(
        string $event,
        ?string $module = null,
        ?string $description = null,
        ?\Illuminate\Http\Request $request = null,
        ?User $user = null,
    ): self {
        $request ??= request();
        $user ??= $request->user();

        try {
            return static::create([
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'event' => $event,
                'module' => $module,
                'description' => $description,
                'ip_address' => $request?->ip(),
                'user_agent' => substr((string) $request?->userAgent(), 0, 500),
                'url' => substr((string) $request?->fullUrl(), 0, 255),
            ]);
        } catch (\Throwable $e) {
            // jangan ganggu request utama bila logging gagal,
            // namun tetap catat penyebabnya untuk penelusuran
            Log::warning('Audit log gagal disimpan ('.$event.'): '.$e->getMessage());

            return new static();
        }
    }

    public function getEventLabelAttribute(): string
    {
        return match ($this->event) {
            self::EVENT_VISIT => 'Kunjungan',
            self::EVENT_LOGIN => 'Login',
            self::EVENT_LOGOUT => 'Logout',
            self::EVENT_LOGIN_FAILED => 'Login Gagal',
            self::EVENT_OTP => 'Verifikasi OTP',
            self::EVENT_CREATE => 'Tambah Data',
            self::EVENT_UPDATE => 'Ubah Data',
            self::EVENT_DELETE => 'Hapus Data',
            self::EVENT_PASSWORD => 'Ganti Password',
            self::EVENT_REGISTER => 'Registrasi',
            default => ucfirst(str_replace('_', ' ', $this->event)),
        };
    }

    public function getEventBadgeAttribute(): string
    {
        return match ($this->event) {
            self::EVENT_VISIT => 'secondary',
            self::EVENT_LOGIN, self::EVENT_OTP => 'primary',
            self::EVENT_LOGOUT => 'dark',
            self::EVENT_LOGIN_FAILED => 'danger',
            self::EVENT_CREATE => 'success',
            self::EVENT_UPDATE => 'info',
            self::EVENT_DELETE => 'warning',
            self::EVENT_PASSWORD => 'warning',
            self::EVENT_REGISTER => 'success',
            default => 'secondary',
        };
    }
}

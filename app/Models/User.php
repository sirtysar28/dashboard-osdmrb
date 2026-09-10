<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'employee_id',
        'is_active',
        'otp_code',
        'otp_expires_at',
        'otp_sent_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'otp_expires_at' => 'datetime',
            'otp_sent_at' => 'datetime',
        ];
    }

    /* ================= RELATIONS ================= */

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /* ================= HELPERS ================= */

    /**
     * Administrator Utama (super admin) — satu-satunya yang boleh
     * mengelola pengaturan aplikasi, pengumuman, tema & SMTP.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Role Biro SDM — mirip admin namun master data bersifat read-only
     * dan berfokus pada verifikasi/persetujuan (approval).
     */
    public function isBiroSdm(): bool
    {
        return $this->hasRole('biro_sdm');
    }

    /**
     * Akun istimewa: admin bagian, Biro SDM, atau Administrator Utama
     * (akses dashboard, data pegawai, verifikasi & persetujuan).
     */
    public function isPrivileged(): bool
    {
        return $this->isAdmin() || $this->isBiroSdm() || $this->isSuperAdmin();
    }

    /**
     * Boleh mengakses pengaturan aplikasi (hanya Administrator Utama).
     */
    public function canManageSettings(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * Label peran untuk tampilan (sidebar / dropdown pengguna).
     */
    public function getRoleLabelAttribute(): string
    {
        return match (true) {
            $this->isSuperAdmin() => 'Administrator Utama',
            $this->isAdmin() => 'Admin Bagian',
            $this->isBiroSdm() => 'Biro SDM',
            default => 'Pegawai',
        };
    }

    public function getInitialsAttribute(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->take(2)
            ->implode('');
    }

    /* ================= NOTIFIKASI EMAIL ================= */

    /**
     * Kirim tautan reset password lewat notification kustom aplikasi
     * (ResetPasswordNotification) sehingga memakai SMTP yang dikonfigurasi
     * di menu Pengaturan — bukan mailer bawaan .env yang bisa saja masih
     * 'log' (penyebab tautan reset tidak pernah sampai ke email).
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /* ================= OTP 2-LAYER LOGIN ================= */

    public function generateOtp(int $length = 6): string
    {
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= random_int(0, 9);
        }

        $this->forceFill([
            'otp_code' => $code,
            'otp_expires_at' => now()->addMinutes(10),
            'otp_sent_at' => now(),
        ])->save();

        return $code;
    }

    public function otpIsValid(): bool
    {
        return $this->otp_code !== null
            && $this->otp_expires_at !== null
            && $this->otp_expires_at->isFuture();
    }

    public function verifyOtp(string $code): bool
    {
        return $this->otpIsValid()
            && hash_equals((string) $this->otp_code, trim($code));
    }

    public function clearOtp(): void
    {
        $this->forceFill([
            'otp_code' => null,
            'otp_expires_at' => null,
        ])->save();
    }

    /**
     * Boleh kirim ulang OTP (anti spam minimal 60 detik).
     */
    public function otpResendAllowed(): bool
    {
        return $this->otp_sent_at === null || $this->otp_sent_at->addSeconds(60)->isPast();
    }
}

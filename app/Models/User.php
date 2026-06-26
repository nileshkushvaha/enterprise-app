<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    use HasFactory, HasRoles, LogsActivity, Notifiable;

    // ── Status constants ────────────────────────────────────────────
    public const STATUS_PENDING     = 'pending_verification';
    public const STATUS_ACTIVE      = 'active';
    public const STATUS_INACTIVE    = 'inactive';
    public const STATUS_BLOCKED     = 'blocked';
    public const STATUS_SUSPENDED   = 'suspended';

    public const MAX_FAILED_ATTEMPTS = 5;
    public const LOCK_DURATION_MINUTES = 30;

    // ── Default role for self-registered frontend users ──────────────
    public const DEFAULT_ROLE = 'student';

    // ────────────────────────────────────────────────────────────────

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'status',
        'avatar',
        'email_verified_at',
        'failed_login_count',
        'locked_until',
        'last_login_at',
        'last_login_ip',
        'last_login_user_agent',
        'password_changed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'locked_until'       => 'datetime',
            'last_login_at'      => 'datetime',
            'password_changed_at'=> 'datetime',
            'password'           => 'hashed',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    // ── Accessors ────────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''))
            ?: $this->name;
    }

    // ── Status helpers ───────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPendingVerification(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function isBlocked(): bool
    {
        return in_array($this->status, [self::STATUS_BLOCKED, self::STATUS_SUSPENDED], true);
    }

    // ── Login tracking ───────────────────────────────────────────────

    public function recordSuccessfulLogin(string $ip, string $userAgent): void
    {
        $this->updateQuietly([
            'failed_login_count'    => 0,
            'locked_until'          => null,
            'last_login_at'         => now(),
            'last_login_ip'         => $ip,
            'last_login_user_agent' => $userAgent,
        ]);
    }

    public function recordFailedLogin(): void
    {
        $newCount = $this->failed_login_count + 1;

        $data = ['failed_login_count' => $newCount];

        if ($newCount >= self::MAX_FAILED_ATTEMPTS) {
            $data['locked_until'] = now()->addMinutes(self::LOCK_DURATION_MINUTES);
        }

        $this->updateQuietly($data);
    }

    // ── Email Verification notification override ─────────────────────

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Notifications\Auth\VerifyEmailNotification);
    }

    // ── Filament ─────────────────────────────────────────────────────

    public function canAccessPanel(Panel $panel): bool
    {
        // Only admin-panel users (roles 1+ can be configured here)
        return $this->isActive() && $this->hasVerifiedEmail();
    }

    // ── Activity Log ─────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'first_name', 'last_name', 'email', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('user');
    }
}


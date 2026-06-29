<?php

declare(strict_types=1);

namespace App\Models;

use App\Notifications\Auth\VerifyEmailNotification;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    use HasFactory, HasRoles, LogsActivity, Notifiable;

    // ── Status constants ────────────────────────────────────────────
    public const STATUS_PENDING = 'pending_verification';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_SUSPENDED = 'suspended';

    // Unlock email token TTL — no corresponding settings field; keep as constant
    public const UNLOCK_TOKEN_MINUTES = 60;

    // Recovery codes — 2FA is future scope; keep as constant until TwoFactorSettings exists
    public const RECOVERY_CODES_COUNT = 8;

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
        'locked_at',
        'locked_until',
        'lock_reason',
        'unlock_token',
        'unlock_token_expires_at',
        'last_login_at',
        'last_login_ip',
        'last_login_user_agent',
        'password_changed_at',
        'must_change_password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'login_alerts_enabled',
        'new_device_alerts_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'unlock_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'locked_at' => 'datetime',
            'locked_until' => 'datetime',
            'unlock_token_expires_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'must_change_password' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'login_alerts_enabled' => 'boolean',
            'new_device_alerts_enabled' => 'boolean',
            'password' => 'hashed',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function loginHistories(): HasMany
    {
        return $this->hasMany(LoginHistory::class)->latest('logged_in_at');
    }

    public function authoredPosts(): HasMany
    {
        return $this->hasMany(Post::class, 'author_id');
    }

    // ── Accessors ────────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''))
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
        // New-style lock: locked_at is set by AccountProtectionService / LoginSecurityService
        if ($this->locked_at !== null) {
            // locked_until = null means manual-unlock-only (no auto-expiry)
            if ($this->locked_until === null) {
                return true;
            }

            return $this->locked_until->isFuture();
        }

        // Legacy: only locked_until set (pre-migration records, test fixtures, self-unlock flow)
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function isManualLock(): bool
    {
        return $this->locked_at !== null && $this->locked_until === null;
    }

    public function isBlocked(): bool
    {
        return in_array($this->status, [self::STATUS_BLOCKED, self::STATUS_SUSPENDED], true);
    }

    // ── Login tracking ───────────────────────────────────────────────

    public function recordSuccessfulLogin(string $ip, string $userAgent): void
    {
        $this->updateQuietly([
            'failed_login_count' => 0,
            'locked_at' => null,
            'locked_until' => null,
            'lock_reason' => null,
            'unlock_token' => null,
            'unlock_token_expires_at' => null,
            'last_login_at' => now(),
            'last_login_ip' => $ip,
            'last_login_user_agent' => $userAgent,
        ]);
    }

    // ── Account unlock (self-service) ────────────────────────────────

    public function generateUnlockToken(): string
    {
        $plain = Str::random(64);
        $this->updateQuietly([
            'unlock_token' => hash('sha256', $plain),
            'unlock_token_expires_at' => now()->addMinutes(self::UNLOCK_TOKEN_MINUTES),
        ]);

        return $plain;
    }

    public function unlock(): void
    {
        $this->updateQuietly([
            'failed_login_count' => 0,
            'locked_at' => null,
            'locked_until' => null,
            'lock_reason' => null,
            'unlock_token' => null,
            'unlock_token_expires_at' => null,
        ]);
    }

    // ── Two-Factor Authentication ────────────────────────────────────

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null
            && $this->two_factor_confirmed_at !== null;
    }

    public function twoFactorPending(): bool
    {
        return $this->two_factor_secret !== null
            && $this->two_factor_confirmed_at === null;
    }

    /** Return decoded recovery codes array. */
    public function twoFactorRecoveryCodes(): array
    {
        if (! $this->two_factor_recovery_codes) {
            return [];
        }

        return json_decode(decrypt($this->two_factor_recovery_codes), true) ?? [];
    }

    /** Replace a used recovery code, return whether one was consumed. */
    public function consumeRecoveryCode(string $code): bool
    {
        $codes = $this->twoFactorRecoveryCodes();
        $key = array_search(trim($code), $codes, true);

        if ($key === false) {
            return false;
        }

        // Replace with a fresh code
        $codes[$key] = Str::random(10).'-'.Str::random(10);
        $this->updateQuietly([
            'two_factor_recovery_codes' => encrypt(json_encode(array_values($codes))),
        ]);

        return true;
    }

    // ── Email Verification notification override ─────────────────────

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    // ── Filament ─────────────────────────────────────────────────────

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->hasRole('super_admin') || $this->id === 1) {
            return $this->isActive();
        }

        return $this->isActive() && $this->hasVerifiedEmail();
    }

    // ── Activity Log ─────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'first_name', 'last_name', 'email', 'status', 'email_verified_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('user');
    }
}

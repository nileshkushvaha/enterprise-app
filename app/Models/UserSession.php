<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    public $incrementing = false;
    public $timestamps   = false;

    protected $primaryKey = 'session_id';
    protected $keyType    = 'string';

    protected $fillable = [
        'session_id',
        'user_id',
        'ip_address',
        'user_agent',
        'browser',
        'platform',
        'device_type',
        'last_activity_at',
        'created_at',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
        'created_at'       => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────

    /** Sessions active within the last 30 minutes. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('last_activity_at', '>=', now()->subMinutes(30));
    }

    /** Sessions belonging to a specific user. */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    public function isCurrent(string $currentSessionId): bool
    {
        return $this->session_id === $currentSessionId;
    }

    public function deviceIcon(): string
    {
        return match ($this->device_type) {
            'mobile'  => 'phone',
            'tablet'  => 'tablet',
            'desktop' => 'desktop',
            default   => 'globe',
        };
    }
}

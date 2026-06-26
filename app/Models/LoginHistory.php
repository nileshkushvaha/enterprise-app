<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginHistory extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'ip_address',
        'user_agent',
        'browser',
        'platform',
        'device_type',
        'location_country',
        'location_city',
        'logged_in_at',
        'logged_out_at',
    ];

    protected function casts(): array
    {
        return [
            'logged_in_at'  => 'datetime',
            'logged_out_at' => 'datetime',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->whereIn('status', ['failed', 'locked', 'unverified', 'blocked']);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('logged_in_at', '>=', now()->subDays($days));
    }

    // ── Helpers ──────────────────────────────────────────────────────

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isActive(): bool
    {
        return $this->status === 'success' && $this->logged_out_at === null;
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A platform-owned meeting host identity (today: one Zoom Pro user) and
 * its simultaneous-meeting capacity. Never a credential — the account's
 * OAuth client lives in MeetingSettings and covers every host.
 */
class PlatformMeetingHost extends Model
{
    use HasUuids;

    protected $fillable = [
        'provider',
        'host_reference',
        'label',
        'capacity',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(MeetingHostReservation::class);
    }

    /** The pool for one provider, in the deterministic order reservations try hosts. */
    public function scopeActivePool(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}

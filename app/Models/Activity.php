<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityActorType;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Extended Activity model that adds actor-type awareness to the Spatie audit log.
 *
 * Three actor types are supported:
 *  - User   → authenticated user action (causer_id is set)
 *  - Guest  → anonymous visitor action (guest_name/guest_email/guest_phone)
 *  - System → automated / background process action (no human involved)
 *
 * Use AuditTrailService::logUser/logGuest/logSystem to create records with the
 * correct actor type set. For raw activity() helper calls (backward compat) the
 * creating hook auto-detects the type from causer presence.
 *
 * @property ActivityActorType|null $actor_type
 * @property string|null $guest_name
 * @property string|null $guest_email
 * @property string|null $guest_phone
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $route
 * @property string|null $method
 * @property string|null $session_id
 */
class Activity extends SpatieActivity
{
    // ── Casts ─────────────────────────────────────────────────────────────

    public function getCasts(): array
    {
        return array_merge(parent::getCasts(), [
            'actor_type' => ActivityActorType::class,
        ]);
    }

    // ── Auto-detect actor type for raw activity() helper calls ────────────

    protected static function booted(): void
    {
        static::creating(function (self $activity): void {
            if ($activity->actor_type === null) {
                $activity->actor_type = $activity->causer_id !== null
                    ? ActivityActorType::User
                    : ActivityActorType::System;
            }
        });
    }

    // ── Type helpers ──────────────────────────────────────────────────────

    public function isUser(): bool
    {
        return $this->actor_type === ActivityActorType::User;
    }

    public function isGuest(): bool
    {
        return $this->actor_type === ActivityActorType::Guest;
    }

    public function isSystem(): bool
    {
        return $this->actor_type === ActivityActorType::System;
    }

    // ── Actor accessors — never expose nullable logic to callers ──────────

    public function actorName(): string
    {
        return match ($this->actor_type) {
            ActivityActorType::User => $this->causer?->name ?? 'Unknown User',
            ActivityActorType::Guest => $this->guest_name ?: 'Guest',
            ActivityActorType::System => 'System',
            default => 'Unknown',
        };
    }

    public function actorEmail(): ?string
    {
        return match ($this->actor_type) {
            ActivityActorType::User => $this->causer?->email,
            ActivityActorType::Guest => $this->guest_email ?: null,
            default => null,
        };
    }

    public function actorIdentifier(): string
    {
        return match ($this->actor_type) {
            ActivityActorType::User => $this->causer?->email ?? (string) ($this->causer_id ?? 'unknown'),
            ActivityActorType::Guest => $this->guest_email ?: $this->guest_name ?: $this->ip_address ?: 'guest',
            ActivityActorType::System => 'system',
            default => 'unknown',
        };
    }

    public function actorDescription(): string
    {
        return match ($this->actor_type) {
            ActivityActorType::User => trim(($this->causer?->name ?? 'Unknown').($this->causer?->email ? " <{$this->causer->email}>" : '')),
            ActivityActorType::Guest => trim(($this->guest_name ?: 'Guest').($this->guest_email ? " ({$this->guest_email})" : '')),
            ActivityActorType::System => 'System (automated)',
            default => 'Unknown',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Activity;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched by ActivityObserver whenever a new audit trail record is saved.
 * Carries the fully-populated App\Models\Activity so listeners can inspect
 * actor_type, guest fields, and request context without a second DB query.
 */
final class ActivityCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Activity $activity,
    ) {}
}

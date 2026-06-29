<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\ActivityCreated;
use App\Models\Activity;

/**
 * Watches App\Models\Activity (the project's extended Spatie model).
 * On every new audit trail record, dispatches ActivityCreated so downstream
 * listeners (admin bell notifications, email, Slack, …) can react without
 * coupling to any business service.
 */
final class ActivityObserver
{
    public function created(Activity $activity): void
    {
        ActivityCreated::dispatch($activity);
    }
}

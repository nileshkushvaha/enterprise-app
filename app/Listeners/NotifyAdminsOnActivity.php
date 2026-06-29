<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ActivityCreated;
use App\Services\Admin\AdminNotificationService;
use App\Services\Admin\NotificationMapper;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Notification Policy Bridge.
 *
 * Listens to ActivityCreated, consults NotificationMapper to decide whether
 * the activity warrants an admin notification, then delegates delivery to
 * AdminNotificationService.
 *
 * QUEUE_CONNECTION=sync in tests means this runs inline.
 * Switch to a real queue driver in production for async delivery.
 */
final class NotifyAdminsOnActivity implements ShouldQueue
{
    public string $queue = 'notifications';

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(
        private readonly NotificationMapper $mapper,
        private readonly AdminNotificationService $service,
    ) {}

    public function handle(ActivityCreated $event): void
    {
        $activity = $event->activity;

        $payload = $this->mapper->map($activity);

        if ($payload === null) {
            return;
        }

        $actor = $activity->isUser() ? $activity->causer : null;

        $this->service->notify($payload, $actor);
    }
}

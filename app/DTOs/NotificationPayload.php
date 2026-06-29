<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Immutable value object produced by NotificationMapper.
 * AdminNotificationService consumes this to create Filament DB notifications.
 */
final readonly class NotificationPayload
{
    public function __construct(
        public ?int $activityId,
        public string $title,
        public string $body,
        public string $icon,
        public string $color,
        public string $severity,
        public string $category,
        public int $priority,
        public ?string $url = null,
    ) {}
}

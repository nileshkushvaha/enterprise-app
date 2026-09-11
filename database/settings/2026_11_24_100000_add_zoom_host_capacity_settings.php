<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Zoom host capacity reservation — the rollout switch and its buffer.
 *
 * Ships OFF. While off, nothing about booking acceptance or meeting
 * creation changes. Turning it on makes every Zoom-bound booking
 * reserve a slot on a registered platform host at acceptance time
 * (App\Booking\Services\MeetingHostCapacityService) and refuses the
 * booking clearly when no host has capacity. It must only be enabled
 * after `meetings:zoom-hosts:register` has created the host row and
 * `meetings:zoom-hosts:preflight` reports no unresolved overlaps —
 * see docs/meetings.md §4a.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('meeting.zoom_host_capacity_enabled', false);
        // Operational buffer added on BOTH sides of the occupied
        // interval, on top of the join-window allowances the host
        // already needs to be present for.
        $this->migrator->add('meeting.zoom_host_capacity_buffer_minutes', 5);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('meeting.zoom_host_capacity_enabled');
        $this->migrator->deleteIfExists('meeting.zoom_host_capacity_buffer_minutes');
    }
};

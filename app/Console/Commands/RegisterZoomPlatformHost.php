<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Booking\Meetings\ZoomMeetingProvider;
use App\Models\PlatformMeetingHost;
use App\Settings\MeetingSettings;
use Illuminate\Console\Command;

/**
 * Registers (or updates) the platform's Zoom host in the capacity pool
 * from the identity already configured in Meeting Settings — the one
 * deliberate operator step before `zoom_host_capacity_enabled` may be
 * switched on. Idempotent: re-running updates label/capacity/active on
 * the same row. Never touches credentials or any setting.
 *
 * A second licensed host later is the same command with --host=<zoom
 * user id or email> (still under the one account and one OAuth client).
 */
final class RegisterZoomPlatformHost extends Command
{
    protected $signature = 'meetings:zoom-hosts:register
        {--host= : Zoom user id or email; defaults to the configured zoom_host_user_id / zoom_host_email}
        {--label= : Human-readable label}
        {--capacity=1 : Simultaneous meetings this licence allows (Zoom Pro: 1)}
        {--inactive : Register but keep the host out of the pool}';

    protected $description = 'Register the platform Zoom host (identity only, no credentials) for capacity reservation';

    public function handle(MeetingSettings $settings): int
    {
        $reference = trim((string) ($this->option('host') ?: ($settings->zoom_host_user_id ?? $settings->zoom_host_email ?? '')));

        if ($reference === '') {
            $this->error('No Zoom host identity: pass --host or configure Host User ID / Host Email in Meeting Settings.');

            return self::FAILURE;
        }

        $capacity = (int) $this->option('capacity');

        if ($capacity < 1 || $capacity > 50) {
            $this->error('Capacity must be between 1 and 50.');

            return self::FAILURE;
        }

        $host = PlatformMeetingHost::query()->updateOrCreate(
            ['provider' => ZoomMeetingProvider::KEY, 'host_reference' => $reference],
            [
                'label' => $this->option('label') ?: 'Platform Zoom host',
                'capacity' => $capacity,
                'is_active' => ! $this->option('inactive'),
            ],
        );

        $this->info(sprintf(
            '%s Zoom host "%s" (%s) — capacity %d, %s.',
            $host->wasRecentlyCreated ? 'Registered' : 'Updated',
            $host->label,
            $host->host_reference,
            $host->capacity,
            $host->is_active ? 'active' : 'inactive',
        ));
        $this->line('Capacity reservation itself is governed by Meeting Settings → Reserve Zoom Host Capacity (ships off). Run meetings:zoom-hosts:preflight before enabling it.');

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Booking\Services\MeetingHostCapacityService;
use App\Booking\Services\ZoomHostCapacityPreflightService;
use App\Models\Booking;
use App\Settings\MeetingSettings;
use Illuminate\Console\Command;

/**
 * READ-ONLY rollout report for Zoom host capacity reservation. Writes
 * nothing, changes no setting. Renders ZoomHostCapacityPreflightService
 * — the same computation the settings page uses as its activation
 * gate — so what this prints is exactly what will block enablement.
 *
 * Findings are resolved deliberately: `meetings:zoom-hosts:backfill`
 * reserves capacity for bookings that have room; anything it cannot
 * place is rescheduled to a free hour or cancelled by an operator.
 * A booking whose Zoom meeting already exists is never "switched" to
 * another provider — that transition is not supported. Exit code 1
 * when anything needs attention.
 */
final class PreflightZoomHostCapacity extends Command
{
    protected $signature = 'meetings:zoom-hosts:preflight {--days=60 : Horizon of upcoming bookings to examine}';

    protected $description = 'Read-only report: Zoom host pool, unallocated upcoming Zoom bookings and planned overlaps';

    public function handle(ZoomHostCapacityPreflightService $preflight, MeetingHostCapacityService $capacity, MeetingSettings $settings): int
    {
        $report = $preflight->report(max(1, (int) $this->option('days')));

        $this->components->twoColumnDetail('Capacity reservation', $report->reservationEnabled ? '<fg=green>ENABLED</>' : '<fg=yellow>off</>');
        $this->components->twoColumnDetail('Default meeting provider', $report->defaultProvider);
        $this->components->twoColumnDetail('Occupied interval', sprintf(
            'lesson − %d min (early join) − %d min (buffer)  …  lesson + %d min (late join/closure) + %d min (buffer), UTC',
            $settings->meeting_link_visible_before_minutes,
            $settings->zoom_host_capacity_buffer_minutes,
            $settings->meeting_link_visible_after_minutes,
            $settings->zoom_host_capacity_buffer_minutes,
        ));

        if (! $report->hasHost()) {
            $this->components->error('No active Zoom host is registered. Run meetings:zoom-hosts:register before enabling capacity reservation.');
        }

        foreach ($report->hosts as $host) {
            $this->components->twoColumnDetail(sprintf('Host %s', $host->label ?? $host->host_reference), sprintf('%s · capacity %d', $host->host_reference, $host->capacity));
        }

        $this->newLine();
        $this->components->twoColumnDetail(sprintf('Upcoming Zoom bookings without a reservation (next %d days)', $report->horizonDays), (string) $report->unallocated->count());

        foreach ($report->unallocated as $booking) {
            /** @var Booking $booking */
            [$from, $until] = $capacity->occupiedInterval($booking->starts_at, $booking->ends_at);
            $this->line(sprintf(
                '  %s  %s → %s  instructor #%d  %s/%s  %s',
                $booking->reference,
                $from->format('Y-m-d H:i'),
                $until->format('H:i'),
                $booking->instructor_id,
                $booking->status->value,
                $booking->payment_status->value,
                $booking->meeting?->provider === 'zoom' ? 'zoom meeting '.$booking->meeting->status->value : ($booking->meeting_provider_intent === null ? 'unpinned (default is zoom)' : 'pinned zoom'),
            ));
        }

        $this->newLine();
        $this->components->twoColumnDetail('Planned intervals exceeding pool capacity', (string) $report->overlaps->count());

        foreach ($report->overlaps as $cluster) {
            $this->line(sprintf(
                '  %s → %s  %d concurrent (capacity %d): %s',
                $cluster['from']->format('Y-m-d H:i'),
                $cluster['until']->format('H:i'),
                $cluster['count'],
                $report->poolCapacity,
                implode(', ', $cluster['refs']),
            ));
        }

        $this->newLine();

        if (! $report->hasFindings()) {
            $this->components->info('No findings. Capacity reservation can be enabled.');

            return self::SUCCESS;
        }

        $this->components->warn($report->summary().' Resolve deliberately: meetings:zoom-hosts:backfill reserves what fits; reschedule or cancel what does not. This command changes nothing.');

        return self::FAILURE;
    }
}

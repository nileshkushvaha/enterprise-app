<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Booking\Exceptions\MeetingHostCapacityException;
use App\Booking\Meetings\ZoomMeetingProvider;
use App\Booking\Services\MeetingHostCapacityService;
use App\Booking\Services\ZoomHostCapacityPreflightService;
use App\Models\Booking;
use App\Services\AuditTrailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The supported reconciliation for bookings accepted BEFORE Zoom host
 * capacity reservation existed: give each upcoming Zoom-bound booking
 * that holds no reservation one now, earliest lesson first, under the
 * same host lock and interval rules as a fresh acceptance. Bookings
 * that fit are reserved; a booking that does not fit is reported with
 * the reason and left exactly as it was — an operator reschedules it
 * to a free hour or cancels it. Nothing is switched to another
 * provider: a Zoom meeting that already exists stays a Zoom meeting.
 *
 * Dry-run by default; `--apply` writes. Deliberately allowed while the
 * reservation switch is still OFF, because this is how the pool is
 * brought to a clean state BEFORE the switch is turned on.
 */
final class BackfillZoomHostCapacity extends Command
{
    protected $signature = 'meetings:zoom-hosts:backfill {--days=60 : Horizon of upcoming bookings to examine} {--apply : Write reservations (default is a dry run)}';

    protected $description = 'Reserve Zoom host capacity for upcoming Zoom-bound bookings accepted before capacity reservation existed';

    public function handle(ZoomHostCapacityPreflightService $preflight, MeetingHostCapacityService $capacity, AuditTrailService $audit): int
    {
        $report = $preflight->report(max(1, (int) $this->option('days')));
        $apply = (bool) $this->option('apply');

        if (! $report->hasHost()) {
            $this->components->error('No active Zoom host is registered. Run meetings:zoom-hosts:register first.');

            return self::FAILURE;
        }

        if ($report->unallocated->isEmpty()) {
            $this->components->info('Every upcoming Zoom-bound booking already holds a reservation. Nothing to do.');

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail($apply ? 'Mode' : 'Mode', $apply ? '<fg=yellow>APPLY</>' : 'dry run (pass --apply to write)');
        $reserved = 0;
        $unplaceable = [];

        foreach ($report->unallocated as $booking) {
            /** @var Booking $booking */
            if (! $apply) {
                $this->line(sprintf('  would reserve  %s  %s', $booking->reference, $booking->starts_at->utc()->format('Y-m-d H:i')));

                continue;
            }

            try {
                DB::transaction(function () use ($booking, $capacity, $audit): void {
                    $reservation = $capacity->ensureReserved($booking, allowWhileDisabled: true);

                    // A Zoom meeting created before hosts existed carries
                    // no host id; pin it to the host it was just reserved
                    // on so reschedules keep it there.
                    $meeting = $booking->meeting;

                    if ($meeting !== null && $meeting->provider === ZoomMeetingProvider::KEY && $meeting->platform_meeting_host_id === null) {
                        $meeting->forceFill(['platform_meeting_host_id' => $reservation->platform_meeting_host_id])->save();
                    }

                    $audit->logSystem(
                        'bookings',
                        'meeting_host_capacity_backfilled',
                        sprintf('Zoom host capacity reserved retroactively for booking %s.', $booking->reference),
                        $booking,
                        ['provider' => ZoomMeetingProvider::KEY, 'reservation_id' => $reservation->id],
                    );
                });

                $reserved++;
                $this->line(sprintf('  reserved       %s  %s', $booking->reference, $booking->starts_at->utc()->format('Y-m-d H:i')));
            } catch (MeetingHostCapacityException $e) {
                $unplaceable[] = [$booking->reference, $booking->starts_at->utc()->format('Y-m-d H:i'), $e->getMessage()];
                $this->line(sprintf('  <fg=red>NOT placed</>     %s  %s  %s', $booking->reference, $booking->starts_at->utc()->format('Y-m-d H:i'), $e->getMessage()));
            }
        }

        $this->newLine();

        if (! $apply) {
            $this->components->info(sprintf('%d booking(s) would be attempted. Re-run with --apply.', $report->unallocated->count()));

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('Reserved', (string) $reserved);
        $this->components->twoColumnDetail('Not placed (reschedule or cancel by hand)', (string) count($unplaceable));

        if ($unplaceable !== []) {
            $this->components->warn('Re-run meetings:zoom-hosts:preflight after resolving the bookings above. Capacity reservation must not be enabled until it reports no findings.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

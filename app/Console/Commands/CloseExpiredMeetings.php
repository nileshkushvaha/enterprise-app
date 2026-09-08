<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Booking\Contracts\BookingMeetingServiceInterface;
use App\Booking\Enums\MeetingStatus;
use App\Models\BookingMeeting;
use App\Settings\MeetingSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Closes lesson meetings whose join window has passed.
 *
 * A Google Meet space outlives the class held in it: without this
 * sweep a conference left open — or restarted from a link somebody
 * kept — keeps running after the lesson, and on a recording-eligible
 * lesson keeps RECORDING, producing footage of an empty or unrelated
 * room that the platform then stores and bills for.
 *
 * The window closed here is exactly the one
 * BookingMeetingService::joinAvailabilityFor() stops serving the link
 * at (ends_at + meeting_link_visible_after_minutes), so nothing is ever
 * closed while SIRI is still offering it.
 *
 * Bounds and safety:
 *  - candidates are meetings still marked Created whose window ended,
 *    within a bounded look-back so the sweep never trawls all history;
 *  - each meeting is attempted independently — one provider failure
 *    never aborts the rest, and the meeting stays a candidate until it
 *    succeeds or ages out;
 *  - idempotent: a closed meeting records `metadata.closed_at` and
 *    leaves the sweep, and the provider call is itself safe to repeat.
 */
final class CloseExpiredMeetings extends Command
{
    protected $signature = 'meetings:close-expired';

    protected $description = 'Close lesson meetings at the provider once their join window has ended.';

    /**
     * How far back the sweep looks. Long enough to survive a night of
     * failed runs or a provider outage, short enough that it never
     * becomes a scan of every meeting the platform has ever held.
     */
    private const LOOK_BACK_HOURS = 48;

    public function handle(BookingMeetingServiceInterface $meetings, MeetingSettings $settings): int
    {
        if (! $settings->meeting_auto_close_enabled) {
            $this->info('Meeting auto-close is disabled; nothing to do.');

            return self::SUCCESS;
        }

        $now = Carbon::now();
        // The window end is ends_at + visible-after; comparing against
        // ends_at with the same offset keeps the SQL bound in step with
        // the setting instead of hard-coding a second policy here.
        $closesBefore = $now->copy()->subMinutes(max(0, $settings->meeting_link_visible_after_minutes));
        $lookBackFrom = $closesBefore->copy()->subHours(self::LOOK_BACK_HOURS);

        $attempted = 0;
        $closed = 0;

        BookingMeeting::query()
            ->where('status', MeetingStatus::Created)
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [$lookBackFrom, $closesBefore])
            ->whereNull('metadata->closed_at')
            ->with('booking')
            ->orderBy('ends_at')
            ->cursor()
            ->each(function (BookingMeeting $meeting) use ($meetings, &$attempted, &$closed): void {
                $attempted++;

                try {
                    if ($meetings->closeExpiredMeeting($meeting)) {
                        $closed++;
                    }
                } catch (Throwable $e) {
                    // Left unclosed on purpose: it stays a candidate for
                    // the next run until it ages out of the look-back.
                    $this->warn(sprintf('Meeting %s could not be closed: %s', $meeting->id, $e->getMessage()));
                }
            });

        $this->info("Checked {$attempted} finished meeting(s); {$closed} closed at the provider.");

        return self::SUCCESS;
    }
}

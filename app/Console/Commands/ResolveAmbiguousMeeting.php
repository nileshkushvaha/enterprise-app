<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Booking\Contracts\BookingMeetingServiceInterface;
use App\Booking\Exceptions\BookingException;
use App\Booking\Services\BookingMeetingService;
use App\Models\Booking;
use App\Models\BookingMeeting;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * The operator's explicit resolution of a meeting create whose remote
 * outcome is unknown (booking_meetings.metadata.remote_state_unknown).
 *
 * SIRI never guesses here. Automatic reconciliation adopts a remote
 * meeting only when exactly one carries the booking reference; when it
 * finds none or several, the row stays failed and this command is how
 * a person, having looked in the Zoom account, settles it:
 *
 *   --adopt=<zoom meeting id>   that meeting IS the booking's; align and adopt it
 *   --none                      no remote meeting exists; allow a fresh create
 *
 * Both are audited with the acting administrator. Without either flag
 * the command only shows what reconciliation last established.
 */
final class ResolveAmbiguousMeeting extends Command
{
    protected $signature = 'meetings:resolve-ambiguous
        {booking : Booking reference (BK-…) or id}
        {--admin= : Acting administrator (user id or email) — required with --adopt or --none}
        {--adopt= : Provider meeting id to adopt}
        {--none : Declare that no remote meeting exists}
        {--reason= : Why (required with --none)}';

    protected $description = 'Show or resolve an ambiguous meeting creation (remote state unknown) for a booking';

    public function handle(BookingMeetingServiceInterface $meetings): int
    {
        $reference = (string) $this->argument('booking');
        $booking = Booking::query()->where('reference', $reference)->orWhere('id', $reference)->first();

        if ($booking === null) {
            $this->components->error(sprintf('Booking %s not found.', $reference));

            return self::FAILURE;
        }

        $meeting = BookingMeeting::query()->where('booking_id', $booking->id)->first();
        $metadata = $meeting?->metadata ?? [];

        $this->components->twoColumnDetail('Booking', $booking->reference);
        $this->components->twoColumnDetail('Meeting row', $meeting === null ? 'none' : sprintf('%s · %s', $meeting->provider, $meeting->status->value));
        $this->components->twoColumnDetail('Remote state unknown', ($metadata[BookingMeetingService::META_REMOTE_STATE_UNKNOWN] ?? false) ? '<fg=red>YES</>' : 'no');

        if (isset($metadata[BookingMeetingService::META_RECONCILIATION])) {
            $last = $metadata[BookingMeetingService::META_RECONCILIATION];
            $this->components->twoColumnDetail('Last reconciliation', sprintf('%s · exhaustive: %s · candidates: %s · %s', $last['status'] ?? '?', ($last['exhaustive'] ?? false) ? 'yes' : 'no', implode(', ', $last['candidate_ids'] ?? []) ?: 'none', $last['checked_at'] ?? ''));
        }

        if ($meeting?->failure_reason) {
            $this->components->twoColumnDetail('Failure reason', $meeting->failure_reason);
        }

        $adopt = trim((string) $this->option('adopt'));
        $none = (bool) $this->option('none');

        if ($adopt === '' && ! $none) {
            $this->line('Pass --adopt=<meeting id> or --none --reason="…" (with --admin=…) to resolve.');

            return self::SUCCESS;
        }

        if ($adopt !== '' && $none) {
            $this->components->error('Choose either --adopt or --none, not both.');

            return self::FAILURE;
        }

        $adminRef = trim((string) $this->option('admin'));
        $admin = $adminRef === '' ? null : User::query()->where('email', $adminRef)->orWhere('id', $adminRef)->first();

        if ($admin === null) {
            $this->components->error('--admin=<user id or email> is required and must name an existing administrator.');

            return self::FAILURE;
        }

        try {
            $resolved = $none
                ? $meetings->acknowledgeNoRemoteMeeting($booking, $admin, (string) $this->option('reason'))
                : $meetings->adoptRemoteMeeting($booking, $adopt, $admin);
        } catch (BookingException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info($none
            ? sprintf('Recorded: no remote meeting exists for %s. Retry meeting creation from the admin panel when ready.', $booking->reference)
            : sprintf('Adopted remote meeting %s for %s (status %s).', $adopt, $booking->reference, $resolved->status->value));

        return self::SUCCESS;
    }
}

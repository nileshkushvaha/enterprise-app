<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Booking\Contracts\BookingMeetingServiceInterface;
use App\Booking\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Route ONE booking to a specific meeting provider before its meeting
 * exists — the supported way to run a Zoom canary while Google Meet
 * stays the platform default. Sets meeting_provider_intent and, for
 * Zoom, reserves host capacity; the meeting is then created by the
 * normal confirmation path. Refused once a meeting has been created on
 * another provider (that transition is not supported), when the
 * provider is not usable, or when no Zoom host has room.
 */
final class PinBookingMeetingProvider extends Command
{
    protected $signature = 'meetings:pin-provider
        {booking : Booking reference (BK-…) or id}
        {--provider=zoom : manual | google_meet | zoom}
        {--admin= : Acting administrator (user id or email) — required}';

    protected $description = 'Pin one not-yet-provisioned booking to a meeting provider (e.g. a Zoom canary) without changing the default';

    public function handle(BookingMeetingServiceInterface $meetings): int
    {
        $reference = (string) $this->argument('booking');
        $booking = Booking::query()->where('reference', $reference)->orWhere('id', $reference)->first();

        if ($booking === null) {
            $this->error(sprintf('Booking %s not found.', $reference));

            return self::FAILURE;
        }

        $adminRef = trim((string) $this->option('admin'));
        $admin = $adminRef === '' ? null : User::query()->where('email', $adminRef)->orWhere('id', $adminRef)->first();

        if ($admin === null) {
            $this->error('--admin=<user id or email> is required and must name an existing administrator.');

            return self::FAILURE;
        }

        try {
            $pinned = $meetings->pinProvider($booking, (string) $this->option('provider'), $admin);
        } catch (BookingException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Booking %s is pinned to %s. Its meeting will be created there on confirmation (or now, via Admin → Bookings → Create/Update Meeting → %s, if already confirmed).',
            $pinned->reference,
            $pinned->meeting_provider_intent,
            $pinned->meeting_provider_intent,
        ));

        return self::SUCCESS;
    }
}

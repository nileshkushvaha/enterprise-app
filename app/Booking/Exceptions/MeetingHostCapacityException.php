<?php

declare(strict_types=1);

namespace App\Booking\Exceptions;

use Carbon\CarbonImmutable;

/**
 * No platform host can take this booking's occupied interval. Raised
 * INSIDE the booking transaction, so the booking rolls back, nothing is
 * charged, no hold is created and no provider is silently substituted.
 * The message is safe to show to the person booking.
 */
final class MeetingHostCapacityException extends BookingException
{
    public static function exhausted(string $provider, CarbonImmutable $from, CarbonImmutable $until): self
    {
        return new self(sprintf(
            'No %s host is available between %s and %s (UTC). Please choose another time.',
            ucfirst($provider),
            $from->utc()->format('Y-m-d H:i'),
            $until->utc()->format('Y-m-d H:i'),
        ));
    }

    public static function noHostRegistered(string $provider): self
    {
        return new self(sprintf(
            'No %s host is registered for capacity reservation. Register one with meetings:zoom-hosts:register or disable capacity reservation.',
            ucfirst($provider),
        ));
    }

    public static function reservationDisabled(): self
    {
        return new self(
            'Zoom lessons are not being accepted right now: Zoom host capacity reservation is switched off. '
            .'Set the default meeting provider to Google Meet, or enable capacity reservation after a clean preflight.',
        );
    }

    public static function hostUnavailable(string $provider, string $hostReference): self
    {
        return new self(sprintf(
            'The %s host this meeting already lives on (%s) has no capacity at the requested time.',
            ucfirst($provider),
            $hostReference,
        ));
    }
}

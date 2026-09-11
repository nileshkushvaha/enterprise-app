<?php

declare(strict_types=1);

namespace App\Booking\Enums;

/**
 * Only Active rows count against a host's capacity. Released rows are
 * history: why a booking stopped occupying its host is on the row
 * (release_reason), and nothing is ever deleted.
 */
enum MeetingHostReservationStatus: string
{
    case Active = 'active';
    case Released = 'released';
}

<?php

declare(strict_types=1);

namespace App\Booking\DTOs;

use App\Booking\Enums\BookingCheckoutState;
use App\Models\Booking;

/** The fresh booking plus the state the checkout UI should render for it. */
final readonly class BookingCheckoutOutcome
{
    public function __construct(
        public BookingCheckoutState $state,
        public Booking $booking,
    ) {}

    public function confirmationInProgress(): bool
    {
        return $this->state->confirmationInProgress();
    }
}

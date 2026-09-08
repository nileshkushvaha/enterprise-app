<?php

declare(strict_types=1);

namespace App\Booking\DTOs;

use App\Models\Booking;
use Illuminate\Support\Collection;

/**
 * The outcome of settling a schedule's reserved classes in one go.
 *
 * Failures are reported per class rather than collapsed into a single
 * "it did not work". Each class is its own reservation with its own
 * price, so one of them losing its slot between the quote and the
 * settlement says nothing about the others — and the student needs to
 * know exactly which class still needs attention.
 */
final readonly class SeriesPrepaymentResult
{
    /**
     * @param  Collection<int, Booking>  $paid
     * @param  array<string, string>  $failures  booking reference => reason
     */
    public function __construct(
        public Collection $paid,
        public array $failures,
        /** Money left in the wallet afterwards, in minor units. */
        public int $remainingWalletMinor = 0,
    ) {}

    public function allPaid(): bool
    {
        return $this->failures === [];
    }

    public function paidCount(): int
    {
        return $this->paid->count();
    }
}

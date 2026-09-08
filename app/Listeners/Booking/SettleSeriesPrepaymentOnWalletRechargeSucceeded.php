<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Booking\Services\BookingSeriesPrepaymentService;
use App\Models\BookingSeries;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Finishes the payment the student started.
 *
 * A student who chose "pay for all my classes" is topped up for exactly
 * that amount and then has the classes settled from the balance. Doing
 * the second half here rather than on the browser's return is what makes
 * it reliable: closing the tab at the gateway, a flaky redirect, or a
 * webhook arriving before the redirect all end the same way, instead of
 * leaving classes unpaid until their reservations lapse.
 *
 * This is NOT automatic charging. It runs only for a recharge the
 * student raised through the prepayment flow, for the exact classes
 * named on it, for the exact amount they were quoted. An ordinary
 * top-up carries no such marker and is never spent on their behalf.
 */
final class SettleSeriesPrepaymentOnWalletRechargeSucceeded implements ShouldQueue
{
    public string $queue = 'notifications';

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(
        private readonly BookingSeriesPrepaymentService $prepayments,
    ) {}

    public function handle(object $event): void
    {
        $metadata = $event->recharge->metadata ?? [];

        if (($metadata['purpose'] ?? null) !== BookingSeriesPrepaymentService::PURPOSE) {
            return;
        }

        $series = BookingSeries::query()->find($metadata['booking_series_id'] ?? null);
        $student = $event->recharge->user;

        if ($series === null || $student === null) {
            return;
        }

        // Re-quoted from the live rows, never from the recharge: between
        // the top-up and now, a class may have been cancelled or its
        // reservation may have lapsed. Paying for what is actually
        // outstanding is the only safe reading.
        $result = $this->prepayments->settleFromWallet($series, $student);

        if ($result->allPaid()) {
            return;
        }

        // Ids and counts only — never the student's identity, the
        // amounts, or anything about the payment instrument.
        Log::warning('Some classes could not be settled from a schedule prepayment.', [
            'booking_series_id' => $series->id,
            'paid' => $result->paidCount(),
            'failed' => count($result->failures),
        ]);
    }
}

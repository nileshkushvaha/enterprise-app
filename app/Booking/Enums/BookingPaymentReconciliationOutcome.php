<?php

declare(strict_types=1);

namespace App\Booking\Enums;

/**
 * What one reconciliation pass over a booking payment established.
 * Returned to the caller (checkout completion, the poll, the sweep,
 * the admin retry) so each can react — the checkout UI in particular
 * must tell a student "waiting for capture" apart from "we could not
 * reach the provider", and neither apart from "you need to pay".
 */
enum BookingPaymentReconciliationOutcome: string
{
    /** This pass settled the booking. */
    case Settled = 'settled';

    /** Already settled before this pass — nothing to do. */
    case AlreadySettled = 'already_settled';

    /** Provider money recorded earlier, local settlement finished on THIS pass. */
    case Recovered = 'recovered';

    /** The provider has the payment in flight (authorised / attempted) and has not captured. */
    case AwaitingCapture = 'awaiting_capture';

    /** The provider reports no payment against the attempt. */
    case NotPaid = 'not_paid';

    /** The provider reports a definitive failure; the attempt was marked Failed. */
    case Failed = 'failed';

    /** The provider could not be asked. An incident is recorded; nothing else changed. */
    case ProviderUnreachable = 'provider_unreachable';

    /** Something an operator must look at (amount/currency mismatch, refunded at provider, local settlement failed). */
    case NeedsAttention = 'needs_attention';

    /** No provider attempt exists to ask about. */
    case NoAttempt = 'no_attempt';

    public function settledBooking(): bool
    {
        return match ($this) {
            self::Settled, self::AlreadySettled, self::Recovered => true,
            default => false,
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Payments\Enums;

/**
 * What an authenticated provider look-up established about one payment
 * attempt. Deliberately richer than "paid or not": the two rules that
 * matter downstream are that silence is never payment (Unreachable is
 * an outage, not a fact) and that an authorised-but-uncaptured payment
 * is a real, distinct state a student is waiting on — not a failure
 * and not a success.
 */
enum PaymentVerificationOutcome: string
{
    /** The provider holds the money. The carried event settles. */
    case Captured = 'captured';

    /** Authorised (or still in progress) and not yet captured — wait, never charge again. */
    case AwaitingCapture = 'awaiting_capture';

    /** The provider answered and reports no payment against this attempt. */
    case NotPaid = 'not_paid';

    /** The provider reports a definitive failure. The carried event records it. */
    case Failed = 'failed';

    /** The provider could not be contacted or returned an unusable answer. */
    case Unreachable = 'unreachable';

    /**
     * The provider answered with a state this platform does not settle
     * on its own (for example a payment already refunded at the
     * provider). An operator must look.
     */
    case NeedsAttention = 'needs_attention';

    /** Nothing to ask about: the attempt was never handed to a provider. */
    case Unverifiable = 'unverifiable';
}

<?php

declare(strict_types=1);

namespace App\Booking\Enums;

/**
 * What the student should be told about a hosted-checkout payment,
 * derived on the server from the attempt ledger and the incident
 * queue — never from a browser flag, so it survives a reload, a new
 * device and a re-login.
 *
 * Every state except Payable means "money may already have moved: do
 * not offer a Pay button".
 */
enum BookingCheckoutState: string
{
    /** Provider capture verified and the booking settled. */
    case Confirmed = 'confirmed';

    /** Callback verified; the provider has not yet reported capture. */
    case AwaitingCapture = 'awaiting_capture';

    /** Callback verified; the provider could not be asked just now. Automatic re-checks continue. */
    case ProviderUnreachable = 'provider_unreachable';

    /** Callback verified; something needs an operator (mismatch, unusual provider state, local settlement failed). */
    case NeedsAttention = 'needs_attention';

    /** The provider reported the attempt as failed; the student may pay again. */
    case Failed = 'failed';

    /** No verified checkout is in flight: the booking is payable and a Pay button is correct. */
    case Payable = 'payable';

    /** A verified checkout exists and has not resolved — show the confirming state, hide Pay. */
    public function confirmationInProgress(): bool
    {
        return match ($this) {
            self::AwaitingCapture, self::ProviderUnreachable, self::NeedsAttention => true,
            default => false,
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::Confirmed => 'Payment confirmed',
            self::AwaitingCapture => 'Payment submitted successfully',
            self::ProviderUnreachable => 'Payment submitted successfully',
            self::NeedsAttention => 'Payment needs verification',
            self::Failed => 'Payment failed',
            self::Payable => 'Complete your payment',
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::Confirmed => 'Your lesson has been booked successfully.',
            self::AwaitingCapture => "We're securely confirming your payment with Razorpay. Please don't make another payment.",
            self::ProviderUnreachable => "We're having trouble confirming the transaction right now. Don't make another payment — we'll keep checking automatically.",
            self::NeedsAttention => "Your payment needs additional verification. Please don't pay again. Our system is checking the transaction.",
            self::Failed => 'The payment did not go through. You can try again.',
            self::Payable => 'Your lesson time is reserved while you complete payment.',
        };
    }

    /** Shown once confirmation has taken longer than expected. */
    public function delayedMessage(): ?string
    {
        return match ($this) {
            self::AwaitingCapture, self::ProviderUnreachable => "Your transaction was submitted successfully and is still being confirmed by Razorpay. You don't need to pay again. You can safely leave this page; your booking will update automatically and you'll receive an email.",
            default => null,
        };
    }
}

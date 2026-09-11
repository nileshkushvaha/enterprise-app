<?php

declare(strict_types=1);

namespace App\Booking\Contracts;

use App\Booking\DTOs\BookingCheckoutOutcome;
use App\Booking\Exceptions\BookingException;
use App\Booking\Exceptions\InvalidPaymentWebhookException;
use App\Models\Booking;

/**
 * Completes a hosted-checkout payment for a booking the moment the
 * browser reports success — the single entry point the checkout UI
 * calls, and the only place the browser callback meets settlement.
 *
 * Settlement here is never based on the browser. The callback proves
 * authenticity (signature) and identity (this order, this booking);
 * the money is then confirmed by asking the provider directly over the
 * authenticated API, and settled through the same path a signed
 * webhook uses. The webhook and the reconciliation sweep remain
 * independent settlement sources for the cases where the browser never
 * comes back.
 */
interface BookingCheckoutCompletionServiceInterface
{
    /**
     * Verify the Razorpay Checkout.js success callback for this booking,
     * confirm the specific payment with Razorpay, and settle it when
     * Razorpay reports it captured. The outcome carries the fresh
     * booking and the state the UI must render: Confirmed when
     * settlement happened; AwaitingCapture / ProviderUnreachable /
     * NeedsAttention when the money may have moved but is not yet
     * proven — in all of which a second Pay button is wrong.
     *
     * @throws InvalidPaymentWebhookException when the callback signature is invalid
     * @throws BookingException when the order does not belong to this booking or checkout is unavailable
     */
    public function completeRazorpayCheckout(Booking $booking, string $orderId, string $paymentId, string $signature): BookingCheckoutOutcome;

    /**
     * Re-confirm a booking whose checkout is still being confirmed — the
     * poll behind the confirming state. Throttled per obligation so a
     * polling page does not become a request storm against the
     * provider; the current state is returned either way.
     */
    public function refreshPendingPayment(Booking $booking): BookingCheckoutOutcome;

    /**
     * The state to render for this booking right now, derived from the
     * attempt ledger and incident queue alone — no provider call, no
     * browser flag. This is what a reloaded page, a second device or a
     * fresh login sees, so a verified checkout can never fall back to
     * "Pay now" because a Livewire property reset.
     */
    public function currentState(Booking $booking): BookingCheckoutOutcome;
}

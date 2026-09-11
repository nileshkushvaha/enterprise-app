<?php

declare(strict_types=1);

namespace App\Booking\Contracts;

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
     * confirm the order with Razorpay, and settle it when Razorpay
     * reports it paid. Returns the fresh booking: `paid`/confirmed when
     * settlement happened, still payable when the provider has not
     * captured yet or could not be reached.
     *
     * @throws InvalidPaymentWebhookException when the callback signature is invalid
     * @throws BookingException when the order does not belong to this booking or checkout is unavailable
     */
    public function completeRazorpayCheckout(Booking $booking, string $orderId, string $paymentId, string $signature): Booking;

    /**
     * Re-confirm a booking that is still payable with its provider — the
     * poll behind the "confirming your payment" state. Throttled per
     * obligation so a polling page does not become a request storm
     * against the provider; a fresh read of the booking is returned
     * either way.
     */
    public function refreshPendingPayment(Booking $booking): Booking;
}

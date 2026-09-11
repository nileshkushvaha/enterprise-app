<?php

declare(strict_types=1);

namespace App\Booking\Services;

use App\Booking\Contracts\BookingCheckoutCompletionServiceInterface;
use App\Booking\Contracts\BookingPaymentReconciliationServiceInterface;
use App\Booking\Payments\RazorpayPaymentProvider;
use App\Models\Booking;
use App\Models\BookingPayment;

/**
 * Hosted-checkout completion for bookings.
 *
 * WHY THIS EXISTS. Razorpay's Checkout.js success handler fires on
 * authorization and is browser-supplied, so it can never be the thing
 * that marks money as received (an earlier version did exactly that
 * and produced confirmed lessons with no receipt). But treating the
 * callback as nothing more than a hint, and leaving settlement to a
 * webhook, meant a student who had just paid watched a "confirming"
 * screen for as long as the webhook took — or forever, when the
 * webhook was misconfigured. Both were wrong.
 *
 * WHAT IT DOES. The industry-standard sequence, in one place:
 *
 *   1. verify the callback — signature over order_id|payment_id, and the
 *      order belongs to THIS booking (RazorpayPaymentProvider::verifyCheckout);
 *   2. confirm with the provider — an authenticated GET of the order from
 *      Razorpay; only `status: paid` counts (PaymentAttemptVerifier);
 *   3. settle — through BookingPaymentSettlementService, the one path the
 *      webhook also uses: attempt captured, obligation captured, booking
 *      confirmed, receipt and notifications.
 *
 * Steps 2–3 ARE the reconciliation service's per-attempt pass, reused
 * rather than reimplemented, so there is exactly one implementation of
 * "ask the provider and settle". The webhook stays the settlement source
 * when the browser never returns; the scheduled sweep stays the backstop
 * for both.
 *
 * WHAT IT NEVER DOES. Settle on the browser's word, settle an
 * authorized-but-uncaptured payment, or raise when the provider is
 * unreachable — that case leaves the booking payable, records a
 * reconciliation issue, and the UI keeps polling.
 */
final class BookingCheckoutCompletionService implements BookingCheckoutCompletionServiceInterface
{
    /**
     * Minimum spacing between provider look-ups for one pending
     * obligation while a page polls. Local reads happen every poll;
     * the provider is asked at most this often.
     */
    public const int PROVIDER_RECHECK_SECONDS = 15;

    public function __construct(
        private readonly RazorpayPaymentProvider $razorpay,
        private readonly BookingPaymentReconciliationServiceInterface $reconciliation,
    ) {}

    public function completeRazorpayCheckout(Booking $booking, string $orderId, string $paymentId, string $signature): Booking
    {
        // Authenticity + identity. Throws on a forged signature or an
        // order that is not this booking's; records the payment id.
        $obligation = $this->razorpay->verifyCheckout($booking, $orderId, $paymentId, $signature);

        // Authenticated confirmation + settlement, or nothing.
        $this->reconciliation->reconcileAttempt($obligation);

        return $booking->refresh();
    }

    public function refreshPendingPayment(Booking $booking): Booking
    {
        $booking->refresh();

        if (! $booking->payment_status->isPayable() || $booking->status->isTerminal()) {
            return $booking;
        }

        $obligation = BookingPayment::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('created_at')
            ->first();

        if ($obligation === null) {
            return $booking;
        }

        // reconcileOne() stamps last_synced_at on every pass, which makes
        // it the throttle: a page polling every few seconds re-reads the
        // booking locally and asks Razorpay only once per window.
        $lastSynced = $obligation->last_synced_at;

        if ($lastSynced !== null && $lastSynced->addSeconds(self::PROVIDER_RECHECK_SECONDS)->isFuture()) {
            return $booking;
        }

        $this->reconciliation->reconcileAttempt($obligation);

        return $booking->refresh();
    }
}

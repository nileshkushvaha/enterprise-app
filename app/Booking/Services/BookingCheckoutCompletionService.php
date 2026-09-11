<?php

declare(strict_types=1);

namespace App\Booking\Services;

use App\Booking\Contracts\BookingCheckoutCompletionServiceInterface;
use App\Booking\Contracts\BookingPaymentReconciliationServiceInterface;
use App\Booking\DTOs\BookingCheckoutOutcome;
use App\Booking\Enums\BookingCheckoutState;
use App\Booking\Enums\BookingPaymentReconciliationIssueType;
use App\Booking\Enums\BookingPaymentReconciliationOutcome;
use App\Booking\Enums\BookingPaymentStatus;
use App\Booking\Payments\RazorpayPaymentProvider;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\BookingPaymentReconciliationIssue;
use App\Models\Payment;
use App\Payments\Enums\PaymentStatus;
use App\Services\AuditTrailService;

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
 *   2. confirm with the provider — an authenticated GET of the specific
 *      PAYMENT from Razorpay; only `status: captured` counts
 *      (PaymentAttemptVerifier);
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
 * reconciliation issue, and the UI keeps polling with an honest message.
 */
final class BookingCheckoutCompletionService implements BookingCheckoutCompletionServiceInterface
{
    /**
     * Minimum spacing between provider look-ups for one pending
     * obligation while a page polls. Local reads happen every poll;
     * the provider is asked at most this often.
     */
    public const int PROVIDER_RECHECK_SECONDS = 15;

    private const string LOG_NAME = 'payments';

    public function __construct(
        private readonly RazorpayPaymentProvider $razorpay,
        private readonly BookingPaymentReconciliationServiceInterface $reconciliation,
        private readonly AuditTrailService $audit,
    ) {}

    public function completeRazorpayCheckout(Booking $booking, string $orderId, string $paymentId, string $signature): BookingCheckoutOutcome
    {
        // Authenticity + identity. Throws on a forged signature or an
        // order that is not this booking's; records the payment id.
        $obligation = $this->razorpay->verifyCheckout($booking, $orderId, $paymentId, $signature);

        $this->audit->logSystem(self::LOG_NAME, 'booking_checkout_verified', sprintf('Razorpay checkout callback verified for booking %s.', (string) $booking->reference), $booking, [
            'source' => 'callback',
            'provider' => 'razorpay',
            'booking_reference' => $booking->reference,
            'provider_order_id' => $orderId,
            'provider_payment_id' => $paymentId,
        ]);

        // Authenticated confirmation + settlement, or nothing.
        $outcome = $this->reconciliation->reconcileNow($obligation, 'callback');

        return $this->outcomeAfter($booking, $outcome);
    }

    public function refreshPendingPayment(Booking $booking): BookingCheckoutOutcome
    {
        $current = $this->currentState($booking);

        if (! $current->confirmationInProgress()) {
            return $current;
        }

        $obligation = $this->latestObligation($booking);

        if ($obligation === null) {
            return $current;
        }

        // reconcileNow() stamps last_synced_at on every pass, which makes
        // it the throttle: a page polling every few seconds re-reads the
        // booking locally and asks Razorpay only once per window.
        $lastSynced = $obligation->last_synced_at;

        if ($lastSynced !== null && $lastSynced->addSeconds(self::PROVIDER_RECHECK_SECONDS)->isFuture()) {
            return $current;
        }

        $outcome = $this->reconciliation->reconcileNow($obligation, 'poll');

        return $this->outcomeAfter($booking, $outcome);
    }

    public function currentState(Booking $booking): BookingCheckoutOutcome
    {
        $booking->refresh();

        if ($booking->payment_status === BookingPaymentStatus::Paid) {
            return new BookingCheckoutOutcome(BookingCheckoutState::Confirmed, $booking);
        }

        if ($booking->status->isTerminal() || ! $booking->payment_status->isPayable()) {
            return new BookingCheckoutOutcome(BookingCheckoutState::Payable, $booking);
        }

        $obligation = $this->latestObligation($booking);

        if ($obligation === null) {
            return new BookingCheckoutOutcome(BookingCheckoutState::Payable, $booking);
        }

        // A checkout is "in flight" when an attempt carries the provider
        // payment id the verified callback recorded and has not resolved
        // — or when the provider's money is already on record (Paid) and
        // only the local half is outstanding. Either way the student
        // must not be offered a second Pay button.
        $inFlight = Payment::query()
            ->forPayable(BookingPayment::PAYABLE_TYPE, (string) $obligation->getKey())
            ->where(fn ($q) => $q
                ->where(fn ($open) => $open->open()->whereNotNull('provider_payment_id'))
                ->orWhere('status', PaymentStatus::Paid->value))
            ->orderByDesc('created_at')
            ->first();

        if ($inFlight === null) {
            return new BookingCheckoutOutcome(BookingCheckoutState::Payable, $booking);
        }

        if ($inFlight->status === PaymentStatus::Paid) {
            return new BookingCheckoutOutcome(BookingCheckoutState::NeedsAttention, $booking);
        }

        $openIssueTypes = BookingPaymentReconciliationIssue::query()
            ->open()
            ->where('booking_payment_id', $obligation->id)
            ->pluck('type')
            ->map(fn ($type) => $type instanceof BookingPaymentReconciliationIssueType ? $type : BookingPaymentReconciliationIssueType::from((string) $type));

        if ($openIssueTypes->contains(fn (BookingPaymentReconciliationIssueType $type): bool => $type !== BookingPaymentReconciliationIssueType::ProviderUnavailable && $type !== BookingPaymentReconciliationIssueType::StaleProcessing)) {
            return new BookingCheckoutOutcome(BookingCheckoutState::NeedsAttention, $booking);
        }

        if ($openIssueTypes->contains(BookingPaymentReconciliationIssueType::ProviderUnavailable)) {
            return new BookingCheckoutOutcome(BookingCheckoutState::ProviderUnreachable, $booking);
        }

        return new BookingCheckoutOutcome(BookingCheckoutState::AwaitingCapture, $booking);
    }

    /**
     * The state after a pass. Derived from the database again rather
     * than mapped from the outcome, so it is exactly what a reloaded
     * page would compute — with one exception: a definitive provider
     * failure is reported as Failed so the student is told to retry
     * rather than shown a Pay button with no explanation.
     */
    private function outcomeAfter(Booking $booking, BookingPaymentReconciliationOutcome $outcome): BookingCheckoutOutcome
    {
        $current = $this->currentState($booking);

        if ($outcome === BookingPaymentReconciliationOutcome::Failed && $current->state === BookingCheckoutState::Payable) {
            return new BookingCheckoutOutcome(BookingCheckoutState::Failed, $current->booking);
        }

        return $current;
    }

    private function latestObligation(Booking $booking): ?BookingPayment
    {
        return BookingPayment::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('created_at')
            ->first();
    }
}

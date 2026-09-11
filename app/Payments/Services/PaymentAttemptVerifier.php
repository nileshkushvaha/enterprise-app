<?php

declare(strict_types=1);

namespace App\Payments\Services;

use App\Booking\Contracts\RazorpayGatewayClient;
use App\Booking\Contracts\StripeGatewayClient;
use App\Booking\Exceptions\GatewayRequestException;
use App\Models\Payment;
use App\Payments\DTOs\PaymentVerificationResult;
use App\Payments\DTOs\VerifiedPaymentEvent;
use App\Payments\Enums\PaymentEventType;
use App\Payments\Enums\PaymentVerificationOutcome;
use App\Services\Payment\PaymentWebhookSignatureService;
use App\Settings\PaymentGatewaySettings;

/**
 * Asks a provider whether it actually holds the money for an attempt.
 *
 * The reconciliation counterpart to PaymentWebhookEventParser: a webhook
 * is evidence that arrives, this is evidence we go and fetch. Both
 * produce the same VerifiedPaymentEvent, so every domain settles from
 * one shape of proof regardless of how the proof reached us.
 *
 * One canonical implementation serves every payable. Booking and package
 * reconciliation must not each decide what "Razorpay says paid" means —
 * that is precisely the kind of divergence that lets two domains
 * disagree about whether money exists.
 *
 * Two rules this class exists to keep:
 *
 *   Silence is never payment. A provider we cannot reach reports
 *   `$reachable = false`, which is categorically different from a
 *   provider that answered "not paid". Callers must tell them apart:
 *   one is an outage, the other is a fact.
 *
 *   The event carries what the PROVIDER reported, not what we stored.
 *   This class previously rebuilt the event from the attempt's own
 *   amount and currency, on the stated reasoning that echoing the
 *   provider's figures back would make settlement's checks
 *   self-confirming. That reasoning is inverted: settlement compares
 *   the event against the attempt, so feeding it the attempt's own
 *   values compared the row with itself and the mismatch guards could
 *   never fire on the reconciliation path at all. Feeding it the
 *   provider's values is what makes those guards mean anything.
 *
 *   A provider that confirms payment without reporting an amount or
 *   currency yields nulls, which settlement treats as "unproven" and
 *   skips — never as agreement.
 */
final class PaymentAttemptVerifier
{
    public function __construct(
        private readonly RazorpayGatewayClient $razorpay,
        private readonly StripeGatewayClient $stripe,
        private readonly PaymentGatewaySettings $gatewaySettings,
    ) {}

    /**
     * Legacy shape kept for the package and wallet reconciliation
     * sweeps: the success event, or null; `$reachable` false on an
     * outage. Asks about the ORDER/INTENT only, exactly as before, so
     * those domains' behaviour is unchanged by the booking work.
     *
     * @param  bool  $reachable  set to false when the provider could not be contacted
     * @return VerifiedPaymentEvent|null the proof of payment, or null when the provider does not confirm one
     */
    public function confirmedPayment(Payment $payment, bool &$reachable): ?VerifiedPaymentEvent
    {
        $result = $this->verify($payment, preferPaymentLookup: false);

        $reachable = $result->isReachable();

        return $result->outcome === PaymentVerificationOutcome::Captured ? $result->event : null;
    }

    /**
     * The full answer. When the attempt already knows WHICH provider
     * payment the customer completed (the checkout callback recorded
     * it), Razorpay is asked about that payment: it is the entity that
     * says captured/authorized/failed and reports the captured amount.
     * Without a payment id the order is the only reference we hold and
     * is asked instead.
     */
    public function verify(Payment $payment, bool $preferPaymentLookup = true): PaymentVerificationResult
    {
        if ($payment->provider_order_id === null) {
            return PaymentVerificationResult::of(PaymentVerificationOutcome::Unverifiable);
        }

        return match ($payment->provider) {
            'razorpay' => $preferPaymentLookup && $payment->provider_payment_id !== null
                ? $this->razorpayPayment($payment)
                : $this->razorpayOrder($payment),
            'stripe' => $this->stripeIntent($payment),
            default => PaymentVerificationResult::of(PaymentVerificationOutcome::Unverifiable),
        };
    }

    /** Razorpay payment statuses: created, authorized, captured, refunded, failed. */
    private function razorpayPayment(Payment $payment): PaymentVerificationResult
    {
        try {
            $entity = $this->razorpay->fetchPayment(
                (string) $this->gatewaySettings->razorpay_key_id,
                (string) PaymentWebhookSignatureService::decryptSecret($this->gatewaySettings, 'razorpay_key_secret'),
                (string) $payment->provider_payment_id,
            );
        } catch (GatewayRequestException) {
            return PaymentVerificationResult::of(PaymentVerificationOutcome::Unreachable, providerEntity: 'payment');
        }

        $status = (string) ($entity['status'] ?? '');

        // The payment must be the one for OUR order. A payment id that
        // resolves to a different order is not evidence for this attempt.
        $orderId = $entity['order_id'] ?? null;

        if (is_string($orderId) && $orderId !== '' && $orderId !== (string) $payment->provider_order_id) {
            return PaymentVerificationResult::of(PaymentVerificationOutcome::NeedsAttention, $status, 'payment');
        }

        return match ($status) {
            'captured' => new PaymentVerificationResult(
                PaymentVerificationOutcome::Captured,
                $this->succeeded($payment, isset($entity['amount']) ? (int) $entity['amount'] : null, isset($entity['currency']) ? strtoupper((string) $entity['currency']) : null),
                $status,
                'payment',
            ),
            'authorized', 'created' => PaymentVerificationResult::of(PaymentVerificationOutcome::AwaitingCapture, $status, 'payment'),
            'failed' => new PaymentVerificationResult(
                PaymentVerificationOutcome::Failed,
                $this->failed($payment, isset($entity['error_description']) ? (string) $entity['error_description'] : null),
                $status,
                'payment',
            ),
            default => PaymentVerificationResult::of(PaymentVerificationOutcome::NeedsAttention, $status !== '' ? $status : null, 'payment'),
        };
    }

    /** Razorpay order statuses: created, attempted, paid. */
    private function razorpayOrder(Payment $payment): PaymentVerificationResult
    {
        try {
            $order = $this->razorpay->fetchOrder(
                (string) $this->gatewaySettings->razorpay_key_id,
                (string) PaymentWebhookSignatureService::decryptSecret($this->gatewaySettings, 'razorpay_key_secret'),
                (string) $payment->provider_order_id,
            );
        } catch (GatewayRequestException) {
            // Unreachable, NOT unpaid. The caller needs to tell those
            // apart; silence is still never treated as payment.
            return PaymentVerificationResult::of(PaymentVerificationOutcome::Unreachable, providerEntity: 'order');
        }

        $status = (string) ($order['status'] ?? '');

        return match ($status) {
            // The order body carries both figures; discarding them is what
            // let reconciliation settle without checking what was collected.
            'paid' => new PaymentVerificationResult(
                PaymentVerificationOutcome::Captured,
                $this->succeeded($payment, isset($order['amount']) ? (int) $order['amount'] : null, isset($order['currency']) ? strtoupper((string) $order['currency']) : null),
                $status,
                'order',
            ),
            'attempted' => PaymentVerificationResult::of(PaymentVerificationOutcome::AwaitingCapture, $status, 'order'),
            'created' => PaymentVerificationResult::of(PaymentVerificationOutcome::NotPaid, $status, 'order'),
            default => PaymentVerificationResult::of(PaymentVerificationOutcome::NeedsAttention, $status !== '' ? $status : null, 'order'),
        };
    }

    private function stripeIntent(Payment $payment): PaymentVerificationResult
    {
        try {
            $intent = $this->stripe->retrievePaymentIntent(
                (string) PaymentWebhookSignatureService::decryptSecret($this->gatewaySettings, 'stripe_secret_key'),
                (string) $payment->provider_order_id,
            );
        } catch (GatewayRequestException) {
            return PaymentVerificationResult::of(PaymentVerificationOutcome::Unreachable, providerEntity: 'payment_intent');
        }

        $status = (string) ($intent['status'] ?? '');

        return match ($status) {
            // `amount_received` is Stripe's authoritative captured amount;
            // `amount` is only what was requested.
            'succeeded' => new PaymentVerificationResult(
                PaymentVerificationOutcome::Captured,
                $this->succeeded($payment, isset($intent['amount_received']) ? (int) $intent['amount_received'] : null, isset($intent['currency']) ? strtoupper((string) $intent['currency']) : null),
                $status,
                'payment_intent',
            ),
            'processing', 'requires_capture' => PaymentVerificationResult::of(PaymentVerificationOutcome::AwaitingCapture, $status, 'payment_intent'),
            'canceled' => PaymentVerificationResult::of(PaymentVerificationOutcome::NotPaid, $status, 'payment_intent'),
            default => PaymentVerificationResult::of(PaymentVerificationOutcome::NotPaid, $status !== '' ? $status : null, 'payment_intent'),
        };
    }

    private function succeeded(Payment $payment, ?int $amountMinor, ?string $currencyCode): VerifiedPaymentEvent
    {
        return VerifiedPaymentEvent::reconciled(
            provider: (string) $payment->provider,
            type: PaymentEventType::Succeeded,
            reference: $payment->idempotency_key,
            providerOrderId: $payment->provider_order_id,
            providerPaymentId: $payment->provider_payment_id,
            amountMinor: $amountMinor,
            currencyCode: $currencyCode,
        );
    }

    private function failed(Payment $payment, ?string $reason): VerifiedPaymentEvent
    {
        return new VerifiedPaymentEvent(
            provider: (string) $payment->provider,
            type: PaymentEventType::Failed,
            reference: $payment->idempotency_key,
            providerOrderId: $payment->provider_order_id,
            providerPaymentId: $payment->provider_payment_id,
            occurredAt: now(),
            reason: $reason ?? 'Provider reported the payment as failed.',
            source: 'reconciliation',
        );
    }
}

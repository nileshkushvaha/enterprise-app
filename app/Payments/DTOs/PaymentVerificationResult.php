<?php

declare(strict_types=1);

namespace App\Payments\DTOs;

use App\Payments\Enums\PaymentVerificationOutcome;

/**
 * The answer to "does the provider hold this attempt's money?", with
 * the evidence attached when there is any.
 *
 * `event` is present only for Captured and Failed — the two outcomes
 * that settle something — and is the same VerifiedPaymentEvent a
 * signed webhook produces, so settlement never learns which route the
 * proof took. `providerStatus` is the provider's own status word
 * (e.g. Razorpay `authorized`), safe to audit; nothing else from the
 * provider body is carried.
 */
final readonly class PaymentVerificationResult
{
    public function __construct(
        public PaymentVerificationOutcome $outcome,
        public ?VerifiedPaymentEvent $event = null,
        public ?string $providerStatus = null,
        public ?string $providerEntity = null,
    ) {}

    public static function of(PaymentVerificationOutcome $outcome, ?string $providerStatus = null, ?string $providerEntity = null): self
    {
        return new self($outcome, null, $providerStatus, $providerEntity);
    }

    public function isReachable(): bool
    {
        return $this->outcome !== PaymentVerificationOutcome::Unreachable;
    }
}

<?php

declare(strict_types=1);

namespace App\Booking\Services;

use App\Booking\Contracts\BookingPaymentReconciliationServiceInterface;
use App\Booking\Contracts\BookingPaymentServiceInterface;
use App\Booking\Enums\BookingPaymentReconciliationIssueStatus;
use App\Booking\Enums\BookingPaymentReconciliationIssueType;
use App\Booking\Enums\BookingPaymentReconciliationOutcome;
use App\Booking\Enums\BookingPaymentReconciliationSeverity;
use App\Booking\Enums\BookingPaymentRecordStatus;
use App\Booking\Enums\BookingPaymentStatus;
use App\Booking\Exceptions\BookingException;
use App\Models\BookingPayment;
use App\Models\BookingPaymentReconciliationIssue;
use App\Models\Payment;
use App\Models\User;
use App\Payments\DTOs\PaymentVerificationResult;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\PaymentVerificationOutcome;
use App\Payments\Services\PaymentAttemptVerifier;
use App\Services\AuditTrailService;
use App\Settings\PaymentGatewaySettings;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Owns the collection-side reconciliation sweep and issue queue — the
 * provider-neutral counterpart to InstructorPayoutReconciliationService,
 * deliberately reusing the exact same shape (chunked reconcileDue(),
 * DB-deduplicated issues, idempotent finalization) but never sharing
 * code or a table with the payout domain. Every state transition it
 * makes goes through BookingPaymentService::applyProviderStatus() —
 * reconciliation never applies a financial effect through a separate
 * code path than the webhook handler does.
 */
final class BookingPaymentReconciliationService implements BookingPaymentReconciliationServiceInterface
{
    private const string LOG_NAME = 'payments';

    public function __construct(
        private readonly BookingPaymentServiceInterface $payments,
        private readonly PaymentProviderResolver $providers,
        private readonly PaymentGatewaySettings $settings,
        private readonly AuditTrailService $audit,
        private readonly PaymentAttemptVerifier $verifier,
        private readonly BookingPaymentSettlementService $settlement,
    ) {}

    /**
     * How many reconciliation windows an attempt may be ignored for
     * before it stops being "in flight" and becomes operator-visible.
     * Derived from the existing sweep cadence, never a second timeout.
     */
    private const int STALE_WINDOW_MULTIPLIER = 6;

    public function reconcileDue(int $limit = 200): int
    {
        if (! $this->settings->booking_payment_reconciliation_enabled) {
            return 0;
        }

        $cutoff = now()->subMinutes(max(1, $this->settings->booking_payment_unknown_timeout_minutes));

        $payments = BookingPayment::query()
            ->reconciliationDue($cutoff)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $examined = 0;
        $outcomes = [];

        foreach ($payments as $payment) {
            $outcome = $this->reconcileOne($payment, 'reconciliation', $cutoff);
            $outcomes[$outcome->value] = ($outcomes[$outcome->value] ?? 0) + 1;
            $examined++;
        }

        if ($examined > 0) {
            // A sweep that examined nothing is the healthy steady state
            // and not worth a row every five minutes; one that did work
            // is the record an operator needs when asking "when did the
            // backstop last act, and what did it find?".
            $this->audit->logSystem(self::LOG_NAME, 'booking_reconciliation_completed', sprintf('Booking payment reconciliation examined %d attempt(s).', $examined), null, ['examined' => $examined, 'outcomes' => $outcomes]);
        }

        return $examined;
    }

    public function reconcileAttempt(BookingPayment $payment): BookingPayment
    {
        $this->reconcileOne($payment, 'admin_retry');

        return BookingPayment::query()->whereKey($payment->id)->firstOrFail();
    }

    public function reconcileNow(BookingPayment $payment, string $source = 'reconciliation'): BookingPaymentReconciliationOutcome
    {
        return $this->reconcileOne($payment, $source);
    }

    /**
     * Reconciles a booking obligation from its external attempt ledger.
     *
     * Provider identity lives on the Payment attempt now, not on the
     * obligation, so this polls the attempt. Reconciliation never
     * creates an attempt and never initializes a provider order — it
     * only asks about money that may already have moved. A student can
     * never be charged by a sweep.
     */
    private function reconcileOne(BookingPayment $payment, string $source, ?CarbonInterface $cutoff = null): BookingPaymentReconciliationOutcome
    {
        $cutoff ??= now()->subMinutes(max(1, $this->settings->booking_payment_unknown_timeout_minutes));

        $attempt = $this->latestAttemptFor($payment);

        if ($attempt === null) {
            $payment->forceFill(['last_synced_at' => now()])->save();

            return BookingPaymentReconciliationOutcome::NoAttempt;
        }

        if ($attempt->status === PaymentStatus::Paid) {
            // The provider's money is on record. Normally the booking
            // was settled in the same breath; when it was not (a local
            // failure after the attempt transition), this is the state
            // that used to be permanent — every pass returned here and
            // no button could repair it. Finish the local half instead.
            $outcome = $this->finishIncompleteLocalSettlement($payment, $attempt, $source);
            $payment->forceFill(['last_synced_at' => now()])->save();

            return $outcome;
        }

        $verification = $this->verifier->verify($attempt);

        if ($verification->outcome !== PaymentVerificationOutcome::AwaitingCapture) {
            // Awaiting capture is the expected answer while a page polls
            // and would only bury the signal; everything else is worth a
            // row an operator can read back in order.
            $this->audit->logSystem(self::LOG_NAME, 'booking_payment_provider_verified', sprintf('%s reported "%s" for booking payment attempt %s.', ucfirst((string) $attempt->provider), $verification->providerStatus ?? $verification->outcome->value, $attempt->id), $payment, [
                'source' => $source,
                'provider' => $attempt->provider,
                'payment_attempt_id' => $attempt->id,
                'provider_order_id' => $attempt->provider_order_id,
                'provider_payment_id' => $attempt->provider_payment_id,
                'provider_entity' => $verification->providerEntity,
                'provider_status' => $verification->providerStatus,
                'outcome' => $verification->outcome->value,
            ]);
        }

        $outcome = match ($verification->outcome) {
            PaymentVerificationOutcome::Unreachable => $this->recordUnreachable($payment, $attempt),
            PaymentVerificationOutcome::Captured, PaymentVerificationOutcome::Failed => $this->applyEvidence($payment, $attempt, $verification, $source),
            PaymentVerificationOutcome::NeedsAttention => $this->recordNeedsAttention($payment, $attempt, $verification),
            PaymentVerificationOutcome::AwaitingCapture, PaymentVerificationOutcome::NotPaid => $this->recordStillOpen($payment, $attempt, $verification, $cutoff),
            PaymentVerificationOutcome::Unverifiable => BookingPaymentReconciliationOutcome::NoAttempt,
        };

        BookingPayment::query()->whereKey($payment->id)->update(['last_synced_at' => now()]);

        return $outcome;
    }

    private function recordUnreachable(BookingPayment $payment, Payment $attempt): BookingPaymentReconciliationOutcome
    {
        // Unreachable is an outage, never evidence of non-payment.
        $this->raiseIssue(
            $payment,
            BookingPaymentReconciliationIssueType::ProviderUnavailable,
            BookingPaymentReconciliationSeverity::Warning,
            sprintf('Could not reach %s to verify this payment.', (string) $attempt->provider),
        );

        return BookingPaymentReconciliationOutcome::ProviderUnreachable;
    }

    /** Captured or definitively failed: hand the proof to the one settlement bridge. */
    private function applyEvidence(BookingPayment $payment, Payment $attempt, PaymentVerificationResult $verification, string $source): BookingPaymentReconciliationOutcome
    {
        $event = $verification->event;

        if ($event === null) {
            return BookingPaymentReconciliationOutcome::NeedsAttention;
        }

        try {
            $settled = $this->settlement->settle($attempt, $event, $source);
        } catch (BookingException $e) {
            // Local settlement failed after the attempt was recorded Paid.
            // The bridge raised its incident; the next pass repairs it
            // through finishIncompleteLocalSettlement().
            report($e);

            return BookingPaymentReconciliationOutcome::NeedsAttention;
        }

        if ($verification->outcome === PaymentVerificationOutcome::Failed) {
            return BookingPaymentReconciliationOutcome::Failed;
        }

        foreach ([
            BookingPaymentReconciliationIssueType::UnknownPaymentOutcome,
            BookingPaymentReconciliationIssueType::ProviderUnavailable,
            BookingPaymentReconciliationIssueType::StaleProcessing,
        ] as $type) {
            $this->resolveOpenIssues($payment, $type, 'auto_reconciled', 'Provider outcome confirmed on a later reconciliation pass.');
        }

        if ($settled) {
            return BookingPaymentReconciliationOutcome::Settled;
        }

        // settle() returned false: either the booking was already paid
        // (a race the webhook won) or the money did not match and the
        // bridge refused, raising its own incident.
        return $payment->booking?->fresh()?->payment_status === BookingPaymentStatus::Paid
            ? BookingPaymentReconciliationOutcome::AlreadySettled
            : BookingPaymentReconciliationOutcome::NeedsAttention;
    }

    private function recordNeedsAttention(BookingPayment $payment, Payment $attempt, PaymentVerificationResult $verification): BookingPaymentReconciliationOutcome
    {
        $this->raiseIssue(
            $payment,
            BookingPaymentReconciliationIssueType::UnknownPaymentOutcome,
            BookingPaymentReconciliationSeverity::Warning,
            sprintf('%s reported "%s" for this payment, a state the platform does not settle automatically.', ucfirst((string) $attempt->provider), $verification->providerStatus ?? 'unknown'),
        );

        return BookingPaymentReconciliationOutcome::NeedsAttention;
    }

    private function recordStillOpen(BookingPayment $payment, Payment $attempt, PaymentVerificationResult $verification, CarbonInterface $cutoff): BookingPaymentReconciliationOutcome
    {
        $this->detectStaleAttempt($payment, $attempt, $cutoff);

        return $verification->outcome === PaymentVerificationOutcome::AwaitingCapture
            ? BookingPaymentReconciliationOutcome::AwaitingCapture
            : BookingPaymentReconciliationOutcome::NotPaid;
    }

    /**
     * The attempt says Paid. If the booking is still payable the earlier
     * settlement stopped halfway; re-enter the local half — lock-
     * protected and idempotent inside the bridge — and close the
     * incident it raised.
     */
    private function finishIncompleteLocalSettlement(BookingPayment $payment, Payment $attempt, string $source): BookingPaymentReconciliationOutcome
    {
        $booking = $payment->booking;

        $stillPayable = $booking !== null
            && $booking->payment_status->isPayable()
            && ! $booking->status->isTerminal();

        if (! $stillPayable && $payment->status === BookingPaymentRecordStatus::Captured) {
            return BookingPaymentReconciliationOutcome::AlreadySettled;
        }

        try {
            $completed = $this->settlement->completeLocalSettlement($attempt, $source);
        } catch (BookingException $e) {
            report($e);

            return BookingPaymentReconciliationOutcome::NeedsAttention;
        }

        if (! $completed) {
            return BookingPaymentReconciliationOutcome::AlreadySettled;
        }

        $this->resolveOpenIssues($payment, BookingPaymentReconciliationIssueType::ProviderSuccessLocalIncomplete, 'auto_reconciled', 'Local settlement completed on a later reconciliation pass.');
        $this->resolveOpenIssues($payment, BookingPaymentReconciliationIssueType::ProviderUnavailable, 'auto_reconciled', 'Local settlement completed on a later reconciliation pass.');

        $this->audit->logSystem(self::LOG_NAME, 'booking_payment_recovered', sprintf('Booking %s: local settlement completed for an attempt the provider had already paid.', (string) $booking?->reference), $payment, [
            'source' => $source,
            'provider' => $attempt->provider,
            'payment_attempt_id' => $attempt->id,
            'provider_order_id' => $attempt->provider_order_id,
            'provider_payment_id' => $attempt->provider_payment_id,
            'booking_reference' => $booking?->reference,
        ]);

        return BookingPaymentReconciliationOutcome::Recovered;
    }

    /**
     * The attempt worth asking about: the open one if there is one
     * (there can be at most one), otherwise the most recent, so a
     * settled or failed history is still inspectable.
     */
    private function latestAttemptFor(BookingPayment $payment): ?Payment
    {
        return Payment::query()
            ->forPayable(BookingPayment::PAYABLE_TYPE, (string) $payment->getKey())
            ->orderByRaw('CASE WHEN status IN (?, ?) THEN 0 ELSE 1 END', [PaymentStatus::Pending->value, PaymentStatus::Processing->value])
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * An attempt the provider is reachable about but will not resolve.
     *
     * Never fails the payment: we do not know the money is gone, only
     * that the provider will not say. Waits several of the sweep's own
     * windows so ordinary checkout latency never reaches the queue.
     */
    private function detectStaleAttempt(BookingPayment $payment, Payment $attempt, CarbonInterface $cutoff): void
    {
        if (! $attempt->status->isOpen()) {
            return;
        }

        $threshold = $cutoff->copy()->subMinutes(
            max(1, $this->settings->booking_payment_unknown_timeout_minutes) * (self::STALE_WINDOW_MULTIPLIER - 1),
        );

        if ($attempt->created_at === null || $attempt->created_at->gt($threshold)) {
            return;
        }

        $this->raiseIssue(
            $payment,
            BookingPaymentReconciliationIssueType::StaleProcessing,
            BookingPaymentReconciliationSeverity::Warning,
            'The provider has not resolved this payment attempt well past the normal window.',
        );
    }

    public function raiseIssue(
        BookingPayment $payment,
        BookingPaymentReconciliationIssueType $type,
        BookingPaymentReconciliationSeverity $severity,
        string $safeSummary,
    ): BookingPaymentReconciliationIssue {
        $existing = BookingPaymentReconciliationIssue::query()
            ->open()
            ->where('booking_payment_id', $payment->id)
            ->where('type', $type)
            ->first();

        if ($existing !== null) {
            $existing->forceFill([
                'last_detected_at' => now(),
                'severity' => $this->higherSeverity($existing->severity, $severity),
                'provider_status' => $payment->provider_payment_id,
                'local_status' => $payment->status->value,
            ])->save();

            return $existing;
        }

        try {
            $issue = DB::transaction(function () use ($payment, $type, $severity, $safeSummary): BookingPaymentReconciliationIssue {
                $issue = BookingPaymentReconciliationIssue::query()->create([
                    'reference' => $this->generateReference(),
                    'booking_payment_id' => $payment->id,
                    'provider' => $payment->provider,
                    'type' => $type,
                    'severity' => $severity,
                    'local_status' => $payment->status->value,
                    'provider_status' => $payment->provider_payment_id,
                    'amount_minor' => $payment->amount_minor,
                    'currency_code' => $payment->currency_code,
                    'safe_summary' => $safeSummary,
                    'first_detected_at' => now(),
                    'last_detected_at' => now(),
                ]);
                $issue->forceFill(['status' => BookingPaymentReconciliationIssueStatus::Open])->save();

                return $issue;
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent sweep won the race to create the same open
            // issue — the DB-level dedupe key (see the creating
            // migration) is the backstop; fetch and update it instead.
            $existing = BookingPaymentReconciliationIssue::query()
                ->open()
                ->where('booking_payment_id', $payment->id)
                ->where('type', $type)
                ->firstOrFail();
            $existing->forceFill(['last_detected_at' => now()])->save();

            return $existing;
        }

        $this->audit->logSystem(self::LOG_NAME, 'booking_payment_reconciliation_issue_detected', sprintf('Reconciliation issue %s raised: %s', $issue->reference, $safeSummary), $issue, ['type' => $type->value, 'severity' => $severity->value]);

        return $issue;
    }

    public function assign(BookingPaymentReconciliationIssue $issue, User $assignee, User $actor): BookingPaymentReconciliationIssue
    {
        $issue->forceFill(['assigned_to' => $assignee->id])->save();

        $this->audit->logUser($actor, self::LOG_NAME, 'booking_payment_reconciliation_issue_assigned', sprintf('Reconciliation issue %s assigned to %s.', $issue->reference, $assignee->name), $issue);

        return $issue;
    }

    public function startInvestigating(BookingPaymentReconciliationIssue $issue, User $actor): BookingPaymentReconciliationIssue
    {
        if ($issue->status !== BookingPaymentReconciliationIssueStatus::Open) {
            throw new BookingException('Only an open issue can move to investigating.');
        }

        $issue->forceFill(['status' => BookingPaymentReconciliationIssueStatus::Investigating])->save();

        $this->audit->logUser($actor, self::LOG_NAME, 'booking_payment_reconciliation_issue_investigating', sprintf('Reconciliation issue %s marked as investigating.', $issue->reference), $issue);

        return $issue;
    }

    public function resolve(BookingPaymentReconciliationIssue $issue, User $actor, string $resolutionType, string $note): BookingPaymentReconciliationIssue
    {
        if (trim($note) === '') {
            throw new BookingException('A resolution note (the evidence) is required.');
        }

        if (! $issue->status->isOpen()) {
            throw new BookingException('This issue is already closed.');
        }

        $issue->forceFill([
            'status' => BookingPaymentReconciliationIssueStatus::Resolved,
            'resolved_at' => now(),
            'resolved_by' => $actor->id,
            'resolution_type' => $resolutionType,
            'resolution_note' => $note,
        ])->save();

        $this->audit->logOverride($actor, self::LOG_NAME, 'booking_payment_reconciliation_issue_resolved', sprintf('Reconciliation issue %s resolved.', $issue->reference), $note, $issue, ['resolution_type' => $resolutionType]);

        return $issue;
    }

    private function resolveOpenIssues(BookingPayment $payment, BookingPaymentReconciliationIssueType $type, string $resolutionType, string $note): void
    {
        BookingPaymentReconciliationIssue::query()
            ->open()
            ->where('booking_payment_id', $payment->id)
            ->where('type', $type)
            ->get()
            ->each(function (BookingPaymentReconciliationIssue $issue) use ($resolutionType, $note): void {
                $issue->forceFill([
                    'status' => BookingPaymentReconciliationIssueStatus::Resolved,
                    'resolved_at' => now(),
                    'resolution_type' => $resolutionType,
                    'resolution_note' => $note,
                ])->save();

                $this->audit->logSystem(self::LOG_NAME, 'booking_payment_reconciliation_issue_resolved', sprintf('Reconciliation issue %s auto-resolved.', $issue->reference), $issue);
            });
    }

    private function higherSeverity(BookingPaymentReconciliationSeverity $a, BookingPaymentReconciliationSeverity $b): BookingPaymentReconciliationSeverity
    {
        $rank = ['info' => 0, 'warning' => 1, 'critical' => 2];

        return $rank[$b->value] > $rank[$a->value] ? $b : $a;
    }

    private function generateReference(): string
    {
        do {
            $reference = sprintf('BPRI-%s-%s', now()->format('Ym'), strtoupper(Str::random(8)));
        } while (BookingPaymentReconciliationIssue::query()->where('reference', $reference)->exists());

        return $reference;
    }
}

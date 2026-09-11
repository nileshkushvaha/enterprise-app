<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Booking\Contracts\BookingCheckoutCompletionServiceInterface;
use App\Booking\Contracts\BookingPaymentReconciliationServiceInterface;
use App\Booking\Contracts\BookingPaymentServiceInterface;
use App\Booking\Contracts\RazorpayGatewayClient;
use App\Booking\Contracts\StudentBookingServiceInterface;
use App\Booking\DTOs\StudentBookingData;
use App\Booking\Enums\BookingCheckoutState;
use App\Booking\Enums\BookingPaymentReconciliationIssueStatus;
use App\Booking\Enums\BookingPaymentReconciliationIssueType;
use App\Booking\Enums\BookingPaymentReconciliationOutcome;
use App\Booking\Enums\BookingPaymentRecordStatus;
use App\Booking\Enums\BookingPaymentStatus;
use App\Booking\Enums\BookingStatus;
use App\Booking\Enums\Weekday;
use App\Booking\Events\BookingConfirmed;
use App\Booking\Exceptions\GatewayRequestException;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\BookingPaymentReconciliationIssue;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TeacherAvailability;
use App\Models\TeacherSubject;
use App\Models\User;
use App\Models\UserProfile;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Services\PaymentService;
use App\Services\Payment\PaymentWebhookSignatureService;
use App\Settings\BookingSettings;
use App\Settings\PaymentGatewaySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\Support\CreatesStudentLessonPrices;
use Tests\TestCase;

/**
 * The production incident, replayed: a captured Razorpay payment, a
 * booking webhook rejected as unverifiable, and the question of which
 * of the remaining routes — callback confirmation, a later webhook, the
 * scheduled sweep, an admin retry — converts it into a confirmed
 * booking. Every route must converge on exactly ONE settlement.
 */
final class RazorpaySettlementRecoveryTest extends TestCase
{
    use CreatesStudentLessonPrices;
    use RefreshDatabase;

    private const KEY_SECRET = 'rzp_test_key_secret_value';

    private const BOOKING_WEBHOOK_SECRET = 'booking_endpoint_secret';

    private const WALLET_WEBHOOK_SECRET = 'wallet_endpoint_secret';

    private User $student;

    private User $teacher;

    private Mockery\MockInterface $razorpay;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $this->student = User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]);
        $this->student->profile()->update(['phone_e164' => '+9199999'.str_pad((string) $this->student->id, 5, '0', STR_PAD_LEFT), 'phone_verified_at' => now()]);

        $this->teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        UserProfile::updateOrCreate(['user_id' => $this->teacher->id], ['instructor_status' => 'approved']);
        TeacherSubject::factory()->state(['teacher_id' => $this->teacher->id])->subject('maths', 1, 12)->create();
        foreach (Weekday::cases() as $day) {
            TeacherAvailability::factory()->state(['teacher_id' => $this->teacher->id])->forDay($day)->between('09:00:00', '17:00:00')->create();
        }

        $priced = $this->createPaidBookingTypeWithPrice('paid_one_to_one', 499.00, 'INR');
        $this->assignBillingCountry($this->student, $priced['country']);

        $gateways = app(PaymentGatewaySettings::class);
        $gateways->razorpay_enabled = true;
        $gateways->razorpay_key_id = 'rzp_test_key_id';
        $gateways->razorpay_key_secret = Crypt::encryptString(self::KEY_SECRET);
        // The production shape: a dedicated secret per endpoint.
        $gateways->razorpay_booking_webhook_secret = Crypt::encryptString(self::BOOKING_WEBHOOK_SECRET);
        $gateways->razorpay_wallet_webhook_secret = Crypt::encryptString(self::WALLET_WEBHOOK_SECRET);
        $gateways->save();

        $bookings = app(BookingSettings::class);
        $bookings->payment_provider = 'razorpay';
        $bookings->save();

        $this->razorpay = Mockery::mock(RazorpayGatewayClient::class);
        $this->razorpay->shouldReceive('createOrder')->andReturn(['id' => 'order_RCV1', 'amount' => 49900, 'currency' => 'INR']);
        $this->app->instance(RazorpayGatewayClient::class, $this->razorpay);
    }

    // ── Fixtures ──────────────────────────────────────────────────────

    /** @return array{Booking, BookingPayment, Payment} */
    private function reservedBooking(): array
    {
        $booking = app(StudentBookingServiceInterface::class)->book(new StudentBookingData(
            typeKey: 'paid_one_to_one',
            studentId: $this->student->id,
            teacherId: $this->teacher->id,
            startsAt: now('UTC')->addDays(3)->setTime(10, 0)->toImmutable(),
            subject: 'maths',
            grade: 7,
        ));
        app(BookingPaymentServiceInterface::class)->initiate($booking);

        $obligation = BookingPayment::query()->where('booking_id', $booking->id)->sole();
        $attempt = Payment::query()->where('payable_id', $obligation->getKey())->sole();

        return [$booking->refresh(), $obligation, $attempt];
    }

    private function signature(string $orderId, string $paymentId): string
    {
        return hash_hmac('sha256', "{$orderId}|{$paymentId}", self::KEY_SECRET);
    }

    private function completion(): BookingCheckoutCompletionServiceInterface
    {
        return app(BookingCheckoutCompletionServiceInterface::class);
    }

    private function reconciliation(): BookingPaymentReconciliationServiceInterface
    {
        return app(BookingPaymentReconciliationServiceInterface::class);
    }

    /** @return array<string, mixed> */
    private function capturedPayment(string $paymentId, string $orderId = 'order_RCV1'): array
    {
        return ['id' => $paymentId, 'entity' => 'payment', 'order_id' => $orderId, 'status' => 'captured', 'amount' => 49900, 'currency' => 'INR'];
    }

    private function razorpayPaymentIs(string $status, string $paymentId = 'pay_RCV1'): void
    {
        $this->razorpay->shouldReceive('fetchPayment')->andReturn(['id' => $paymentId, 'order_id' => 'order_RCV1', 'status' => $status, 'amount' => 49900, 'currency' => 'INR']);
    }

    private function razorpayUnreachable(): void
    {
        $this->razorpay->shouldReceive('fetchPayment')->andThrow(new GatewayRequestException('Razorpay did not respond in time'));
        $this->razorpay->shouldReceive('fetchOrder')->andThrow(new GatewayRequestException('Razorpay did not respond in time'));
    }

    /** Replaces the mock so a later phase of a test can give a different provider answer. */
    private function razorpayNow(string $paymentStatus, string $paymentId = 'pay_RCV1'): void
    {
        Mockery::close();
        $this->razorpay = Mockery::mock(RazorpayGatewayClient::class);
        $this->razorpay->shouldReceive('fetchPayment')->andReturn(['id' => $paymentId, 'order_id' => 'order_RCV1', 'status' => $paymentStatus, 'amount' => 49900, 'currency' => 'INR']);
        $this->razorpay->shouldReceive('fetchOrder')->andReturn(['id' => 'order_RCV1', 'status' => $paymentStatus === 'captured' ? 'paid' : 'attempted', 'amount' => 49900, 'currency' => 'INR']);
        $this->app->instance(RazorpayGatewayClient::class, $this->razorpay);
    }

    /** @return array<string, mixed> */
    private function webhookPayload(Payment $attempt, string $paymentId = 'pay_RCV1', string $event = 'payment.captured'): array
    {
        return [
            'event' => $event,
            'payload' => ['payment' => ['entity' => [
                'id' => $paymentId,
                'order_id' => $attempt->provider_order_id,
                'amount' => 49900,
                'currency' => 'INR',
                'notes' => ['payment_reference' => $attempt->idempotency_key],
            ]]],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    private function postBookingWebhook(array $payload, string $secret = self::BOOKING_WEBHOOK_SECRET): TestResponse
    {
        $body = (string) json_encode($payload);

        return $this->call('POST', '/api/webhooks/bookings/payments/razorpay', [], [], [], [
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, $secret),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $body);
    }

    private function assertSettledExactlyOnce(Booking $booking, Payment $attempt): void
    {
        $booking->refresh();
        $this->assertSame(BookingPaymentStatus::Paid, $booking->payment_status);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(PaymentStatus::Paid, $attempt->refresh()->status);
        $this->assertSame(BookingPaymentRecordStatus::Captured, BookingPayment::query()->where('booking_id', $booking->id)->sole()->status);
        $this->assertSame(1, Invoice::query()->count(), 'exactly one receipt');
        $this->assertSame(1, Activity::query()->where('event', 'payment_attempt_paid')->count(), 'the attempt moved to Paid exactly once');
        $this->assertSame(1, Activity::query()->where('event', 'booking_payment_settled')->count(), 'the booking settled exactly once');
    }

    // ── The incident ──────────────────────────────────────────────────

    /**
     * 11 Sep 2026: Razorpay captured, its booking deliveries were signed
     * with a secret SIRI did not hold for that endpoint, and every one
     * was answered 401. With payment-level confirmation the callback
     * settles anyway, and the eventually-correct webhook is a no-op.
     */
    public function test_a_captured_payment_settles_through_the_callback_even_when_the_webhook_secret_is_wrong(): void
    {
        [$booking, , $attempt] = $this->reservedBooking();

        // Razorpay retries with the secret of a DIFFERENT endpoint (the
        // wallet one — what a single shared value amounts to).
        $this->postBookingWebhook($this->webhookPayload($attempt), self::WALLET_WEBHOOK_SECRET)->assertStatus(401);
        $this->postBookingWebhook($this->webhookPayload($attempt), self::WALLET_WEBHOOK_SECRET)->assertStatus(401);
        $this->assertSame(2, Activity::query()->where('event', 'booking_webhook_signature_invalid')->count(), 'each rejection is inspectable');
        $this->assertSame(BookingPaymentStatus::Pending, $booking->refresh()->payment_status);

        // The browser comes back; Razorpay confirms the specific payment is captured.
        $this->razorpayPaymentIs('captured');
        $outcome = $this->completion()->completeRazorpayCheckout($booking, 'order_RCV1', 'pay_RCV1', $this->signature('order_RCV1', 'pay_RCV1'));

        $this->assertSame(BookingCheckoutState::Confirmed, $outcome->state);
        $this->assertSettledExactlyOnce($booking, $attempt);
        $this->assertSame(1, Activity::query()->where('event', 'booking_checkout_verified')->count());

        // The operator fixes the secret; Razorpay's retry now verifies and is harmlessly ignored.
        $this->postBookingWebhook($this->webhookPayload($attempt))->assertOk()->assertJsonPath('status', 'ignored');
        $this->assertSettledExactlyOnce($booking, $attempt);
    }

    public function test_callback_lookup_unavailable_then_the_webhook_settles(): void
    {
        [$booking, $obligation, $attempt] = $this->reservedBooking();
        $this->razorpayUnreachable();

        $outcome = $this->completion()->completeRazorpayCheckout($booking, 'order_RCV1', 'pay_RCV1', $this->signature('order_RCV1', 'pay_RCV1'));

        $this->assertSame(BookingCheckoutState::ProviderUnreachable, $outcome->state);
        $this->assertTrue($outcome->confirmationInProgress());
        $issue = BookingPaymentReconciliationIssue::query()->where('booking_payment_id', $obligation->id)->sole();
        $this->assertSame(BookingPaymentReconciliationIssueType::ProviderUnavailable, $issue->type);

        // A reload sees the same server-derived state — no Pay button.
        $this->assertSame(BookingCheckoutState::ProviderUnreachable, $this->completion()->currentState($booking->fresh())->state);

        $this->postBookingWebhook($this->webhookPayload($attempt))->assertOk()->assertJsonPath('status', 'processed');

        $this->assertSettledExactlyOnce($booking, $attempt);
        $this->assertSame(BookingCheckoutState::Confirmed, $this->completion()->currentState($booking->fresh())->state);
        $this->assertSame(1, Activity::query()->where('event', 'booking_webhook_processed')->count());
    }

    public function test_callback_lookup_unavailable_then_the_scheduled_sweep_settles(): void
    {
        [$booking, $obligation, $attempt] = $this->reservedBooking();
        $this->razorpayUnreachable();
        $this->completion()->completeRazorpayCheckout($booking, 'order_RCV1', 'pay_RCV1', $this->signature('order_RCV1', 'pay_RCV1'));
        $this->assertSame(BookingPaymentStatus::Pending, $booking->refresh()->payment_status);

        // Past the unknown-timeout window the sweep picks the attempt up;
        // Razorpay is back and confirms capture.
        $this->travel(app(PaymentGatewaySettings::class)->booking_payment_unknown_timeout_minutes + 1)->minutes();
        $this->razorpayNow('captured');

        $examined = $this->reconciliation()->reconcileDue();

        $this->assertSame(1, $examined);
        $this->assertSettledExactlyOnce($booking, $attempt);
        $this->assertSame(BookingPaymentReconciliationIssueStatus::Resolved, BookingPaymentReconciliationIssue::query()->where('booking_payment_id', $obligation->id)->sole()->status, 'the outage incident closes itself once the outcome is known');
        $this->assertSame(1, Activity::query()->where('event', 'booking_reconciliation_completed')->count());
    }

    // ── Paid attempt, pending booking ─────────────────────────────────

    /**
     * The state that used to be permanent: provider money recorded on
     * the attempt, booking never settled. The next pass — sweep, poll or
     * admin retry — must finish the local half without touching the
     * attempt again.
     */
    public function test_a_paid_attempt_with_a_pending_booking_is_recovered_by_reconciliation(): void
    {
        [$booking, $obligation, $attempt] = $this->reservedBooking();
        $this->razorpayUnreachable();

        // Provider money recorded, local settlement never happened.
        app(PaymentService::class)->transition($attempt, PaymentStatus::Paid, ['provider_payment_id' => 'pay_RCV1']);
        $this->assertSame(BookingPaymentStatus::Pending, $booking->refresh()->payment_status);
        $this->assertSame(BookingCheckoutState::NeedsAttention, $this->completion()->currentState($booking)->state, 'the student is told not to pay again');

        $outcome = $this->reconciliation()->reconcileNow($obligation, 'reconciliation');

        $this->assertSame(BookingPaymentReconciliationOutcome::Recovered, $outcome);
        $this->assertSettledExactlyOnce($booking, $attempt);
        $this->assertSame(1, Activity::query()->where('event', 'booking_payment_recovered')->count());

        // A second pass — or the admin's retry — is a no-op.
        $this->assertSame(BookingPaymentReconciliationOutcome::AlreadySettled, $this->reconciliation()->reconcileNow($obligation->fresh(), 'reconciliation'));
        $this->reconciliation()->reconcileAttempt($obligation->fresh());
        $this->assertSettledExactlyOnce($booking, $attempt);
    }

    public function test_the_admin_retry_can_finish_a_paid_attempt_with_a_pending_booking(): void
    {
        [$booking, $obligation, $attempt] = $this->reservedBooking();
        $this->razorpayUnreachable();
        app(PaymentService::class)->transition($attempt, PaymentStatus::Paid, ['provider_payment_id' => 'pay_RCV1']);

        $updated = $this->reconciliation()->reconcileAttempt($obligation);

        $this->assertSame(BookingPaymentRecordStatus::Captured, $updated->status);
        $this->assertSettledExactlyOnce($booking, $attempt);
    }

    // ── Ordering and idempotency ──────────────────────────────────────

    public function test_webhook_before_callback_leaves_the_callback_a_confirmed_no_op(): void
    {
        [$booking, , $attempt] = $this->reservedBooking();
        $this->razorpay->shouldNotReceive('fetchPayment');
        $this->razorpay->shouldNotReceive('fetchOrder');

        $this->postBookingWebhook($this->webhookPayload($attempt))->assertOk()->assertJsonPath('status', 'processed');

        $outcome = $this->completion()->completeRazorpayCheckout($booking, 'order_RCV1', 'pay_RCV1', $this->signature('order_RCV1', 'pay_RCV1'));

        $this->assertSame(BookingCheckoutState::Confirmed, $outcome->state);
        $this->assertSettledExactlyOnce($booking, $attempt);
    }

    public function test_order_paid_and_payment_captured_deliveries_converge_on_one_settlement(): void
    {
        [$booking, , $attempt] = $this->reservedBooking();

        $this->postBookingWebhook($this->webhookPayload($attempt, event: 'order.paid'))->assertOk()->assertJsonPath('status', 'processed');
        $this->postBookingWebhook($this->webhookPayload($attempt))->assertOk()->assertJsonPath('status', 'ignored');
        $this->postBookingWebhook($this->webhookPayload($attempt))->assertOk()->assertJsonPath('status', 'ignored');

        $this->assertSettledExactlyOnce($booking, $attempt);
    }

    public function test_an_order_paid_only_subscription_still_settles(): void
    {
        [$booking, , $attempt] = $this->reservedBooking();

        $this->postBookingWebhook($this->webhookPayload($attempt, event: 'order.paid'))->assertOk()->assertJsonPath('status', 'processed');

        $this->assertSettledExactlyOnce($booking, $attempt);
    }

    /**
     * Callback, webhook and sweep all arrive for the same payment, back
     * to back. One financial settlement, one confirmation, one receipt,
     * one BookingConfirmed (the meeting side effect hangs off it).
     */
    public function test_callback_webhook_and_sweep_for_the_same_payment_converge_on_one_settlement(): void
    {
        Event::fake([BookingConfirmed::class]);

        [$booking, $obligation, $attempt] = $this->reservedBooking();
        $this->razorpayPaymentIs('captured');
        $this->razorpay->shouldReceive('fetchOrder')->andReturn(['id' => 'order_RCV1', 'status' => 'paid', 'amount' => 49900, 'currency' => 'INR']);

        $this->completion()->completeRazorpayCheckout($booking, 'order_RCV1', 'pay_RCV1', $this->signature('order_RCV1', 'pay_RCV1'));
        $this->postBookingWebhook($this->webhookPayload($attempt))->assertOk()->assertJsonPath('status', 'ignored');
        $this->completion()->refreshPendingPayment($booking->fresh());
        $this->travel(app(PaymentGatewaySettings::class)->booking_payment_unknown_timeout_minutes + 1)->minutes();
        $this->reconciliation()->reconcileDue();
        $this->reconciliation()->reconcileAttempt($obligation->fresh());

        $this->assertSettledExactlyOnce($booking, $attempt);
        Event::assertDispatchedTimes(BookingConfirmed::class, 1);
    }

    // ── Authorised, not captured ──────────────────────────────────────

    public function test_an_authorized_payment_is_awaiting_capture_and_settles_once_captured(): void
    {
        [$booking, , $attempt] = $this->reservedBooking();
        $this->razorpayPaymentIs('authorized');

        $outcome = $this->completion()->completeRazorpayCheckout($booking, 'order_RCV1', 'pay_RCV1', $this->signature('order_RCV1', 'pay_RCV1'));

        $this->assertSame(BookingCheckoutState::AwaitingCapture, $outcome->state);
        $this->assertSame(BookingPaymentStatus::Pending, $outcome->booking->payment_status);
        $this->assertSame(0, BookingPaymentReconciliationIssue::query()->count(), 'waiting for capture is not an incident');
        $this->assertSame(BookingCheckoutState::AwaitingCapture, $this->completion()->currentState($booking->fresh())->state, 'survives a reload');

        $this->travel(20)->seconds();
        $this->razorpayNow('captured');

        $this->assertSame(BookingCheckoutState::Confirmed, $this->completion()->refreshPendingPayment($booking->fresh())->state);
        $this->assertSettledExactlyOnce($booking, $attempt);
    }

    public function test_a_payment_razorpay_reports_failed_is_recorded_and_the_student_may_retry(): void
    {
        [$booking, , $attempt] = $this->reservedBooking();
        $this->razorpayPaymentIs('failed');

        $outcome = $this->completion()->completeRazorpayCheckout($booking, 'order_RCV1', 'pay_RCV1', $this->signature('order_RCV1', 'pay_RCV1'));

        $this->assertSame(BookingCheckoutState::Failed, $outcome->state);
        $this->assertFalse($outcome->confirmationInProgress());
        $this->assertSame(PaymentStatus::Failed, $attempt->refresh()->status);
        $this->assertTrue($booking->refresh()->payment_status->isPayable(), 'one declined card does not cancel the obligation');
        $this->assertSame(0, Invoice::query()->count());
    }

    /** A payment id that resolves to somebody else's order is never evidence for this booking. */
    public function test_a_payment_belonging_to_another_order_needs_attention_and_never_settles(): void
    {
        [$booking, $obligation] = $this->reservedBooking();
        $this->razorpay->shouldReceive('fetchPayment')->andReturn($this->capturedPayment('pay_RCV1', orderId: 'order_SOMEONE_ELSE'));

        $outcome = $this->completion()->completeRazorpayCheckout($booking, 'order_RCV1', 'pay_RCV1', $this->signature('order_RCV1', 'pay_RCV1'));

        $this->assertSame(BookingCheckoutState::NeedsAttention, $outcome->state);
        $this->assertSame(BookingPaymentStatus::Pending, $outcome->booking->payment_status);
        $this->assertSame(BookingPaymentReconciliationIssueType::UnknownPaymentOutcome, BookingPaymentReconciliationIssue::query()->where('booking_payment_id', $obligation->id)->sole()->type);
    }

    /** The secret configured for the wallet endpoint never authenticates the booking endpoint, whatever the payload. */
    public function test_a_wallet_secret_cannot_settle_a_booking(): void
    {
        [$booking, , $attempt] = $this->reservedBooking();

        $this->postBookingWebhook($this->webhookPayload($attempt), self::WALLET_WEBHOOK_SECRET)->assertStatus(401);

        $this->assertSame(BookingPaymentStatus::Pending, $booking->refresh()->payment_status);
        $this->assertSame(PaymentStatus::Pending, $attempt->refresh()->status);
        $this->assertSame(PaymentWebhookSignatureService::PURPOSE_BOOKING, 'booking');
    }
}

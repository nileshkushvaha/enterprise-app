<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Booking\Contracts\BookingCheckoutCompletionServiceInterface;
use App\Booking\Contracts\BookingPaymentServiceInterface;
use App\Booking\Contracts\RazorpayGatewayClient;
use App\Booking\Contracts\StudentBookingServiceInterface;
use App\Booking\DTOs\StudentBookingData;
use App\Booking\Enums\BookingCheckoutState;
use App\Booking\Enums\BookingPaymentStatus;
use App\Booking\Enums\BookingStatus;
use App\Booking\Enums\Weekday;
use App\Booking\Exceptions\GatewayRequestException;
use App\Booking\Exceptions\InvalidPaymentWebhookException;
use App\Booking\Services\BookingCheckoutCompletionService;
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
use App\Settings\BookingSettings;
use App\Settings\PaymentGatewaySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\Support\CreatesStudentLessonPrices;
use Tests\TestCase;

/**
 * Checkout completion, at the service boundary: the browser callback is
 * verified, the provider is asked, and settlement happens through the
 * one settlement path — or not at all.
 */
final class BookingCheckoutCompletionServiceTest extends TestCase
{
    use CreatesStudentLessonPrices;
    use RefreshDatabase;

    private const KEY_SECRET = 'rzp_test_key_secret_value';

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
        $gateways->razorpay_booking_webhook_secret = Crypt::encryptString('webhook_secret');
        $gateways->save();

        $bookings = app(BookingSettings::class);
        $bookings->payment_provider = 'razorpay';
        $bookings->save();

        $this->razorpay = Mockery::mock(RazorpayGatewayClient::class);
        $this->razorpay->shouldReceive('createOrder')->andReturn(['id' => 'order_SVC1', 'amount' => 49900, 'currency' => 'INR']);
        $this->app->instance(RazorpayGatewayClient::class, $this->razorpay);
    }

    /** @return array{Booking, string} the pending booking and its Razorpay order id */
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

        return [$booking->refresh(), 'order_SVC1'];
    }

    private function signature(string $orderId, string $paymentId): string
    {
        return hash_hmac('sha256', "{$orderId}|{$paymentId}", self::KEY_SECRET);
    }

    private function service(): BookingCheckoutCompletionServiceInterface
    {
        return app(BookingCheckoutCompletionServiceInterface::class);
    }

    /** @return array<string, mixed> */
    private function capturedPayment(string $paymentId, string $orderId, int $amount = 49900, string $currency = 'INR'): array
    {
        return ['id' => $paymentId, 'entity' => 'payment', 'order_id' => $orderId, 'status' => 'captured', 'amount' => $amount, 'currency' => $currency];
    }

    /** @return array<string, mixed> */
    private function authorizedPayment(string $paymentId, string $orderId): array
    {
        return ['id' => $paymentId, 'entity' => 'payment', 'order_id' => $orderId, 'status' => 'authorized', 'amount' => 49900, 'currency' => 'INR'];
    }

    public function test_a_verified_callback_whose_order_razorpay_reports_paid_settles_through_the_real_path(): void
    {
        [$booking, $orderId] = $this->reservedBooking();
        $this->razorpay->shouldReceive('fetchPayment')->once()->with('rzp_test_key_id', self::KEY_SECRET, 'pay_SVC1')->andReturn($this->capturedPayment('pay_SVC1', $orderId));
        $this->razorpay->shouldNotReceive('fetchOrder');

        $outcome = $this->service()->completeRazorpayCheckout($booking, $orderId, 'pay_SVC1', $this->signature($orderId, 'pay_SVC1'));
        $result = $outcome->booking;

        $this->assertSame(BookingCheckoutState::Confirmed, $outcome->state);
        $this->assertSame(BookingPaymentStatus::Paid, $result->payment_status);
        $this->assertSame(BookingStatus::Confirmed, $result->status);
        $this->assertNull($result->reserved_until, 'the hold is cleared');
        $obligation = BookingPayment::query()->where('booking_id', $booking->id)->sole();
        $attempt = Payment::query()->where('payable_id', $obligation->getKey())->sole();
        $this->assertSame(PaymentStatus::Paid, $attempt->status);
        $this->assertSame('pay_SVC1', $attempt->provider_payment_id);
        $this->assertSame(1, Invoice::query()->count(), 'receipt chain, exactly as for a webhook');
    }

    /** Checkout.js fires on authorization; if Razorpay has not captured, nothing is settled. */
    public function test_an_order_razorpay_does_not_yet_report_paid_leaves_the_booking_payable(): void
    {
        [$booking, $orderId] = $this->reservedBooking();
        $this->razorpay->shouldReceive('fetchPayment')->once()->andReturn($this->authorizedPayment('pay_SVC1', $orderId));

        $outcome = $this->service()->completeRazorpayCheckout($booking, $orderId, 'pay_SVC1', $this->signature($orderId, 'pay_SVC1'));
        $result = $outcome->booking;

        $this->assertSame(BookingCheckoutState::AwaitingCapture, $outcome->state);
        $this->assertTrue($outcome->confirmationInProgress());
        $this->assertSame(BookingPaymentStatus::Pending, $result->payment_status);
        $this->assertSame(BookingStatus::Pending, $result->status);
        $this->assertSame('pay_SVC1', Payment::query()->firstOrFail()->provider_payment_id, 'the payment id is still recorded for the webhook to correlate');
        $this->assertSame(0, Invoice::query()->count());
    }

    /** The provider being down is not a failure of the payment: the booking stays payable and an issue is recorded for operators. */
    public function test_an_unreachable_provider_leaves_the_booking_payable_and_raises_an_issue(): void
    {
        [$booking, $orderId] = $this->reservedBooking();
        $this->razorpay->shouldReceive('fetchPayment')->once()->andThrow(new GatewayRequestException('timeout'));

        $outcome = $this->service()->completeRazorpayCheckout($booking, $orderId, 'pay_SVC1', $this->signature($orderId, 'pay_SVC1'));

        $this->assertSame(BookingCheckoutState::ProviderUnreachable, $outcome->state);
        $this->assertTrue($outcome->confirmationInProgress(), 'the student is told not to pay again, not shown a Pay button');
        $this->assertSame(BookingPaymentStatus::Pending, $outcome->booking->payment_status);
        $this->assertSame(1, BookingPaymentReconciliationIssue::query()->count());
    }

    public function test_a_forged_signature_is_refused_before_the_provider_is_ever_asked(): void
    {
        [$booking, $orderId] = $this->reservedBooking();
        $this->razorpay->shouldNotReceive('fetchOrder');
        $this->razorpay->shouldNotReceive('fetchPayment');

        $this->expectException(InvalidPaymentWebhookException::class);
        $this->service()->completeRazorpayCheckout($booking, $orderId, 'pay_FORGED', 'not-the-signature');
    }

    /** The poll: local reads are free; the provider is asked at most once per window, and settles when it reports paid. */
    public function test_refresh_throttles_provider_lookups_and_settles_once_paid(): void
    {
        [$booking, $orderId] = $this->reservedBooking();
        $this->razorpay->shouldReceive('fetchPayment')->once()->andReturn($this->authorizedPayment('pay_SVC1', $orderId));
        $this->service()->completeRazorpayCheckout($booking, $orderId, 'pay_SVC1', $this->signature($orderId, 'pay_SVC1'));

        // Inside the window: no provider call even though it would now report captured.
        $this->razorpay->shouldReceive('fetchPayment')->never();
        $refreshed = $this->service()->refreshPendingPayment($booking);
        $this->assertSame(BookingCheckoutState::AwaitingCapture, $refreshed->state);
        $this->assertSame(BookingPaymentStatus::Pending, $refreshed->booking->payment_status);

        // After the window: asked again, settled.
        $this->travel(BookingCheckoutCompletionService::PROVIDER_RECHECK_SECONDS + 1)->seconds();
        Mockery::close();
        $this->razorpay = Mockery::mock(RazorpayGatewayClient::class);
        $this->razorpay->shouldReceive('fetchPayment')->once()->andReturn($this->capturedPayment('pay_SVC1', $orderId));
        $this->app->instance(RazorpayGatewayClient::class, $this->razorpay);

        $settled = $this->service()->refreshPendingPayment($booking->fresh());
        $this->assertSame(BookingCheckoutState::Confirmed, $settled->state);
        $this->assertSame(BookingPaymentStatus::Paid, $settled->booking->payment_status);
    }

    public function test_refresh_is_a_plain_read_for_a_booking_that_is_not_awaiting_payment(): void
    {
        $this->razorpay->shouldNotReceive('fetchOrder');
        $this->razorpay->shouldNotReceive('fetchPayment');
        $booking = Booking::factory()->confirmed()->create(['payment_status' => BookingPaymentStatus::Paid]);

        $outcome = $this->service()->refreshPendingPayment($booking);
        $this->assertSame(BookingCheckoutState::Confirmed, $outcome->state);
        $this->assertSame(BookingPaymentStatus::Paid, $outcome->booking->payment_status);
    }
}

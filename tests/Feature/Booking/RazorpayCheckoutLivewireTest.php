<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Booking\Contracts\RazorpayGatewayClient;
use App\Booking\Contracts\StudentBookingServiceInterface;
use App\Booking\DTOs\StudentBookingData;
use App\Booking\Enums\BookingPaymentRecordStatus;
use App\Booking\Enums\BookingPaymentStatus;
use App\Booking\Enums\BookingStatus;
use App\Booking\Enums\Weekday;
use App\Booking\Exceptions\GatewayRequestException;
use App\Booking\Services\BookingCheckoutCompletionService;
use App\Livewire\Frontend\Student\BookingDetail;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\BookingType;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\StudentLessonPrice;
use App\Models\TeacherAvailability;
use App\Models\TeacherSubject;
use App\Models\User;
use App\Models\UserProfile;
use App\Payments\Enums\PaymentStatus;
use App\Settings\BookingSettings;
use App\Settings\PaymentGatewaySettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Mockery;
use Mockery\MockInterface;
use Tests\Support\CreatesAcademicBookingContext;
use Tests\Support\CreatesStudentLessonPrices;
use Tests\TestCase;

/**
 * Student-facing Razorpay checkout via Livewire: the wizard's golden
 * path (pay immediately after booking) and BookingHistory's retry path
 * (pay later from the dashboard), plus the 'pay' policy boundary.
 */
class RazorpayCheckoutLivewireTest extends TestCase
{
    use CreatesAcademicBookingContext;
    use CreatesStudentLessonPrices;
    use RefreshDatabase;

    private const KEY_SECRET = 'test_key_secret';

    private User $teacher;

    private Country $pricedCountry;

    /** @var array<string, mixed> */
    private array $academic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootAcademicBookingContext();

        $this->teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->teacher->assignRole('instructor');
        UserProfile::updateOrCreate(['user_id' => $this->teacher->id], [
            'instructor_status' => 'approved',
            'profile_visibility' => 'public',
        ]);
        foreach (Weekday::cases() as $day) {
            TeacherAvailability::factory()->state(['teacher_id' => $this->teacher->id])
                ->forDay($day)->between('09:00:00', '17:00:00')->create();
        }

        // Country-aware academics are mandatory, so checkout needs a
        // complete Country -> System -> Level -> Subject -> Curriculum
        // chain plus an eligible instructor before a price can even be
        // resolved. The shared trait owns that fixture.
        $priced = $this->createPaidBookingTypeWithPrice('paid_one_to_one', 499.00, 'INR', durationMinutes: 60);
        BookingType::query()->where('key', 'paid_one_to_one')->update(['sort_order' => 1]);
        $this->pricedCountry = $priced['country'];

        $this->academic = $this->seedAcademicContext('RZP', $this->pricedCountry, normalizedGrade: 10);

        // The instructor must teach the academic subject and be eligible
        // for this system/curriculum, or the wizard offers no slots.
        TeacherSubject::factory()->create([
            'teacher_id' => $this->teacher->id,
            'subject' => $this->academic['subject']->name,
            'subject_id' => $this->academic['subject']->id,
            'grade_from' => 1,
            'grade_to' => 12,
        ]);
        $this->makeInstructorEligible($this->teacher, $this->academic['system'], $this->academic['curriculum']);

        // Price the ACADEMIC subject (the legacy 'maths' row cannot be
        // reached now that subject selection comes from the curriculum).
        $this->seedStudentLessonPrice(
            $priced['type'],
            $this->pricedCountry,
            $priced['currency'],
            499.00,
            $this->academic['subject']->slug,
        );

        $gateways = app(PaymentGatewaySettings::class);
        $gateways->razorpay_enabled = true;
        $gateways->razorpay_key_id = 'rzp_test_key_id';
        $gateways->razorpay_key_secret = Crypt::encryptString(self::KEY_SECRET);
        $gateways->razorpay_booking_webhook_secret = Crypt::encryptString('webhook_secret');
        $gateways->save();

        app(BookingSettings::class)->payment_provider = 'razorpay';
        app(BookingSettings::class)->save();

        $mock = Mockery::mock(RazorpayGatewayClient::class);
        $mock->shouldReceive('createOrder')->andReturn(['id' => 'order_LW1', 'amount' => 49900, 'currency' => 'INR']);
        // The checkout callback now asks Razorpay for the order at once.
        // By default the order is NOT yet paid, so the existing tests keep
        // exercising the "verified but unsettled" state; tests that want
        // instant confirmation override this with status 'paid'.
        $mock->shouldReceive('fetchOrder')->andReturn(['id' => 'order_LW1', 'status' => 'created', 'amount' => 49900, 'currency' => 'INR'])->byDefault();
        // Once the callback has recorded the payment id, the specific
        // PAYMENT is asked about. By default it is authorized but not yet
        // captured — the "verified but unsettled" state.
        $mock->shouldReceive('fetchPayment')->andReturnUsing(fn (string $k, string $s, string $paymentId): array => ['id' => $paymentId, 'order_id' => 'order_LW1', 'status' => 'authorized', 'amount' => 49900, 'currency' => 'INR'])->byDefault();
        $this->app->instance(RazorpayGatewayClient::class, $mock);
        $this->razorpayMock = $mock;
    }

    private MockInterface $razorpayMock;

    /** Razorpay reports the order as paid when asked — the normal case right after a successful checkout. */
    private function razorpayReportsPaid(string $orderId = 'order_LW1'): void
    {
        $this->razorpayMock->shouldReceive('fetchOrder')->andReturn(['id' => $orderId, 'status' => 'paid', 'amount' => 49900, 'currency' => 'INR']);
        $this->razorpayMock->shouldReceive('fetchPayment')->andReturnUsing(fn (string $k, string $s, string $paymentId): array => ['id' => $paymentId, 'order_id' => $orderId, 'status' => 'captured', 'amount' => 49900, 'currency' => 'INR']);
    }

    private function checkoutSignature(string $orderId, string $paymentId): string
    {
        return hash_hmac('sha256', "{$orderId}|{$paymentId}", self::KEY_SECRET);
    }

    // A resolvable billing country is required before checkout (the
    // UI-layer profile-completeness gate) — set it here so these tests
    // exercise payment behavior, not the gate itself. Uses
    // $this->pricedCountry (not a fresh random one) so the booking's
    // subject/grade + this country actually resolves against the
    // StudentLessonPrice seeded in setUp().
    private function withBillingCountry(User $user): void
    {
        $this->assignBillingCountry($user, $this->pricedCountry);
    }

    public function test_student_can_pay_via_the_booking_wizard(): void
    {
        $student = User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]);
        $this->withBillingCountry($student);

        $slot = CarbonImmutable::now('UTC')->addDays(3)->setTime(10, 0);

        $component = $this->navigateAcademicWizardToSlot(
            Livewire::actingAs($student)->test('frontend.booking.booking-wizard'),
            $this->academic,
            $slot,
        );

        // The canonical paid flow is nine phases (mode, level, subject,
        // curriculum, billing mode, date, time, review, payment), so the
        // final step is 9 — not the legacy flow's 8.
        $component->call('submit')
            ->assertSet('step', 9)
            // The CTA carries the formatted amount, so this also proves
            // MoneyFormatter's currency-correct output reaches checkout.
            ->assertSee('Pay 499.00 INR');

        $bookingId = $component->get('bookingId');
        $this->assertNotNull($bookingId);

        $component->call('initiatePayment');
        $orderId = $component->get('paymentOrder')['order_id'];
        $this->assertSame('order_LW1', $orderId);

        $signature = $this->checkoutSignature($orderId, 'pay_LW1');
        $component->call('verifyPayment', $orderId, 'pay_LW1', $signature);

        // The callback is NON-AUTHORITATIVE. It records which provider
        // payment succeeded and nothing else — the booking stays unpaid
        // until a signed webhook settles it. This previously asserted
        // Paid/Confirmed, which is exactly the behaviour that produced
        // confirmed bookings with no receipt and no notifications.
        $booking = Booking::query()->findOrFail($bookingId);
        $obligation = BookingPayment::query()->where('booking_id', $booking->id)->sole();
        $attempt = Payment::query()->where('payable_id', $obligation->getKey())->sole();

        $this->assertSame('pay_LW1', $attempt->provider_payment_id);
        $this->assertSame(PaymentStatus::Pending, $attempt->status);
        $this->assertSame(BookingPaymentRecordStatus::Pending, $obligation->status);
        $this->assertTrue($booking->payment_status->isPayable());
        $this->assertNotSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(0, Invoice::query()->count());

        // The signed webhook is what settles, and it produces the whole
        // downstream chain the callback deliberately does not.
        $body = (string) json_encode([
            'event' => 'payment.captured',
            'payload' => ['payment' => ['entity' => [
                'id' => 'pay_LW1',
                'order_id' => $orderId,
                'amount' => 49900,
                'currency' => 'INR',
                'notes' => ['payment_reference' => $attempt->idempotency_key],
            ]]],
        ]);

        $this->call('POST', '/api/webhooks/bookings/payments/razorpay', [], [], [], [
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, 'webhook_secret'),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $body)->assertOk()->assertJsonPath('status', 'processed');

        $booking->refresh();
        $this->assertSame(BookingPaymentStatus::Paid, $booking->payment_status);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(PaymentStatus::Paid, $attempt->refresh()->status);
        $this->assertSame(1, Invoice::query()->count());
    }

    /**
     * The normal case: Razorpay's checkout reports success and, asked at
     * once, Razorpay confirms the order is paid. The booking must be
     * settled and CONFIRMED in this very request — through the same
     * settlement path the webhook uses, receipt included — and the
     * student must see "Booking confirmed", never a confirming screen.
     */
    public function test_a_captured_payment_confirms_immediately_on_the_checkout_callback(): void
    {
        $student = User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]);
        $this->withBillingCountry($student);
        $slot = CarbonImmutable::now('UTC')->addDays(3)->setTime(10, 0);

        $component = $this->navigateAcademicWizardToSlot(
            Livewire::actingAs($student)->test('frontend.booking.booking-wizard'),
            $this->academic,
            $slot,
        );
        $component->call('submit');
        $component->call('initiatePayment');
        $orderId = $component->get('paymentOrder')['order_id'];
        $this->razorpayReportsPaid($orderId);

        $component->call('verifyPayment', $orderId, 'pay_LW1', $this->checkoutSignature($orderId, 'pay_LW1'))
            ->assertSet('awaitingPaymentConfirmation', false)
            ->assertSee('Booking confirmed')
            ->assertDontSee('Payment submitted successfully')
            ->assertDontSee('Pay 499.00 INR securely');

        $booking = Booking::query()->findOrFail($component->get('bookingId'));
        $this->assertSame(BookingPaymentStatus::Paid, $booking->payment_status);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $obligation = BookingPayment::query()->where('booking_id', $booking->id)->sole();
        $attempt = Payment::query()->where('payable_id', $obligation->getKey())->sole();
        $this->assertSame(PaymentStatus::Paid, $attempt->status, 'settled through the real settlement path');
        $this->assertSame(BookingPaymentRecordStatus::Captured, $obligation->status);
        $this->assertSame(1, Invoice::query()->count(), 'the receipt chain fires exactly as for a webhook');
    }

    /** The provider says "not yet": the confirming screen shows, and a later poll re-asks the provider and confirms. */
    public function test_polling_re_asks_the_provider_and_confirms_once_it_reports_paid(): void
    {
        $student = User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]);
        $this->withBillingCountry($student);
        $slot = CarbonImmutable::now('UTC')->addDays(3)->setTime(10, 0);

        $component = $this->navigateAcademicWizardToSlot(
            Livewire::actingAs($student)->test('frontend.booking.booking-wizard'),
            $this->academic,
            $slot,
        );
        $component->call('submit');
        $component->call('initiatePayment');
        $orderId = $component->get('paymentOrder')['order_id'];

        // Not captured yet when the callback arrives.
        $component->call('verifyPayment', $orderId, 'pay_LW1', $this->checkoutSignature($orderId, 'pay_LW1'))
            ->assertSet('awaitingPaymentConfirmation', true)
            ->assertSee('Payment submitted successfully');
        $bookingId = $component->get('bookingId');
        $this->assertNotNull(BookingPayment::query()->where('booking_id', $bookingId)->sole()->last_synced_at, 'the provider was asked at completion');

        // Razorpay now reports paid, but a poll inside the re-check window only re-reads locally.
        $this->razorpayReportsPaid($orderId);
        $component->call('checkPaymentStatus')->assertSet('awaitingPaymentConfirmation', true);

        // After the window the poll asks Razorpay again and settles.
        $this->travel(BookingCheckoutCompletionService::PROVIDER_RECHECK_SECONDS + 1)->seconds();
        $component->call('checkPaymentStatus')
            ->assertSet('awaitingPaymentConfirmation', false)
            ->assertSee('Booking confirmed');

        $this->assertSame(BookingStatus::Confirmed, Booking::query()->findOrFail($component->get('bookingId'))->status);
    }

    /**
     * After the checkout callback the booking is verified but not yet
     * settled. The screen must say so and poll — never show a second
     * "Pay" button for money the provider has already accepted — and
     * must flip to confirmed on its own once the webhook settles.
     */
    public function test_the_payment_screen_shows_a_confirming_state_after_checkout_until_the_webhook_settles(): void
    {
        $student = User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]);
        $this->withBillingCountry($student);
        $slot = CarbonImmutable::now('UTC')->addDays(3)->setTime(10, 0);

        $component = $this->navigateAcademicWizardToSlot(
            Livewire::actingAs($student)->test('frontend.booking.booking-wizard'),
            $this->academic,
            $slot,
        );
        $component->call('submit')->assertSet('awaitingPaymentConfirmation', false)->assertSee('Complete your payment');
        $component->call('initiatePayment');
        $orderId = $component->get('paymentOrder')['order_id'];

        $component->call('verifyPayment', $orderId, 'pay_LW1', $this->checkoutSignature($orderId, 'pay_LW1'))
            ->assertSet('awaitingPaymentConfirmation', true)
            ->assertSee('Payment submitted successfully')
            ->assertSee('wire:poll.3s="checkPaymentStatus"', false)
            ->assertDontSee('Pay 499.00 INR securely')
            ->assertDontSee('Complete your payment');

        // Polling while nothing has changed keeps waiting — and keeps the Pay button hidden.
        $component->call('checkPaymentStatus')
            ->assertSet('awaitingPaymentConfirmation', true)
            ->assertDontSee('Pay 499.00 INR securely');

        $bookingId = $component->get('bookingId');
        $obligation = BookingPayment::query()->where('booking_id', $bookingId)->sole();
        $attempt = Payment::query()->where('payable_id', $obligation->getKey())->sole();
        $body = (string) json_encode([
            'event' => 'payment.captured',
            'payload' => ['payment' => ['entity' => [
                'id' => 'pay_LW1', 'order_id' => $orderId, 'amount' => 49900, 'currency' => 'INR',
                'notes' => ['payment_reference' => $attempt->idempotency_key],
            ]]],
        ]);
        $this->call('POST', '/api/webhooks/bookings/payments/razorpay', [], [], [], [
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, 'webhook_secret'),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $body)->assertOk();

        // The next poll observes settlement and the screen becomes the confirmed state.
        $component->call('checkPaymentStatus')
            ->assertSet('awaitingPaymentConfirmation', false)
            ->assertSee('Booking confirmed')
            ->assertDontSee('Payment submitted successfully');
    }

    /** A rejected callback never enters the confirming state — the Pay button stays available. */
    public function test_a_forged_callback_does_not_show_the_confirming_state(): void
    {
        $student = User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]);
        $this->withBillingCountry($student);
        $slot = CarbonImmutable::now('UTC')->addDays(3)->setTime(10, 0);

        $component = $this->navigateAcademicWizardToSlot(
            Livewire::actingAs($student)->test('frontend.booking.booking-wizard'),
            $this->academic,
            $slot,
        );
        $component->call('submit');
        $component->call('initiatePayment');
        $orderId = $component->get('paymentOrder')['order_id'];

        $component->call('verifyPayment', $orderId, 'pay_FORGED', 'not-the-real-signature')
            ->assertSet('awaitingPaymentConfirmation', false)
            ->assertSee('Pay 499.00 INR securely')
            ->assertDontSee('Payment submitted successfully');
    }

    /** Pressing Pay again (a retry after a failure) leaves any stale confirming state behind. */
    public function test_initiating_payment_resets_the_confirming_state(): void
    {
        $student = User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]);
        $this->withBillingCountry($student);
        $slot = CarbonImmutable::now('UTC')->addDays(3)->setTime(10, 0);

        $component = $this->navigateAcademicWizardToSlot(
            Livewire::actingAs($student)->test('frontend.booking.booking-wizard'),
            $this->academic,
            $slot,
        );
        $component->call('submit');
        $component->set('awaitingPaymentConfirmation', true);

        $component->call('initiatePayment')->assertSet('awaitingPaymentConfirmation', false);
    }

    public function test_booking_wizard_shows_safe_error_when_no_matrix_price_configured(): void
    {
        // Drives the actual wizard submit() flow (not the service layer
        // directly) so the UI-facing error path itself is proven, not
        // just BookingPriceCalculator's exception.
        StudentLessonPrice::query()->delete();

        $student = User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]);
        $this->withBillingCountry($student);

        $slot = CarbonImmutable::now('UTC')->addDays(3)->setTime(10, 0);

        $this->navigateAcademicWizardToSlot(
            Livewire::actingAs($student)->test('frontend.booking.booking-wizard'),
            $this->academic,
            $slot,
        )
            ->call('continueStage')
            ->call('submit')
            // Submission is refused on the review step (8 of 9) — the
            // student never reaches payment without a resolvable price.
            ->assertSet('step', 8)
            ->assertSee('price is not configured');

        $this->assertDatabaseMissing('bookings', ['instructor_id' => $this->teacher->id]);
    }

    public function test_razorpay_checkout_event_payload_has_only_public_fields(): void
    {
        // Proves the exact composition of the dispatched browser event
        // — order id, amount, currency, public key,
        // student name/email — and nothing else (no key_secret, no
        // webhook_secret, no raw gateway/admin metadata).
        $student = User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]);
        $this->withBillingCountry($student);

        $slot = CarbonImmutable::now('UTC')->addDays(3)->setTime(10, 0);

        $component = $this->navigateAcademicWizardToSlot(
            Livewire::actingAs($student)->test('frontend.booking.booking-wizard'),
            $this->academic,
            $slot,
        )
            ->call('submit')
            ->call('initiatePayment');

        $component->assertDispatched(
            'razorpay-checkout-ready',
            orderId: 'order_LW1',
            keyId: 'rzp_test_key_id',
            amountMinor: 49900,
            currency: 'INR',
            name: $student->name,
            email: $student->email,
        );
    }

    public function test_forged_signature_via_livewire_leaves_booking_unpaid(): void
    {
        // "Frontend success alone cannot mark booking paid" — drives
        // verifyPayment() with a bad signature through the actual
        // Livewire component a browser would call, not the provider class
        // directly (RazorpayCheckoutTest already covers that layer).
        $student = User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]);
        $this->withBillingCountry($student);

        $booking = app(StudentBookingServiceInterface::class)->book(new StudentBookingData(
            typeKey: 'paid_one_to_one',
            studentId: $student->id,
            teacherId: $this->teacher->id,
            startsAt: now('UTC')->addDays(4)->setTime(11, 0)->toImmutable(),
            subject: $this->academic['subject']->name,
            grade: 10,
        ));

        $component = Livewire::actingAs($student)
            ->test(BookingDetail::class, ['bookingId' => $booking->id])
            ->call('initiatePayment');

        $orderId = $component->get('paymentOrder')['order_id'];
        $component->call('verifyPayment', $orderId, 'pay_FORGED', 'not-the-real-signature');

        $this->assertSame(BookingPaymentStatus::Pending, $booking->refresh()->payment_status);
        $this->assertSame(BookingStatus::Pending, $booking->status);
    }

    public function test_student_can_retry_payment_from_booking_history(): void
    {
        $student = User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]);
        $this->withBillingCountry($student);

        $booking = app(StudentBookingServiceInterface::class)->book(new StudentBookingData(
            typeKey: 'paid_one_to_one',
            studentId: $student->id,
            teacherId: $this->teacher->id,
            startsAt: now('UTC')->addDays(4)->setTime(11, 0)->toImmutable(),
            subject: $this->academic['subject']->name,
            grade: 10,
        ));

        $component = Livewire::actingAs($student)
            ->test(BookingDetail::class, ['bookingId' => $booking->id])
            ->call('initiatePayment');

        $orderId = $component->get('paymentOrder')['order_id'];
        $signature = $this->checkoutSignature($orderId, 'pay_LW2');
        $component->call('verifyPayment', $orderId, 'pay_LW2', $signature);

        // Retry-from-dashboard uses the same non-authoritative callback:
        // the provider payment id is recorded, settlement waits for the
        // signed webhook. (The full callback -> webhook -> invoice chain
        // is proven in test_student_can_pay_via_the_booking_wizard.)
        $obligation = BookingPayment::query()->where('booking_id', $booking->id)->sole();
        $attempt = Payment::query()->where('payable_id', $obligation->getKey())
            ->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();

        $this->assertSame('pay_LW2', $attempt->provider_payment_id);
        $this->assertSame(PaymentStatus::Pending, $attempt->status);
        $this->assertTrue($booking->refresh()->payment_status->isPayable());
        $this->assertNotSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(0, Invoice::query()->count());
    }

    /** My Bookings uses the same completion service: a captured payment confirms in the callback. */
    public function test_a_captured_payment_confirms_immediately_from_booking_history(): void
    {
        $student = User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]);
        $this->withBillingCountry($student);

        $booking = app(StudentBookingServiceInterface::class)->book(new StudentBookingData(
            typeKey: 'paid_one_to_one',
            studentId: $student->id,
            teacherId: $this->teacher->id,
            startsAt: now('UTC')->addDays(4)->setTime(11, 0)->toImmutable(),
            subject: $this->academic['subject']->name,
            grade: 10,
        ));

        $component = Livewire::actingAs($student)
            ->test(BookingDetail::class, ['bookingId' => $booking->id])
            ->call('initiatePayment');
        $orderId = $component->get('paymentOrder')['order_id'];
        $this->razorpayReportsPaid($orderId);

        $component->call('verifyPayment', $orderId, 'pay_LW3', $this->checkoutSignature($orderId, 'pay_LW3'))
            ->assertSet('awaitingPaymentConfirmation', false)
            ->assertDontSee('Payment submitted successfully')
            ->assertDontSee('Pay now');

        $booking->refresh();
        $this->assertSame(BookingPaymentStatus::Paid, $booking->payment_status);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(1, Invoice::query()->count());
    }

    /** My Bookings shows the confirming state, without a Pay button, while the provider has not captured. */
    public function test_booking_history_shows_a_confirming_state_while_capture_is_pending(): void
    {
        $student = User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]);
        $this->withBillingCountry($student);

        $booking = app(StudentBookingServiceInterface::class)->book(new StudentBookingData(
            typeKey: 'paid_one_to_one',
            studentId: $student->id,
            teacherId: $this->teacher->id,
            startsAt: now('UTC')->addDays(4)->setTime(11, 0)->toImmutable(),
            subject: $this->academic['subject']->name,
            grade: 10,
        ));

        $component = Livewire::actingAs($student)
            ->test(BookingDetail::class, ['bookingId' => $booking->id])
            ->call('initiatePayment');
        $orderId = $component->get('paymentOrder')['order_id'];

        $component->call('verifyPayment', $orderId, 'pay_LW4', $this->checkoutSignature($orderId, 'pay_LW4'))
            ->assertSet('awaitingPaymentConfirmation', true)
            ->assertSee('Payment submitted successfully')
            ->assertDontSee('Pay now');
    }

    /**
     * The state is derived on the server: a reload, a new device or a
     * fresh login after a verified callback shows the confirming state
     * and never a Pay button over money that may already have moved.
     */
    public function test_the_confirming_state_survives_a_reload_of_the_booking_page(): void
    {
        $student = User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]);
        $this->withBillingCountry($student);

        $booking = app(StudentBookingServiceInterface::class)->book(new StudentBookingData(
            typeKey: 'paid_one_to_one',
            studentId: $student->id,
            teacherId: $this->teacher->id,
            startsAt: now('UTC')->addDays(4)->setTime(11, 0)->toImmutable(),
            subject: $this->academic['subject']->name,
            grade: 10,
        ));

        $first = Livewire::actingAs($student)
            ->test(BookingDetail::class, ['bookingId' => $booking->id])
            ->call('initiatePayment');
        $orderId = $first->get('paymentOrder')['order_id'];
        $first->call('verifyPayment', $orderId, 'pay_LW5', $this->checkoutSignature($orderId, 'pay_LW5'))
            ->assertSet('awaitingPaymentConfirmation', true);

        // A brand-new component instance — what a reload is.
        $reloaded = Livewire::actingAs($student)->test(BookingDetail::class, ['bookingId' => $booking->id]);

        $reloaded
            ->assertSet('awaitingPaymentConfirmation', true)
            ->assertSet('paymentConfirmationState', 'awaiting_capture')
            ->assertSee('Payment submitted successfully')
            ->assertSee("don't make another payment")
            ->assertDontSee('Pay now')
            ->assertSee('wire:poll.3s="checkPaymentStatus"', false);

        // Even a direct call to initiatePayment cannot open a second checkout.
        $this->razorpayMock->shouldReceive('createOrder')->never();
        $reloaded->call('initiatePayment')
            ->assertSet('awaitingPaymentConfirmation', true)
            ->assertDontSee('Pay now');
        $this->assertSame(1, Payment::query()->count(), 'no second attempt was opened');
    }

    /** The provider being down is told to the student honestly — and still never as "pay again". */
    public function test_an_unreachable_provider_shows_its_own_message_and_no_pay_button(): void
    {
        $student = User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]);
        $this->withBillingCountry($student);

        $booking = app(StudentBookingServiceInterface::class)->book(new StudentBookingData(
            typeKey: 'paid_one_to_one',
            studentId: $student->id,
            teacherId: $this->teacher->id,
            startsAt: now('UTC')->addDays(4)->setTime(11, 0)->toImmutable(),
            subject: $this->academic['subject']->name,
            grade: 10,
        ));

        $component = Livewire::actingAs($student)
            ->test(BookingDetail::class, ['bookingId' => $booking->id])
            ->call('initiatePayment');
        $orderId = $component->get('paymentOrder')['order_id'];

        $this->razorpayMock->shouldReceive('fetchPayment')->andThrow(new GatewayRequestException('timeout'));

        $component->call('verifyPayment', $orderId, 'pay_LW6', $this->checkoutSignature($orderId, 'pay_LW6'))
            ->assertSet('awaitingPaymentConfirmation', true)
            ->assertSet('paymentConfirmationState', 'provider_unreachable')
            ->assertSee('trouble confirming the transaction')
            ->assertSee("Don't make another payment")
            ->assertDontSee('Pay now')
            ->assertDontSee('timeout');
    }

    /** The wizard, too, refuses to open a second checkout while one is being confirmed. */
    public function test_the_wizard_does_not_open_a_second_checkout_while_confirming(): void
    {
        $student = User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]);
        $this->withBillingCountry($student);
        $slot = CarbonImmutable::now('UTC')->addDays(3)->setTime(10, 0);

        $component = $this->navigateAcademicWizardToSlot(
            Livewire::actingAs($student)->test('frontend.booking.booking-wizard'),
            $this->academic,
            $slot,
        );
        $component->call('submit');
        $component->call('initiatePayment');
        $orderId = $component->get('paymentOrder')['order_id'];
        $component->call('verifyPayment', $orderId, 'pay_LW7', $this->checkoutSignature($orderId, 'pay_LW7'))
            ->assertSet('awaitingPaymentConfirmation', true);

        $component->call('initiatePayment')
            ->assertSet('awaitingPaymentConfirmation', true)
            ->assertSet('paymentConfirmationState', 'awaiting_capture')
            ->assertNotDispatched('razorpay-checkout-ready')
            ->assertDontSee('Pay 499.00 INR securely');
    }

    public function test_student_cannot_pay_for_another_students_booking(): void
    {
        $owner = User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]);
        $this->withBillingCountry($owner);
        $intruder = User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]);

        $booking = app(StudentBookingServiceInterface::class)->book(new StudentBookingData(
            typeKey: 'paid_one_to_one',
            studentId: $owner->id,
            teacherId: $this->teacher->id,
            startsAt: now('UTC')->addDays(5)->setTime(12, 0)->toImmutable(),
            subject: $this->academic['subject']->name,
            grade: 10,
        ));

        // The intruder can never open the owner's booking in the first
        // place — the detail page authorizes 'view' before it renders,
        // so there is no state for any payment call to act on.
        $this->actingAs($intruder)
            ->get(route('dashboard.my-bookings.show', $booking))
            ->assertForbidden();

        $this->assertSame(BookingPaymentStatus::Pending, $booking->refresh()->payment_status);
    }
}

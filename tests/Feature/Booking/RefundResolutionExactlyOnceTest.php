<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Booking\Contracts\BookingPaymentServiceInterface;
use App\Booking\Contracts\RazorpayGatewayClient;
use App\Booking\Enums\BookingPaymentReconciliationIssueType;
use App\Booking\Enums\BookingPaymentStatus;
use App\Booking\Enums\BookingStatus;
use App\Booking\Exceptions\BookingException;
use App\Booking\Exceptions\GatewayRequestException;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\BookingPaymentReconciliationIssue;
use App\Models\BookingType;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use App\Payments\Enums\PaymentStatus;
use App\Settings\PaymentGatewaySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Exactly-once financial resolution for a booking refund.
 *
 * Two routes can resolve the same captured payment — a wallet credit
 * (the normal path) and a provider reversal (the exception path). The
 * invariant is not merely "never twice": it is **exactly once**. Zero is
 * just as wrong as two, because the student is owed their money either
 * way, and a refund that silently never happens is the failure nobody
 * notices.
 *
 * The bug these tests were written for: refundViaProvider() claimed the
 * payment BEFORE establishing it could actually refund it. A provider
 * refund that was never possible — no settled attempt, or a provider
 * whose charges cannot be reversed — still wrote
 * `provider_refund_pending`, which made a concurrent wallet refund
 * refuse. The claim was released afterwards, but the wallet attempt was
 * already gone and nothing retried it, so the booking ended up with NO
 * refund at all.
 */
class RefundResolutionExactlyOnceTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::query()->firstOrCreate(['code' => 'INR'], [
            'name' => 'Indian Rupee', 'symbol' => 'Rs', 'numeric_code' => '356',
            'minor_units' => 2, 'status' => 'active', 'sort_order' => 1,
        ]);

        Role::findOrCreate('student', 'web');

        $this->student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->student->assignRole('student');
        $this->actor = User::factory()->create(['status' => User::STATUS_ACTIVE]);

        $settings = app(PaymentGatewaySettings::class);
        $settings->razorpay_key_id = 'rzp_test_key';
        $settings->razorpay_key_secret = Crypt::encryptString('secret');
        $settings->save();
    }

    /**
     * A paid booking. With $provider set, it also has the settled
     * Payment attempt a real provider charge leaves behind — which is
     * what makes a provider reversal possible at all.
     */
    private function paidBooking(?string $provider = 'razorpay'): Booking
    {
        $type = BookingType::factory()->create(['key' => 'paid_one_to_one', 'is_paid' => true]);

        $booking = Booking::factory()->create([
            'student_id' => $this->student->id,
            'booking_type_id' => $type->id,
            'status' => BookingStatus::Confirmed,
            'payment_status' => BookingPaymentStatus::Paid,
            'price' => 499,
            'currency' => 'INR',
            'payment_reference' => 'PAY-'.strtoupper(Str::random(12)),
        ]);

        $obligation = BookingPayment::factory()->captured()->create([
            'booking_id' => $booking->id,
            'user_id' => $this->student->id,
            'amount_minor' => 49900,
            'currency_code' => 'INR',
        ]);

        if ($provider !== null) {
            Payment::query()->create([
                'payable_type' => BookingPayment::PAYABLE_TYPE,
                'payable_id' => $obligation->id,
                'user_id' => $this->student->id,
                'provider' => $provider,
                'provider_payment_id' => 'pay_'.strtoupper(Str::random(14)),
                'status' => PaymentStatus::Paid->value,
                'amount_minor' => 49900,
                'currency_code' => 'INR',
                'idempotency_key' => 'ATT-'.strtoupper(Str::random(12)),
                'paid_at' => now(),
            ]);
        }

        return $booking;
    }

    private function payments(): BookingPaymentServiceInterface
    {
        return app(BookingPaymentServiceInterface::class);
    }

    private function resolutionOf(Booking $booking): ?string
    {
        return BookingPayment::query()->where('booking_id', $booking->id)->sole()->metadata['refund_resolution'] ?? null;
    }

    private function walletBalance(): int
    {
        return (int) (Wallet::query()
            ->where('user_id', $this->student->id)
            ->where('currency_code', 'INR')
            ->value('balance_minor') ?? 0);
    }

    private function expectGatewayRefund(): void
    {
        $client = Mockery::mock(RazorpayGatewayClient::class);
        $client->shouldReceive('refundPayment')->once()->andReturn(['id' => 'rfnd_1']);
        $this->instance(RazorpayGatewayClient::class, $client);
    }

    private function expectGatewayRefundToFail(): void
    {
        $client = Mockery::mock(RazorpayGatewayClient::class);
        $client->shouldReceive('refundPayment')->once()->andThrow(new GatewayRequestException('gateway down'));
        $this->instance(RazorpayGatewayClient::class, $client);
    }

    /** Strict: any gateway call at all fails the test. */
    private function forbidGatewayCalls(): void
    {
        $this->instance(RazorpayGatewayClient::class, Mockery::mock(RazorpayGatewayClient::class));
    }

    // ── One winner, whichever route gets there first ───────────────────────

    public function test_wallet_path_wins_and_the_provider_path_is_then_refused(): void
    {
        $this->forbidGatewayCalls();
        $booking = $this->paidBooking();

        $this->payments()->refundToWallet($booking, 'Cancelled.');

        $this->assertSame('wallet_credited', $this->resolutionOf($booking));
        $this->assertSame(49900, $this->walletBalance());

        // The loser refuses rather than reversing the charge as well.
        $this->expectException(BookingException::class);
        $this->payments()->refundViaProvider($booking->refresh(), $this->actor, 'Manual.');
    }

    public function test_provider_path_wins_and_the_wallet_path_is_then_refused(): void
    {
        $this->expectGatewayRefund();
        $booking = $this->paidBooking();

        $this->payments()->refundViaProvider($booking, $this->actor, 'Manual.');

        $this->assertSame('provider_refunded', $this->resolutionOf($booking));
        // The money went back to the card, so nothing was credited.
        $this->assertSame(0, $this->walletBalance());

        $this->expectException(BookingException::class);
        $this->payments()->refundToWallet($booking->refresh(), 'Cancelled.');
    }

    // ── The bug: a doomed provider refund must not strand the wallet ───────

    public function test_an_unrefundable_provider_payment_never_blocks_the_wallet_route(): void
    {
        // A booking with no settled provider attempt (a wallet- or
        // simulator-settled payment). A provider reversal is impossible.
        $this->forbidGatewayCalls();
        $booking = $this->paidBooking(provider: null);

        try {
            $this->payments()->refundViaProvider($booking, $this->actor, 'Manual.');
            $this->fail('a provider refund with no settled attempt must be refused');
        } catch (BookingException) {
            // expected
        }

        // The crux: the failed attempt left NO claim behind, so the
        // student's refund is still reachable by the route that works.
        $this->assertNull($this->resolutionOf($booking->refresh()));

        $this->payments()->refundToWallet($booking->refresh(), 'Cancelled.');

        $this->assertSame('wallet_credited', $this->resolutionOf($booking));
        $this->assertSame(49900, $this->walletBalance());
    }

    public function test_an_unsupported_provider_never_blocks_the_wallet_route(): void
    {
        // Settled through the simulator: a real attempt exists, but its
        // charge cannot be reversed automatically.
        $this->forbidGatewayCalls();
        $booking = $this->paidBooking(provider: 'fake');

        try {
            $this->payments()->refundViaProvider($booking, $this->actor, 'Manual.');
            $this->fail('an unsupported provider must be refused');
        } catch (BookingException $exception) {
            $this->assertStringContainsString('cannot be refunded automatically', $exception->getMessage());
        }

        $this->assertNull($this->resolutionOf($booking->refresh()));

        $this->payments()->refundToWallet($booking->refresh(), 'Cancelled.');
        $this->assertSame(49900, $this->walletBalance());
    }

    // ── Repeats and replays ────────────────────────────────────────────────

    public function test_repeating_the_same_wallet_refund_credits_once(): void
    {
        $this->forbidGatewayCalls();
        $booking = $this->paidBooking();

        $this->payments()->refundToWallet($booking, 'Cancelled.');

        try {
            $this->payments()->refundToWallet($booking->refresh(), 'Cancelled.');
            $this->fail('a repeated refund must be refused');
        } catch (BookingException) {
            // expected
        }

        $this->assertSame(49900, $this->walletBalance());
        $this->assertSame(1, WalletLedgerEntry::query()->where('source_type', BookingPayment::class)->count());
    }

    public function test_repeating_the_same_provider_refund_calls_the_gateway_once(): void
    {
        // The mock allows exactly one call, so a second reversal fails
        // the test rather than quietly refunding twice.
        $this->expectGatewayRefund();
        $booking = $this->paidBooking();

        $this->payments()->refundViaProvider($booking, $this->actor, 'Manual.');

        $this->expectException(BookingException::class);
        $this->payments()->refundViaProvider($booking->refresh(), $this->actor, 'Manual.');
    }

    public function test_an_already_refunded_booking_refuses_both_routes(): void
    {
        $this->forbidGatewayCalls();
        $booking = $this->paidBooking();

        $this->payments()->refundToWallet($booking, 'Cancelled.');
        $booking->refresh();

        $this->assertSame(BookingPaymentStatus::Refunded, $booking->payment_status);

        foreach (['wallet', 'provider'] as $route) {
            try {
                $route === 'wallet'
                    ? $this->payments()->refundToWallet($booking->refresh(), 'Again.')
                    : $this->payments()->refundViaProvider($booking->refresh(), $this->actor, 'Again.');

                $this->fail("the {$route} route must refuse an already-refunded booking");
            } catch (BookingException) {
                // expected
            }
        }

        $this->assertSame(49900, $this->walletBalance());
    }

    // ── The pending claim ──────────────────────────────────────────────────

    public function test_a_standing_provider_claim_blocks_the_wallet_route(): void
    {
        // While a provider reversal is genuinely in flight, the wallet
        // route must not also pay out — this is the half of the
        // invariant that prevents a DOUBLE refund.
        $this->forbidGatewayCalls();
        $booking = $this->paidBooking();

        $payment = BookingPayment::query()->where('booking_id', $booking->id)->sole();
        $payment->forceFill(['metadata' => [...($payment->metadata ?? []), 'refund_resolution' => 'provider_refund_pending']])->save();

        try {
            $this->payments()->refundToWallet($booking->refresh(), 'Cancelled.');
            $this->fail('the wallet route must not pay out while a provider refund is in flight');
        } catch (BookingException $exception) {
            $this->assertStringContainsString('provider_refund_pending', $exception->getMessage());
        }

        $this->assertSame(0, $this->walletBalance());
    }

    // ── Failure and retry ──────────────────────────────────────────────────

    public function test_a_failed_gateway_refund_releases_its_claim_and_is_surfaced(): void
    {
        $this->expectGatewayRefundToFail();
        $booking = $this->paidBooking();

        try {
            $this->payments()->refundViaProvider($booking, $this->actor, 'Manual.');
            $this->fail('a failed gateway refund must surface');
        } catch (BookingException) {
            // expected
        }

        // Released, so the money is still reachable...
        $this->assertNull($this->resolutionOf($booking->refresh()));
        $this->assertSame(BookingPaymentStatus::Paid, $booking->refresh()->payment_status);

        // ...and raised, because a concurrent wallet attempt may have
        // been refused while the claim stood and nothing will retry it.
        $this->assertDatabaseHas('booking_payment_reconciliation_issues', [
            'type' => BookingPaymentReconciliationIssueType::RefundStatusMismatch->value,
        ]);
    }

    public function test_the_wallet_route_still_works_after_a_failed_gateway_refund(): void
    {
        $this->expectGatewayRefundToFail();
        $booking = $this->paidBooking();

        try {
            $this->payments()->refundViaProvider($booking, $this->actor, 'Manual.');
        } catch (BookingException) {
            // expected
        }

        $this->payments()->refundToWallet($booking->refresh(), 'Cancelled.');

        $this->assertSame('wallet_credited', $this->resolutionOf($booking));
        $this->assertSame(49900, $this->walletBalance());
    }

    public function test_a_retried_provider_refund_succeeds_after_a_transient_failure(): void
    {
        $client = Mockery::mock(RazorpayGatewayClient::class);
        $client->shouldReceive('refundPayment')->once()->andThrow(new GatewayRequestException('transient'));
        $client->shouldReceive('refundPayment')->once()->andReturn(['id' => 'rfnd_1']);
        $this->instance(RazorpayGatewayClient::class, $client);

        $booking = $this->paidBooking();

        try {
            $this->payments()->refundViaProvider($booking, $this->actor, 'Manual.');
        } catch (BookingException) {
            // expected
        }

        $this->payments()->refundViaProvider($booking->refresh(), $this->actor, 'Manual.');

        $this->assertSame('provider_refunded', $this->resolutionOf($booking));
        $this->assertSame(BookingPaymentStatus::Refunded, $booking->refresh()->payment_status);
        // The retry reversed the charge; it never also credited the wallet.
        $this->assertSame(0, $this->walletBalance());
    }

    public function test_exactly_one_reconciliation_issue_per_failed_claim(): void
    {
        $this->expectGatewayRefundToFail();
        $booking = $this->paidBooking();

        try {
            $this->payments()->refundViaProvider($booking, $this->actor, 'Manual.');
        } catch (BookingException) {
            // expected
        }

        $this->assertSame(
            1,
            BookingPaymentReconciliationIssue::query()
                ->where('type', BookingPaymentReconciliationIssueType::RefundStatusMismatch)
                ->count(),
        );
    }
}

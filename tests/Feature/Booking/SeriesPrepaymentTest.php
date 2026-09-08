<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Booking\Enums\BookingPaymentRecordStatus;
use App\Booking\Enums\BookingPaymentStatus;
use App\Booking\Enums\BookingSeriesStatus;
use App\Booking\Enums\BookingStatus;
use App\Booking\Enums\RecurrenceEndCondition;
use App\Booking\Enums\RecurrenceFrequency;
use App\Booking\Exceptions\BookingException;
use App\Booking\Services\BookingSeriesPrepaymentService;
use App\Listeners\Booking\SettleSeriesPrepaymentOnWalletRechargeSucceeded;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\BookingSeries;
use App\Models\BookingType;
use App\Models\Country;
use App\Models\Currency;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Wallet;
use App\Models\WalletRecharge;
use App\Settings\FeatureSettings;
use App\Wallet\Enums\WalletLedgerEntryType;
use App\Wallet\Enums\WalletRechargeStatus;
use App\Wallet\Events\WalletRechargeSucceeded;
use App\Wallet\Services\WalletLedgerService;
use App\Wallet\Services\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Paying for a whole repeating schedule in one go.
 *
 * The behaviour these lock down is not "a payment succeeds" — the
 * single-booking wallet path already guarantees that. It is that
 * batching changes ONLY the number of times a student is asked for
 * money: each class keeps its own price, its own reservation and its
 * own payment record, a class that fails leaves the others paid and the
 * money still in the balance, and nothing is ever collected for a class
 * that does not exist yet.
 */
class SeriesPrepaymentTest extends TestCase
{
    use RefreshDatabase;

    private BookingType $paidType;

    private User $instructor;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::query()->firstOrCreate(['code' => 'INR'], [
            'name' => 'Indian Rupee', 'symbol' => 'Rs', 'numeric_code' => '356',
            'minor_units' => 2, 'status' => 'active', 'sort_order' => 1,
        ]);
        Currency::query()->firstOrCreate(['code' => 'USD'], [
            'name' => 'US Dollar', 'symbol' => '$', 'numeric_code' => '840',
            'minor_units' => 2, 'status' => 'active', 'sort_order' => 2,
        ]);

        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        app(FeatureSettings::class)->wallet_enabled = true;

        $this->paidType = BookingType::factory()->create(['key' => 'paid_one_to_one', 'is_paid' => true]);
        $this->instructor = User::factory()->create(['status' => User::STATUS_ACTIVE]);
    }

    private function student(): User
    {
        $student = User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]);
        $country = Country::factory()->create(['status' => 'active']);
        UserProfile::updateOrCreate(['user_id' => $student->id], ['country_id' => $country->id]);

        return $student;
    }

    /** A schedule with $classes reserved, unpaid classes. */
    private function series(User $student, int $classes = 3, float $price = 499.00, string $currency = 'INR'): BookingSeries
    {
        $start = CarbonImmutable::now('UTC')->addDays(3)->setTime(10, 0);

        $series = BookingSeries::query()->create([
            'booking_type_id' => $this->paidType->id,
            'student_id' => $student->id,
            'instructor_id' => $this->instructor->id,
            'status' => BookingSeriesStatus::Active,
            'frequency' => RecurrenceFrequency::Weekly,
            'repeat_interval' => 1,
            'weekdays' => [(int) $start->dayOfWeek],
            'start_date' => $start->toDateString(),
            'time_of_day' => $start->format('H:i:s'),
            'duration_minutes' => 60,
            'timezone' => 'UTC',
            'student_timezone' => 'UTC',
            'end_condition' => RecurrenceEndCondition::AfterCount,
            'occurrence_count' => $classes,
        ]);

        for ($i = 0; $i < $classes; $i++) {
            $startsAt = $start->addWeeks($i);

            Booking::factory()->create([
                'student_id' => $student->id,
                'instructor_id' => $this->instructor->id,
                'booking_type_id' => $this->paidType->id,
                'booking_series_id' => $series->id,
                'series_occurrence_date' => $startsAt->toDateString(),
                'status' => BookingStatus::Pending,
                'payment_status' => BookingPaymentStatus::Pending,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->addMinutes(60),
                'price' => $price,
                'currency' => $currency,
                'reserved_until' => CarbonImmutable::now('UTC')->addMinutes(30),
            ]);
        }

        return $series->refresh();
    }

    private function fundWallet(User $student, int $amountMinor, string $currency = 'INR'): Wallet
    {
        $wallet = app(WalletService::class)->getOrCreateWallet($student, $currency, $student);
        app(WalletLedgerService::class)->credit($wallet, $amountMinor, WalletLedgerEntryType::PromotionalCredit, $student);

        return $wallet->fresh();
    }

    private function prepayments(): BookingSeriesPrepaymentService
    {
        return app(BookingSeriesPrepaymentService::class);
    }

    // ── The quote ──────────────────────────────────────────────────────────

    public function test_the_quote_totals_every_reserved_class(): void
    {
        $student = $this->student();
        $series = $this->series($student, 3);

        $quote = $this->prepayments()->quote($series, $student);

        $this->assertSame(3, $quote->count());
        $this->assertSame(149700, $quote->totalMinor);
        $this->assertSame('INR', $quote->currencyCode);
        $this->assertTrue($quote->isPayable());
    }

    public function test_the_shortfall_is_exactly_the_gap_never_more(): void
    {
        // The platform should not end up holding more of a student's
        // money than the thing they are buying costs.
        $student = $this->student();
        $series = $this->series($student, 3);
        $this->fundWallet($student, 50000);

        $quote = $this->prepayments()->quote($series, $student);

        $this->assertSame(50000, $quote->walletBalanceMinor);
        $this->assertSame(99700, $quote->shortfallMinor);
        $this->assertFalse($quote->coveredByWallet());
    }

    public function test_a_balance_that_already_covers_the_bill_needs_no_checkout(): void
    {
        $student = $this->student();
        $series = $this->series($student, 3);
        $this->fundWallet($student, 200000);

        $quote = $this->prepayments()->quote($series, $student);

        $this->assertSame(0, $quote->shortfallMinor);
        $this->assertTrue($quote->coveredByWallet());
    }

    public function test_already_paid_classes_are_not_quoted_again(): void
    {
        $student = $this->student();
        $series = $this->series($student, 3);

        $series->bookings()->orderBy('starts_at')->first()
            ->forceFill(['payment_status' => BookingPaymentStatus::Paid])->save();

        $quote = $this->prepayments()->quote($series, $student);

        $this->assertSame(2, $quote->count());
        $this->assertSame(99800, $quote->totalMinor);
    }

    public function test_cancelled_classes_are_not_quoted(): void
    {
        $student = $this->student();
        $series = $this->series($student, 3);

        $series->bookings()->orderBy('starts_at')->first()
            ->forceFill(['status' => BookingStatus::Cancelled])->save();

        $this->assertSame(2, $this->prepayments()->quote($series, $student)->count());
    }

    public function test_a_mixed_currency_batch_is_refused_rather_than_half_settled(): void
    {
        // Should never happen — one schedule is one instructor at one
        // price — but a half-settled batch is far worse than a refusal.
        $student = $this->student();
        $series = $this->series($student, 2);

        $series->bookings()->orderBy('starts_at')->first()
            ->forceFill(['currency' => 'USD'])->save();

        $quote = $this->prepayments()->quote($series->refresh(), $student);

        $this->assertFalse($quote->isPayable());
        $this->assertStringContainsString('different currencies', (string) $quote->blockedReason);
    }

    public function test_a_wallet_in_another_currency_blocks_the_batch(): void
    {
        $student = $this->student();
        $series = $this->series($student, 2);
        $this->fundWallet($student, 100000, 'USD');

        $quote = $this->prepayments()->quote($series, $student);

        $this->assertFalse($quote->isPayable());
        $this->assertStringContainsString('different currency', (string) $quote->blockedReason);
    }

    public function test_another_students_schedule_cannot_be_quoted_or_paid(): void
    {
        $owner = $this->student();
        $series = $this->series($owner, 2);
        $stranger = $this->student();

        $this->expectException(BookingException::class);
        $this->prepayments()->quote($series, $stranger);
    }

    // ── Settling ───────────────────────────────────────────────────────────

    public function test_one_action_pays_every_class_and_each_keeps_its_own_payment_record(): void
    {
        $student = $this->student();
        $series = $this->series($student, 3);
        $this->fundWallet($student, 200000);

        $result = $this->prepayments()->settleFromWallet($series, $student);

        $this->assertTrue($result->allPaid());
        $this->assertSame(3, $result->paidCount());

        // Per-class semantics survive: three classes, three payment
        // records, three prices — only the number of checkouts changed.
        $this->assertSame(3, $series->bookings()->where('payment_status', BookingPaymentStatus::Paid)->count());
        $this->assertSame(3, BookingPayment::query()
            ->whereIn('booking_id', $series->bookings()->pluck('id'))
            ->where('status', BookingPaymentRecordStatus::Captured)
            ->count());

        // Exactly the bill was taken, not a penny more.
        $this->assertSame(200000 - 149700, (int) Wallet::query()->where('user_id', $student->id)->value('available_balance_minor'));
    }

    public function test_classes_are_settled_earliest_first_so_a_short_balance_secures_the_soonest(): void
    {
        $student = $this->student();
        $series = $this->series($student, 3);
        // Enough for two of the three.
        $this->fundWallet($student, 100000);

        $result = $this->prepayments()->settleFromWallet($series, $student);

        $this->assertSame(2, $result->paidCount());
        $this->assertCount(1, $result->failures);

        $paid = $series->bookings()->where('payment_status', BookingPaymentStatus::Paid)->orderBy('starts_at')->get();
        $unpaid = $series->bookings()->where('payment_status', BookingPaymentStatus::Pending)->get();

        $this->assertCount(2, $paid);
        $this->assertCount(1, $unpaid);
        $this->assertTrue($paid->last()->starts_at->lessThan($unpaid->first()->starts_at));
    }

    public function test_a_failed_class_leaves_the_others_paid_and_the_money_in_the_balance(): void
    {
        $student = $this->student();
        $series = $this->series($student, 3);
        $this->fundWallet($student, 200000);

        // One class loses its slot while the student is at the gateway.
        $doomed = $series->bookings()->orderByDesc('starts_at')->first();
        $doomed->forceFill(['status' => BookingStatus::Cancelled])->save();

        $result = $this->prepayments()->settleFromWallet($series->refresh(), $student);

        $this->assertSame(2, $result->paidCount());
        // Only the two that could be paid were charged for.
        $this->assertSame(200000 - 99800, (int) Wallet::query()->where('user_id', $student->id)->value('available_balance_minor'));
    }

    public function test_paying_twice_does_not_charge_twice(): void
    {
        $student = $this->student();
        $series = $this->series($student, 3);
        $this->fundWallet($student, 300000);

        $this->prepayments()->settleFromWallet($series, $student);
        $balanceAfterFirst = (int) Wallet::query()->where('user_id', $student->id)->value('available_balance_minor');

        // A double click, a retried job, a refreshed tab.
        $second = $this->prepayments()->settleFromWallet($series->refresh(), $student);

        $this->assertSame(0, $second->paidCount(), 'nothing is left to pay for');
        $this->assertSame($balanceAfterFirst, (int) Wallet::query()->where('user_id', $student->id)->value('available_balance_minor'));
    }

    // ── Finishing what the student started ─────────────────────────────────

    public function test_a_prepayment_top_up_settles_the_classes_when_it_lands(): void
    {
        // The student closed the tab at the gateway. The webhook still
        // has to finish the job they paid for.
        $student = $this->student();
        $series = $this->series($student, 3);
        $wallet = $this->fundWallet($student, 149700);

        $recharge = WalletRecharge::query()->create([
            'wallet_id' => $wallet->id,
            'user_id' => $student->id,
            'amount_minor' => 149700,
            'currency_code' => 'INR',
            'status' => WalletRechargeStatus::Succeeded,
            'reference' => 'WRCH-TEST00000001',
            'metadata' => [
                'purpose' => BookingSeriesPrepaymentService::PURPOSE,
                'booking_series_id' => (string) $series->id,
                'booking_ids' => $series->bookings()->pluck('id')->all(),
            ],
        ]);

        app(SettleSeriesPrepaymentOnWalletRechargeSucceeded::class)
            ->handle(new WalletRechargeSucceeded($recharge));

        $this->assertSame(3, $series->bookings()->where('payment_status', BookingPaymentStatus::Paid)->count());
    }

    public function test_an_ordinary_top_up_is_never_spent_on_the_students_behalf(): void
    {
        // The line that keeps this from being automatic charging: money
        // added for no stated purpose stays where the student put it.
        $student = $this->student();
        $series = $this->series($student, 3);
        $wallet = $this->fundWallet($student, 300000);

        $recharge = WalletRecharge::query()->create([
            'wallet_id' => $wallet->id,
            'user_id' => $student->id,
            'amount_minor' => 300000,
            'currency_code' => 'INR',
            'status' => WalletRechargeStatus::Succeeded,
            'reference' => 'WRCH-TEST00000002',
            'metadata' => null,
        ]);

        app(SettleSeriesPrepaymentOnWalletRechargeSucceeded::class)
            ->handle(new WalletRechargeSucceeded($recharge));

        $this->assertSame(3, $series->bookings()->where('payment_status', BookingPaymentStatus::Pending)->count());
        $this->assertSame(300000, (int) Wallet::query()->where('user_id', $student->id)->value('available_balance_minor'));
    }

    public function test_a_redelivered_settlement_event_does_not_charge_twice(): void
    {
        $student = $this->student();
        $series = $this->series($student, 3);
        $wallet = $this->fundWallet($student, 200000);

        $recharge = WalletRecharge::query()->create([
            'wallet_id' => $wallet->id,
            'user_id' => $student->id,
            'amount_minor' => 149700,
            'currency_code' => 'INR',
            'status' => WalletRechargeStatus::Succeeded,
            'reference' => 'WRCH-TEST00000003',
            'metadata' => [
                'purpose' => BookingSeriesPrepaymentService::PURPOSE,
                'booking_series_id' => (string) $series->id,
                'booking_ids' => $series->bookings()->pluck('id')->all(),
            ],
        ]);

        $listener = app(SettleSeriesPrepaymentOnWalletRechargeSucceeded::class);
        $listener->handle(new WalletRechargeSucceeded($recharge));
        $balance = (int) Wallet::query()->where('user_id', $student->id)->value('available_balance_minor');

        $listener->handle(new WalletRechargeSucceeded($recharge));

        $this->assertSame($balance, (int) Wallet::query()->where('user_id', $student->id)->value('available_balance_minor'));
        $this->assertSame(3, $series->bookings()->where('payment_status', BookingPaymentStatus::Paid)->count());
    }

    // ── What is deliberately NOT collected ─────────────────────────────────

    public function test_planned_classes_beyond_the_horizon_are_never_charged_for(): void
    {
        // The schedule is for 10 classes but only 3 are reserved. Money
        // is never taken for a class nobody is holding.
        $student = $this->student();
        $series = $this->series($student, 3);
        $series->forceFill(['occurrence_count' => 10])->save();

        $quote = $this->prepayments()->quote($series->refresh(), $student);

        $this->assertSame(3, $quote->count());
        $this->assertSame(149700, $quote->totalMinor);
        $this->assertGreaterThan(0, $quote->plannedCount);
    }
}

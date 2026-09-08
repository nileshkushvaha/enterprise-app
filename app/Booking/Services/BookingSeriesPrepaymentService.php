<?php

declare(strict_types=1);

namespace App\Booking\Services;

use App\Booking\Contracts\BookingPaymentServiceInterface;
use App\Booking\DTOs\SeriesPrepaymentQuoteData;
use App\Booking\DTOs\SeriesPrepaymentResult;
use App\Booking\Enums\BookingPaymentStatus;
use App\Booking\Enums\BookingStatus;
use App\Booking\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingSeries;
use App\Models\User;
use App\Models\Wallet;
use App\Payments\DTOs\PaymentCheckoutData;
use App\Support\MoneyFormatter;
use App\Wallet\Exceptions\WalletException;
use App\Wallet\Services\WalletRechargeService;
use App\Wallet\Services\WalletService;
use Illuminate\Support\Collection;

/**
 * Paying for a whole repeating schedule in ONE checkout.
 *
 * Before this, N reserved classes meant N obligations and therefore N
 * trips through a payment gateway. That is not a rounding-error
 * annoyance — a student who booked twelve classes was asked to complete
 * twelve card payments, and the reservation on each one expires
 * independently while they do it.
 *
 * The money moves in one hop through the student's own WALLET rather
 * than through a new multi-booking obligation, and that choice is the
 * whole design:
 *
 *  - `BookingPayment` is documented as the obligation to pay for ONE
 *    booking. Making one obligation span many would mean apportioning a
 *    partial refund across a single captured provider payment when one
 *    class is later cancelled — the hardest problem in the whole area.
 *    Refunds already credit the wallet, so routing the payment through
 *    it means cancelling one class of a batch is EXACTLY the refund
 *    path that already exists and is already tested.
 *  - Every per-class semantic survives untouched: its own price, its own
 *    BookingPayment row, its own reservation, its own invoice, its own
 *    refund decision. Only the number of times the student is asked for
 *    money changes.
 *
 * What is deliberately NOT collected: classes beyond the confirmation
 * horizon. They are not reservations yet, and money is never taken for
 * a class nobody is holding.
 */
final class BookingSeriesPrepaymentService
{
    public function __construct(
        private readonly BookingPaymentServiceInterface $payments,
        private readonly WalletRechargeService $recharges,
        private readonly WalletService $wallets,
        private readonly BookingSeriesService $series,
    ) {}

    /**
     * What the student owes for this schedule's reserved classes, and
     * how much of it their wallet already covers.
     *
     * Display-only. Nothing here is trusted at settlement time — every
     * amount is recomputed from the booking rows under a lock by
     * BookingPaymentService::payWithWallet(), exactly as a single
     * payment already is.
     */
    public function quote(BookingSeries $series, User $student): SeriesPrepaymentQuoteData
    {
        $this->assertOwnership($series, $student);

        $payable = $this->payableBookings($series);
        $planned = max(0, $this->plannedCount($series));

        if ($payable->isEmpty()) {
            return new SeriesPrepaymentQuoteData([], 0, null, 0, 0, $planned);
        }

        $currencies = $payable->pluck('currency')->map(
            static fn (?string $code): string => strtoupper((string) $code),
        )->unique();

        // A batch has to be one currency. In practice a schedule is one
        // instructor at one price so this never differs — but a
        // half-settled batch is a far worse outcome than an explicit
        // refusal, and wallet payment never converts (see
        // BookingPaymentService::resolveMatchingWallet).
        if ($currencies->count() > 1) {
            return new SeriesPrepaymentQuoteData(
                [], 0, null, 0, 0, $planned,
                blockedReason: 'These classes are priced in different currencies, so they cannot be paid for together. Please pay for them individually.',
            );
        }

        $currency = (string) $currencies->first();
        $total = $payable->sum(fn (Booking $booking): int => $this->minorAmount($booking));
        $wallet = $this->walletFor($student);

        if ($wallet !== null && strtoupper($wallet->currency_code) !== $currency) {
            return new SeriesPrepaymentQuoteData(
                [], 0, $currency, 0, 0, $planned,
                blockedReason: 'Your wallet is in a different currency to these classes, so they cannot be paid for together.',
            );
        }

        $balance = (int) ($wallet?->available_balance_minor ?? 0);

        return new SeriesPrepaymentQuoteData(
            bookingIds: $payable->pluck('id')->map(static fn ($id): string => (string) $id)->all(),
            totalMinor: (int) $total,
            currencyCode: $currency,
            walletBalanceMinor: $balance,
            // Exactly the gap, never a rounded-up "convenient" amount:
            // the platform should not end up holding more of a student's
            // money than the thing they are buying costs.
            shortfallMinor: max(0, (int) $total - $balance),
            plannedCount: $planned,
        );
    }

    /**
     * Settles every reserved class of the schedule from the student's
     * wallet.
     *
     * Each class goes through the ordinary single-booking wallet path,
     * so each gets its own lock, its own re-validation and its own
     * BookingPayment — this method only decides the ORDER and reports
     * what happened. One class failing (its slot was taken while the
     * student was at the gateway) leaves the others paid and the unspent
     * money in the wallet; nothing is lost and nothing is silently
     * confirmed.
     *
     * Earliest class first, deliberately: if the balance turns out to be
     * short, the classes that survive are the ones happening soonest.
     */
    public function settleFromWallet(BookingSeries $series, User $student): SeriesPrepaymentResult
    {
        $this->assertOwnership($series, $student);

        $paid = new Collection;
        $failures = [];

        foreach ($this->payableBookings($series) as $booking) {
            try {
                $paid->push($this->payments->payWithWallet($booking, $student));
            } catch (BookingException $exception) {
                $failures[$booking->reference] = $exception->getMessage();
            }
        }

        return new SeriesPrepaymentResult(
            paid: $paid,
            failures: $failures,
            remainingWalletMinor: (int) ($this->walletFor($student)?->available_balance_minor ?? 0),
        );
    }

    /**
     * Opens ONE checkout for exactly the shortfall.
     *
     * The recharge carries the schedule and the classes it was raised
     * for, so the settlement listener can finish the job the student
     * started even if they close the tab on the way back. That is not
     * automatic charging: they asked to pay for these classes, and paid
     * this exact amount for them.
     *
     * @throws BookingException
     */
    public function initiateTopUp(BookingSeries $series, User $student): PaymentCheckoutData
    {
        $quote = $this->quote($series, $student);

        if ($quote->blockedReason !== null) {
            throw new BookingException($quote->blockedReason);
        }

        if (! $quote->isPayable()) {
            throw new BookingException('There is nothing left to pay for in this schedule.');
        }

        if ($quote->shortfallMinor <= 0) {
            throw new BookingException('Your balance already covers these classes.');
        }

        try {
            return $this->recharges->initiate(
                $student,
                $quote->shortfallMinor,
                metadata: [
                    'purpose' => self::PURPOSE,
                    'booking_series_id' => (string) $series->id,
                    'booking_ids' => $quote->bookingIds,
                ],
            );
        } catch (WalletException $exception) {
            throw new BookingException($exception->getMessage(), previous: $exception);
        }
    }

    /** Marks a recharge as raised to pay for a schedule's classes. */
    public const string PURPOSE = 'booking_series_prepayment';

    /**
     * The classes money can actually be collected for: reserved, not
     * terminal, and genuinely awaiting payment.
     *
     * @return Collection<int, Booking>
     */
    private function payableBookings(BookingSeries $series): Collection
    {
        return $series->bookings()
            ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
            ->where('payment_status', BookingPaymentStatus::Pending)
            ->whereNotNull('price')
            ->orderBy('starts_at')
            ->get();
    }

    private function plannedCount(BookingSeries $series): int
    {
        $total = $this->series->scheduleFor($series, 1)->totalScheduled;

        return $total === null ? 0 : max(0, $total - $series->liveBookingsCount());
    }

    private function walletFor(User $student): ?Wallet
    {
        return Wallet::query()->where('user_id', $student->id)->first();
    }

    private function minorAmount(Booking $booking): int
    {
        $minorUnits = MoneyFormatter::minorUnitsFor((string) $booking->currency);

        return (int) round(((float) $booking->price) * (10 ** $minorUnits));
    }

    /** @throws BookingException */
    private function assertOwnership(BookingSeries $series, User $student): void
    {
        if ((int) $series->student_id !== (int) $student->id) {
            throw new BookingException('You may only pay for your own classes.');
        }
    }
}

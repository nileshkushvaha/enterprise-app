<?php

declare(strict_types=1);

namespace App\Jobs\Booking;

use App\Booking\Services\BookingSeriesPrepaymentService;
use App\Booking\Services\BookingSeriesService;
use App\Models\BookingSeries;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fills one recurring schedule forward, one bounded batch at a time.
 *
 * This is what makes a long or ongoing series possible without a cap on
 * classes: no run does unbounded work, and a run that dies is simply
 * repeated. Correctness under repetition is not this job's own doing —
 * it comes from BookingSeriesService::generate(), which is idempotent
 * against the (series, occurrence date) unique index — so retries,
 * duplicate messages and a concurrent run of the same series cannot
 * produce a second class or a second payment demand.
 *
 * ShouldBeUnique keeps the common case cheap rather than correct: it
 * stops the same series being queued twice at once. Correctness holds
 * even if it does not (a lost lock, a flushed cache).
 *
 * The job re-dispatches itself while the horizon still has room, so an
 * ongoing schedule advances in steps instead of one long transaction.
 */
class GenerateBookingSeriesOccurrences implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    /**
     * Long enough to cover a slow batch, short enough that a worker
     * killed mid-run does not lock the series out until tomorrow.
     */
    public int $uniqueFor = 900;

    public function __construct(
        public readonly string $bookingSeriesId,
        /** Guards against a pathological rule chain-dispatching forever. */
        public readonly int $pass = 1,
    ) {
        $this->onQueue('notifications');
    }

    public function uniqueId(): string
    {
        return $this->bookingSeriesId;
    }

    public function handle(BookingSeriesService $series, BookingSeriesPrepaymentService $prepayments): void
    {
        $model = BookingSeries::query()->with('type')->find($this->bookingSeriesId);

        if ($model === null || ! $model->status->generatesOccurrences()) {
            return;
        }

        $outcome = $series->generate($model);

        if ($outcome['failures'] !== []) {
            // Dates, reasons and the series id only — never the student's
            // or instructor's identity, notes, or anything about payment.
            Log::info('Recurring schedule generation recorded unbookable dates.', [
                'booking_series_id' => $this->bookingSeriesId,
                'dates' => array_keys($outcome['failures']),
            ]);
        }

        // Classes only just created are payment-due. If the student asked
        // for it — and this deployment allows it — confirm them from the
        // balance they already deposited for exactly this. Runs AFTER
        // generation, and only ever spends money already in the wallet;
        // it never opens a checkout. See
        // BookingSeriesPrepaymentService::autoSettle().
        if ($outcome['booked']->isNotEmpty()) {
            $prepayments->autoSettle($model->refresh());
        }

        // "exhausted" means the horizon stopped us, not the schedule. Keep
        // going only while the pass actually created something, so a
        // series whose horizon is already full does not re-queue forever.
        if (! $outcome['exhausted'] && $outcome['booked']->isNotEmpty() && $this->pass < 20) {
            self::dispatch($this->bookingSeriesId, $this->pass + 1);
        }
    }
}

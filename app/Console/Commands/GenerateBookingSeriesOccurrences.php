<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Booking\Enums\BookingSeriesStatus;
use App\Jobs\Booking\GenerateBookingSeriesOccurrences as GenerateJob;
use App\Models\BookingSeries;
use Illuminate\Console\Command;

/**
 * Rolls every active recurring schedule forward into the confirmation
 * horizon.
 *
 * This is the counterpart to not capping how many classes a student may
 * schedule: instead of creating a whole series up front, the platform
 * keeps the next horizon's worth of classes reserved and this sweep
 * tops it up as time passes.
 *
 * Cursor-chunked and job-per-series, so the command itself holds no
 * meaningful memory regardless of how many schedules exist, and one
 * problematic series cannot stall the rest.
 */
class GenerateBookingSeriesOccurrences extends Command
{
    protected $signature = 'booking:generate-series {--series= : Only this series id} {--sync : Generate inline instead of queueing}';

    protected $description = 'Create upcoming classes for active recurring schedules';

    public function handle(): int
    {
        $query = BookingSeries::query()->where('status', BookingSeriesStatus::Active);

        if ($seriesId = $this->option('series')) {
            $query->whereKey($seriesId);
        }

        $dispatched = 0;

        foreach ($query->orderBy('created_at')->cursor() as $series) {
            $this->option('sync')
                ? GenerateJob::dispatchSync($series->id)
                : GenerateJob::dispatch($series->id);

            $dispatched++;
        }

        $this->info(sprintf('%d recurring schedule(s) queued for generation.', $dispatched));

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Booking\Enums\BookingPaymentReconciliationIssueStatus;
use App\Booking\Enums\BookingSeriesStatus;
use App\Models\BookingPaymentReconciliationIssue;
use App\Models\BookingSeries;
use App\Models\SchedulerHistory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The signals an external monitor needs to tell "running normally" from
 * "quietly stopped".
 *
 * The gap this closes: the platform records scheduler and job FAILURES
 * well, and shows them in the admin. It had nothing at all for the more
 * dangerous case — the scheduler simply not running. A stopped cron
 * produces no failed row, no exception and no log line; it produces
 * silence, and silence looked exactly like health. Recurring class
 * generation depends entirely on that cron, so a schedule can stop
 * growing for days without anything saying so.
 *
 * Deliberately a READ-ONLY computation over records the platform
 * already keeps (`scheduler_histories`, `jobs`, `failed_jobs`,
 * `booking_series`). It adds no table, no daemon and no second
 * monitoring framework; SchedulerService remains the source of truth
 * for what is scheduled, and this only asks whether it has been
 * happening.
 *
 * It does not deliver alerts. Delivery belongs to whatever the
 * deployment already uses (uptime monitor, Nagios, cron+curl), so this
 * exposes a machine-readable verdict and lets that own the paging —
 * see docs/deployment/recurring-bookings-go-live.md.
 */
final class OperationalHealthService
{
    public const string OK = 'ok';

    public const string WARNING = 'warning';

    public const string CRITICAL = 'critical';

    /** Nothing has run at all. Distinct from "ran, but too long ago". */
    public const string UNKNOWN = 'unknown';

    /**
     * How long after a command's own cadence we start complaining.
     *
     * Generous on purpose. An hourly command that ran 61 minutes ago is
     * not a fault — a deploy, a slow run or ordinary scheduler jitter
     * all produce that, and a monitor that cries during every release
     * gets muted, which is worse than having no monitor. Two missed
     * runs plus a margin is a real signal.
     */
    private const int SCHEDULER_HEARTBEAT_GRACE_MINUTES = 15;

    private const int HOURLY_COMMAND_GRACE_MINUTES = 150;

    /** A queued job still waiting this long means nothing is draining the queue. */
    private const int QUEUE_BACKLOG_AGE_MINUTES = 30;

    /** An active schedule untouched this long has stopped being filled. */
    private const int SERIES_GENERATION_STALE_HOURS = 6;

    /**
     * @return array{status: string, checks: array<int, array<string, mixed>>, generated_at: string}
     */
    public function report(): array
    {
        $checks = [
            $this->schedulerHeartbeat(),
            $this->recurringGenerationRan(),
            $this->schedulerFailures(),
            $this->queueBacklog(),
            $this->failedJobs(),
            $this->stalledRecurringSchedules(),
            $this->openReconciliationIssues(),
        ];

        return [
            'status' => $this->worst($checks),
            'checks' => $checks,
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    /**
     * Is the Laravel scheduler running AT ALL?
     *
     * The broadest signal, and the one that catches a missing cron.
     * Asked across every command rather than one, because a single
     * command can legitimately be idle while the scheduler is fine.
     */
    private function schedulerHeartbeat(): array
    {
        $latest = SchedulerHistory::query()->where('triggered_by', '!=', 'manual')->max('ran_at');

        if ($latest === null) {
            return $this->check(
                'scheduler_heartbeat',
                self::UNKNOWN,
                'No scheduled task has ever run. The Laravel scheduler is probably not installed.',
            );
        }

        $minutes = (int) CarbonImmutable::parse($latest)->diffInMinutes(CarbonImmutable::now());

        // Something in the schedule runs every minute, so silence for
        // longer than the grace means cron itself has stopped.
        return $this->check(
            'scheduler_heartbeat',
            $minutes > self::SCHEDULER_HEARTBEAT_GRACE_MINUTES ? self::CRITICAL : self::OK,
            sprintf('Last scheduled task ran %d minute(s) ago.', $minutes),
            ['last_run_at' => (string) $latest, 'minutes_ago' => $minutes],
        );
    }

    /** Has the recurring-class sweep itself run recently? */
    private function recurringGenerationRan(): array
    {
        $latest = SchedulerHistory::query()
            ->where('command', 'like', '%booking:generate-series%')
            ->where('triggered_by', '!=', 'manual')
            ->max('ran_at');

        if ($latest === null) {
            return $this->check(
                'recurring_generation_scheduled',
                self::UNKNOWN,
                'booking:generate-series has never been run by the scheduler. Recurring classes are not being generated.',
            );
        }

        $minutes = (int) CarbonImmutable::parse($latest)->diffInMinutes(CarbonImmutable::now());

        return $this->check(
            'recurring_generation_scheduled',
            $minutes > self::HOURLY_COMMAND_GRACE_MINUTES ? self::CRITICAL : self::OK,
            sprintf('booking:generate-series last ran %d minute(s) ago.', $minutes),
            ['last_run_at' => (string) $latest, 'minutes_ago' => $minutes],
        );
    }

    private function schedulerFailures(): array
    {
        $failures = SchedulerHistory::query()
            ->where('status', 'failed')
            ->where('ran_at', '>=', CarbonImmutable::now()->subDay())
            ->count();

        return $this->check(
            'scheduler_failures_24h',
            $failures > 0 ? self::WARNING : self::OK,
            sprintf('%d scheduled task failure(s) in the last 24 hours.', $failures),
            ['count' => $failures],
        );
    }

    /**
     * Is anything actually draining the queue?
     *
     * Recurring generation and every booking notification are queued, so
     * a stopped worker stops both. Measured by the AGE of the oldest
     * waiting job rather than queue depth: a deep queue that is moving is
     * healthy, and a single job stuck for an hour is not.
     */
    private function queueBacklog(): array
    {
        if (config('queue.default') !== 'database') {
            return $this->check(
                'queue_backlog',
                self::UNKNOWN,
                sprintf('Queue driver is "%s"; check backlog with that driver\'s own tooling.', (string) config('queue.default')),
            );
        }

        try {
            $oldest = DB::table('jobs')->min('available_at');
        } catch (Throwable) {
            return $this->check('queue_backlog', self::UNKNOWN, 'Queue table could not be read.');
        }

        if ($oldest === null) {
            return $this->check('queue_backlog', self::OK, 'No jobs waiting.');
        }

        $minutes = (int) CarbonImmutable::createFromTimestamp((int) $oldest)->diffInMinutes(CarbonImmutable::now());

        return $this->check(
            'queue_backlog',
            $minutes > self::QUEUE_BACKLOG_AGE_MINUTES ? self::CRITICAL : self::OK,
            sprintf('Oldest waiting job has been queued %d minute(s).', $minutes),
            ['oldest_waiting_minutes' => $minutes],
        );
    }

    private function failedJobs(): array
    {
        try {
            $recent = DB::table('failed_jobs')
                ->where('failed_at', '>=', CarbonImmutable::now()->subDay())
                ->count();
        } catch (Throwable) {
            return $this->check('failed_jobs_24h', self::UNKNOWN, 'Failed-jobs table could not be read.');
        }

        return $this->check(
            'failed_jobs_24h',
            $recent > 0 ? self::WARNING : self::OK,
            sprintf('%d job(s) failed in the last 24 hours.', $recent),
            ['count' => $recent],
        );
    }

    /**
     * Active schedules that have stopped being filled.
     *
     * Narrower than the heartbeat and worth having separately: the
     * scheduler can be running perfectly while one schedule fails every
     * pass. Only counts series that have generated before, so a
     * just-created one is never reported as stalled.
     */
    private function stalledRecurringSchedules(): array
    {
        $stalled = BookingSeries::query()
            ->where('status', BookingSeriesStatus::Active)
            ->whereNotNull('last_generated_at')
            ->where('last_generated_at', '<', CarbonImmutable::now()->subHours(self::SERIES_GENERATION_STALE_HOURS))
            ->count();

        $failing = BookingSeries::query()
            ->where('status', BookingSeriesStatus::Active)
            ->where('generation_failures', '>', 3)
            ->count();

        $status = $failing > 0 ? self::CRITICAL : ($stalled > 0 ? self::WARNING : self::OK);

        return $this->check(
            'recurring_schedules',
            $status,
            sprintf('%d active schedule(s) not filled in %dh; %d with repeated failures.', $stalled, self::SERIES_GENERATION_STALE_HOURS, $failing),
            ['stalled' => $stalled, 'repeatedly_failing' => $failing],
        );
    }

    /**
     * Money the platform owes that no automatic process will resolve —
     * including a provider refund that failed after claiming
     * (RefundStatusMismatch), which only a human can close.
     */
    private function openReconciliationIssues(): array
    {
        try {
            $open = BookingPaymentReconciliationIssue::query()
                ->where('status', BookingPaymentReconciliationIssueStatus::Open)
                ->count();
        } catch (Throwable) {
            return $this->check('reconciliation_issues', self::UNKNOWN, 'Reconciliation issues could not be read.');
        }

        return $this->check(
            'reconciliation_issues',
            $open > 0 ? self::WARNING : self::OK,
            sprintf('%d open booking payment reconciliation issue(s).', $open),
            ['open' => $open],
        );
    }

    /** @param array<string, mixed> $context */
    private function check(string $name, string $status, string $summary, array $context = []): array
    {
        return ['name' => $name, 'status' => $status, 'summary' => $summary, ...($context === [] ? [] : ['context' => $context])];
    }

    /**
     * The report's overall verdict.
     *
     * UNKNOWN counts as CRITICAL on purpose: "we have never seen this
     * run" is exactly the state a missing cron produces, and treating it
     * as merely unknown would hide the one failure this exists to catch.
     *
     * @param  array<int, array<string, mixed>>  $checks
     */
    private function worst(array $checks): string
    {
        $statuses = array_column($checks, 'status');

        if (in_array(self::CRITICAL, $statuses, true) || in_array(self::UNKNOWN, $statuses, true)) {
            return self::CRITICAL;
        }

        return in_array(self::WARNING, $statuses, true) ? self::WARNING : self::OK;
    }
}

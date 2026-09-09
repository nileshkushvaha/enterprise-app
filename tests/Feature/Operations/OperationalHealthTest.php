<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Booking\Enums\BookingPaymentReconciliationIssueStatus;
use App\Booking\Enums\BookingPaymentReconciliationIssueType;
use App\Booking\Enums\BookingPaymentReconciliationSeverity;
use App\Booking\Enums\BookingSeriesStatus;
use App\Booking\Enums\RecurrenceEndCondition;
use App\Booking\Enums\RecurrenceFrequency;
use App\Models\BookingPayment;
use App\Models\BookingPaymentReconciliationIssue;
use App\Models\BookingSeries;
use App\Models\BookingType;
use App\Models\SchedulerHistory;
use App\Models\User;
use App\Services\OperationalHealthService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The monitor that notices ABSENCE.
 *
 * Failures were always recorded. A scheduler that simply stops is the
 * dangerous case, because it produces no failed row, no exception and no
 * log line — and recurring class generation depends entirely on it. So
 * most of these tests are about silence being graded as a fault rather
 * than as health.
 */
class OperationalHealthTest extends TestCase
{
    use RefreshDatabase;

    private function health(): OperationalHealthService
    {
        return app(OperationalHealthService::class);
    }

    /** @return array<string, mixed> */
    private function check(string $name): array
    {
        foreach ($this->health()->report()['checks'] as $check) {
            if ($check['name'] === $name) {
                return $check;
            }
        }

        $this->fail("no check named {$name}");
    }

    private function ranAt(string $command, CarbonImmutable $at, string $status = 'success', string $triggeredBy = 'schedule'): void
    {
        SchedulerHistory::query()->create([
            'command' => $command,
            'triggered_by' => $triggeredBy,
            'status' => $status,
            'duration_ms' => 10,
            'ran_at' => $at,
        ]);
    }

    // ── Absence is a fault, not silence ────────────────────────────────────

    public function test_a_scheduler_that_has_never_run_is_critical(): void
    {
        // Exactly the state a missing or misconfigured scheduler
        // produces. Grading it "unknown" would hide the one failure this
        // exists to catch.
        $this->assertSame(OperationalHealthService::UNKNOWN, $this->check('scheduler_heartbeat')['status']);
        $this->assertSame(OperationalHealthService::CRITICAL, $this->health()->report()['status']);
    }

    public function test_a_stale_scheduler_is_critical(): void
    {
        $this->ranAt('artisan cms:publish-scheduled', CarbonImmutable::now()->subHours(3));

        $this->assertSame(OperationalHealthService::CRITICAL, $this->check('scheduler_heartbeat')['status']);
    }

    public function test_a_recently_active_scheduler_is_ok(): void
    {
        $this->ranAt('artisan cms:publish-scheduled', CarbonImmutable::now()->subMinutes(2));

        $this->assertSame(OperationalHealthService::OK, $this->check('scheduler_heartbeat')['status']);
    }

    public function test_a_manual_run_does_not_count_as_a_heartbeat(): void
    {
        // Someone running a command by hand proves the command works, not
        // that anything is scheduled. Counting it would let a dead
        // scheduler look alive for as long as an operator kept poking it.
        $this->ranAt('artisan cms:publish-scheduled', CarbonImmutable::now(), triggeredBy: 'manual');

        $this->assertSame(OperationalHealthService::UNKNOWN, $this->check('scheduler_heartbeat')['status']);
    }

    // ── The recurring sweep specifically ───────────────────────────────────

    public function test_generation_never_scheduled_is_reported_even_when_the_scheduler_is_alive(): void
    {
        // The scheduler runs, but this one command was never registered
        // or never fired — a real and otherwise invisible misconfiguration.
        $this->ranAt('artisan cms:publish-scheduled', CarbonImmutable::now()->subMinute());

        $this->assertSame(OperationalHealthService::OK, $this->check('scheduler_heartbeat')['status']);
        $this->assertSame(OperationalHealthService::UNKNOWN, $this->check('recurring_generation_scheduled')['status']);
        $this->assertSame(OperationalHealthService::CRITICAL, $this->health()->report()['status']);
    }

    public function test_generation_that_ran_within_its_cadence_is_ok(): void
    {
        $this->ranAt("'/usr/bin/php' 'artisan' booking:generate-series", CarbonImmutable::now()->subMinutes(30));

        $this->assertSame(OperationalHealthService::OK, $this->check('recurring_generation_scheduled')['status']);
    }

    public function test_an_hourly_command_is_not_alarmed_by_ordinary_jitter(): void
    {
        // 70 minutes for an hourly command is a deploy or a slow run, not
        // an incident. A monitor that cries during every release gets
        // muted, which is worse than having no monitor.
        $this->ranAt("'/usr/bin/php' 'artisan' booking:generate-series", CarbonImmutable::now()->subMinutes(70));

        $this->assertSame(OperationalHealthService::OK, $this->check('recurring_generation_scheduled')['status']);
    }

    public function test_generation_missing_several_cycles_is_critical(): void
    {
        $this->ranAt("'/usr/bin/php' 'artisan' booking:generate-series", CarbonImmutable::now()->subHours(4));

        $this->assertSame(OperationalHealthService::CRITICAL, $this->check('recurring_generation_scheduled')['status']);
    }

    // ── Queue, failures and money ──────────────────────────────────────────

    public function test_an_undrained_queue_is_critical(): void
    {
        // Generation and every booking notification are queued, so a
        // stopped worker stops both. Age, not depth: a deep queue that is
        // moving is healthy.
        //
        // The suite runs QUEUE_CONNECTION=sync, where there is no queue
        // table to inspect and "unknown" is the correct answer — so this
        // test configures the driver it is actually about.
        config(['queue.default' => 'database']);

        DB::table('jobs')->insert([
            'queue' => 'notifications',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => CarbonImmutable::now()->subHour()->timestamp,
            'created_at' => CarbonImmutable::now()->subHour()->timestamp,
        ]);

        $this->assertSame(OperationalHealthService::CRITICAL, $this->check('queue_backlog')['status']);
    }

    public function test_a_moving_queue_is_ok(): void
    {
        config(['queue.default' => 'database']);

        DB::table('jobs')->insert([
            'queue' => 'notifications',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => CarbonImmutable::now()->timestamp,
            'created_at' => CarbonImmutable::now()->timestamp,
        ]);

        $this->assertSame(OperationalHealthService::OK, $this->check('queue_backlog')['status']);
    }

    public function test_a_recent_scheduler_failure_is_a_warning(): void
    {
        $this->ranAt('artisan cms:publish-scheduled', CarbonImmutable::now()->subMinute(), status: 'failed');

        $this->assertSame(OperationalHealthService::WARNING, $this->check('scheduler_failures_24h')['status']);
    }

    public function test_an_open_reconciliation_issue_is_a_warning(): void
    {
        // Money the platform owes that no automatic process will resolve
        // — including a provider refund that failed after claiming.
        $payment = BookingPayment::factory()->captured()->create();

        BookingPaymentReconciliationIssue::query()->create([
            'reference' => 'RCN-TEST0001',
            'booking_payment_id' => $payment->id,
            'provider' => 'razorpay',
            'type' => BookingPaymentReconciliationIssueType::RefundStatusMismatch,
            'severity' => BookingPaymentReconciliationSeverity::Critical,
            'status' => BookingPaymentReconciliationIssueStatus::Open,
            'safe_summary' => 'A provider refund failed after claiming; the refund is still owed.',
            'first_detected_at' => CarbonImmutable::now(),
            'last_detected_at' => CarbonImmutable::now(),
        ]);

        $this->assertSame(OperationalHealthService::WARNING, $this->check('reconciliation_issues')['status']);
    }

    // ── Per-series stalling ────────────────────────────────────────────────

    public function test_a_schedule_that_stopped_being_filled_is_reported(): void
    {
        // Narrower than the heartbeat and worth having separately: the
        // scheduler can be perfectly healthy while one schedule fails
        // every pass.
        $this->series(lastGeneratedAt: CarbonImmutable::now()->subHours(12));

        $this->assertSame(OperationalHealthService::WARNING, $this->check('recurring_schedules')['status']);
    }

    public function test_a_repeatedly_failing_schedule_is_critical(): void
    {
        $this->series(lastGeneratedAt: CarbonImmutable::now(), failures: 5);

        $this->assertSame(OperationalHealthService::CRITICAL, $this->check('recurring_schedules')['status']);
    }

    public function test_a_brand_new_schedule_is_not_reported_as_stalled(): void
    {
        // Never generated yet is not the same as stopped generating.
        $this->series(lastGeneratedAt: null);

        $this->assertSame(OperationalHealthService::OK, $this->check('recurring_schedules')['status']);
    }

    // ── The contract external monitors depend on ───────────────────────────

    public function test_the_command_exit_code_distinguishes_ok_warning_and_critical(): void
    {
        config(['queue.default' => 'database']);

        // Nothing has run: critical.
        $this->artisan('platform:health-check')->assertExitCode(2);

        // Alive, sweep recent, but a failure recorded: warning.
        $this->ranAt('artisan cms:publish-scheduled', CarbonImmutable::now()->subMinute());
        $this->ranAt("'/usr/bin/php' 'artisan' booking:generate-series", CarbonImmutable::now()->subMinutes(5));
        $this->ranAt('artisan meetings:sync-pending', CarbonImmutable::now()->subMinutes(2), status: 'failed');

        $this->artisan('platform:health-check')->assertExitCode(1);

        // Clear the failure: ok.
        SchedulerHistory::query()->where('status', 'failed')->delete();

        $this->artisan('platform:health-check')->assertExitCode(0);
    }

    public function test_the_json_report_is_machine_readable(): void
    {
        config(['queue.default' => 'database']);

        $this->ranAt('artisan cms:publish-scheduled', CarbonImmutable::now()->subMinute());
        $this->ranAt("'/usr/bin/php' 'artisan' booking:generate-series", CarbonImmutable::now()->subMinutes(5));

        $report = $this->health()->report();

        $this->assertSame(OperationalHealthService::OK, $report['status']);
        $this->assertNotEmpty($report['generated_at']);
        $this->assertSame(
            ['scheduler_heartbeat', 'recurring_generation_scheduled', 'scheduler_failures_24h', 'queue_backlog', 'failed_jobs_24h', 'recurring_schedules', 'reconciliation_issues'],
            array_column($report['checks'], 'name'),
        );
    }

    private function series(?CarbonImmutable $lastGeneratedAt, int $failures = 0): BookingSeries
    {
        $student = User::factory()->create();
        $instructor = User::factory()->create();
        $type = BookingType::factory()->create();

        return BookingSeries::query()->create([
            'booking_type_id' => $type->id,
            'student_id' => $student->id,
            'instructor_id' => $instructor->id,
            'status' => BookingSeriesStatus::Active,
            'frequency' => RecurrenceFrequency::Weekly,
            'repeat_interval' => 1,
            'weekdays' => [1],
            'start_date' => CarbonImmutable::now()->toDateString(),
            'time_of_day' => '10:00:00',
            'duration_minutes' => 60,
            'timezone' => 'UTC',
            'student_timezone' => 'UTC',
            'end_condition' => RecurrenceEndCondition::AfterCount,
            'occurrence_count' => 10,
            'last_generated_at' => $lastGeneratedAt,
            'generation_failures' => $failures,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Booking;

use App\Booking\DTOs\RecurrenceRuleData;
use App\Booking\Enums\RecurrenceEndCondition;
use App\Booking\Enums\RecurrenceFrequency;
use App\Booking\Enums\Weekday;
use App\Booking\Services\RecurrenceScheduler;
use App\Support\Timezone\LocalWallClock;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * The recurrence math, isolated from persistence, availability and the
 * clock. Everything the wizard, the API and the background generator
 * agree on is decided here, so this is where the calendar rules —
 * multi-weekday, every-N-weeks, end boundaries, month/year rollover and
 * daylight saving — are pinned down.
 */
class RecurrenceSchedulerTest extends TestCase
{
    private RecurrenceScheduler $scheduler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scheduler = new RecurrenceScheduler;
    }

    /** @return list<string> */
    private function dates(RecurrenceRuleData $rule, int $limit = 50, ?string $after = null, array $skipped = []): array
    {
        return array_map(
            static fn ($occurrence): string => $occurrence->localDate,
            $this->scheduler->occurrences($rule, $limit, $after, $skipped),
        );
    }

    public function test_daily_recurrence_produces_consecutive_days(): void
    {
        $rule = new RecurrenceRuleData(
            frequency: RecurrenceFrequency::Daily,
            startDate: '2026-03-09',
            timeOfDay: '18:00:00',
            timezone: 'UTC',
            endCondition: RecurrenceEndCondition::AfterCount,
            occurrenceCount: 4,
        );

        $this->assertSame(['2026-03-09', '2026-03-10', '2026-03-11', '2026-03-12'], $this->dates($rule));
    }

    public function test_daily_recurrence_honours_an_interval(): void
    {
        $rule = new RecurrenceRuleData(
            frequency: RecurrenceFrequency::Daily,
            startDate: '2026-03-09',
            timeOfDay: '18:00:00',
            timezone: 'UTC',
            interval: 3,
            endCondition: RecurrenceEndCondition::AfterCount,
            occurrenceCount: 3,
        );

        $this->assertSame(['2026-03-09', '2026-03-12', '2026-03-15'], $this->dates($rule));
    }

    public function test_weekly_recurrence_supports_multiple_weekdays_in_chronological_order(): void
    {
        // Monday 9 March 2026. Selected: Monday, Wednesday, Saturday.
        $rule = new RecurrenceRuleData(
            frequency: RecurrenceFrequency::Weekly,
            startDate: '2026-03-09',
            timeOfDay: '18:00:00',
            timezone: 'UTC',
            weekdays: [Weekday::Wednesday, Weekday::Monday, Weekday::Saturday],
            endCondition: RecurrenceEndCondition::AfterCount,
            occurrenceCount: 6,
        );

        $this->assertSame([
            '2026-03-09', '2026-03-11', '2026-03-14',
            '2026-03-16', '2026-03-18', '2026-03-21',
        ], $this->dates($rule));
    }

    public function test_weekly_recurrence_never_starts_before_the_start_date(): void
    {
        // Start on Wednesday but also repeat on Monday: the Monday of the
        // FIRST week is in the past relative to the start date and must
        // not be scheduled.
        $rule = new RecurrenceRuleData(
            frequency: RecurrenceFrequency::Weekly,
            startDate: '2026-03-11',
            timeOfDay: '18:00:00',
            timezone: 'UTC',
            weekdays: [Weekday::Monday, Weekday::Wednesday],
            endCondition: RecurrenceEndCondition::AfterCount,
            occurrenceCount: 4,
        );

        $this->assertSame(['2026-03-11', '2026-03-16', '2026-03-18', '2026-03-23'], $this->dates($rule));
    }

    public function test_every_n_weeks_keeps_its_phase_across_a_month_boundary(): void
    {
        $rule = new RecurrenceRuleData(
            frequency: RecurrenceFrequency::Weekly,
            startDate: '2026-03-24',
            timeOfDay: '18:00:00',
            timezone: 'UTC',
            interval: 2,
            weekdays: [Weekday::Tuesday],
            endCondition: RecurrenceEndCondition::AfterCount,
            occurrenceCount: 4,
        );

        $this->assertSame(['2026-03-24', '2026-04-07', '2026-04-21', '2026-05-05'], $this->dates($rule));
    }

    public function test_every_n_weeks_with_multiple_weekdays_skips_the_off_weeks_entirely(): void
    {
        $rule = new RecurrenceRuleData(
            frequency: RecurrenceFrequency::Weekly,
            startDate: '2026-03-09',
            timeOfDay: '18:00:00',
            timezone: 'UTC',
            interval: 2,
            weekdays: [Weekday::Monday, Weekday::Thursday],
            endCondition: RecurrenceEndCondition::AfterCount,
            occurrenceCount: 4,
        );

        $this->assertSame(['2026-03-09', '2026-03-12', '2026-03-23', '2026-03-26'], $this->dates($rule));
    }

    public function test_recurrence_crosses_a_year_boundary_correctly(): void
    {
        $rule = new RecurrenceRuleData(
            frequency: RecurrenceFrequency::Weekly,
            startDate: '2026-12-21',
            timeOfDay: '18:00:00',
            timezone: 'UTC',
            weekdays: [Weekday::Monday],
            endCondition: RecurrenceEndCondition::AfterCount,
            occurrenceCount: 4,
        );

        $this->assertSame(['2026-12-21', '2026-12-28', '2027-01-04', '2027-01-11'], $this->dates($rule));
    }

    public function test_end_date_is_inclusive_and_bounds_the_series(): void
    {
        $rule = new RecurrenceRuleData(
            frequency: RecurrenceFrequency::Weekly,
            startDate: '2026-03-09',
            timeOfDay: '18:00:00',
            timezone: 'UTC',
            weekdays: [Weekday::Monday],
            endCondition: RecurrenceEndCondition::OnDate,
            endDate: '2026-03-23',
        );

        $this->assertSame(['2026-03-09', '2026-03-16', '2026-03-23'], $this->dates($rule));
        $this->assertSame(3, $this->scheduler->totalOccurrences($rule));
        $this->assertSame('2026-03-23', $this->scheduler->lastLocalDate($rule));
    }

    public function test_end_date_one_day_before_an_occurrence_excludes_it(): void
    {
        $rule = new RecurrenceRuleData(
            frequency: RecurrenceFrequency::Weekly,
            startDate: '2026-03-09',
            timeOfDay: '18:00:00',
            timezone: 'UTC',
            weekdays: [Weekday::Monday],
            endCondition: RecurrenceEndCondition::OnDate,
            endDate: '2026-03-22',
        );

        $this->assertSame(['2026-03-09', '2026-03-16'], $this->dates($rule));
    }

    public function test_after_count_produces_exactly_that_many_classes_well_beyond_twelve(): void
    {
        $rule = new RecurrenceRuleData(
            frequency: RecurrenceFrequency::Weekly,
            startDate: '2026-03-09',
            timeOfDay: '18:00:00',
            timezone: 'UTC',
            weekdays: [Weekday::Monday],
            endCondition: RecurrenceEndCondition::AfterCount,
            occurrenceCount: 60,
        );

        $dates = $this->dates($rule, 200);

        $this->assertCount(60, $dates);
        $this->assertSame('2026-03-09', $dates[0]);
        $this->assertSame('2027-04-26', $dates[59]);
        $this->assertSame(60, $this->scheduler->totalOccurrences($rule));
    }

    public function test_an_ongoing_series_has_no_total_and_no_last_date(): void
    {
        $rule = new RecurrenceRuleData(
            frequency: RecurrenceFrequency::Weekly,
            startDate: '2026-03-09',
            timeOfDay: '18:00:00',
            timezone: 'UTC',
            weekdays: [Weekday::Monday],
            endCondition: RecurrenceEndCondition::Never,
        );

        $this->assertNull($this->scheduler->totalOccurrences($rule));
        $this->assertNull($this->scheduler->lastLocalDate($rule));
        $this->assertCount(30, $this->dates($rule, 30));
    }

    public function test_skipped_dates_do_not_consume_a_class_of_a_counted_series(): void
    {
        $rule = new RecurrenceRuleData(
            frequency: RecurrenceFrequency::Weekly,
            startDate: '2026-03-09',
            timeOfDay: '18:00:00',
            timezone: 'UTC',
            weekdays: [Weekday::Monday],
            endCondition: RecurrenceEndCondition::AfterCount,
            occurrenceCount: 3,
        );

        // Without a skip the third class is 23 March; skipping 16 March
        // pushes the series one week further so three classes still happen.
        $this->assertSame(['2026-03-09', '2026-03-16', '2026-03-23'], $this->dates($rule));
        $this->assertSame(['2026-03-09', '2026-03-23', '2026-03-30'], $this->dates($rule, 50, null, ['2026-03-16']));
    }

    public function test_skipped_dates_reduce_the_class_count_of_a_date_bounded_series(): void
    {
        $rule = new RecurrenceRuleData(
            frequency: RecurrenceFrequency::Weekly,
            startDate: '2026-03-09',
            timeOfDay: '18:00:00',
            timezone: 'UTC',
            weekdays: [Weekday::Monday],
            endCondition: RecurrenceEndCondition::OnDate,
            endDate: '2026-03-23',
        );

        $this->assertSame(['2026-03-09', '2026-03-23'], $this->dates($rule, 50, null, ['2026-03-16']));
        $this->assertSame(2, $this->scheduler->totalOccurrences($rule, ['2026-03-16']));
    }

    public function test_paging_after_a_date_preserves_sequence_numbers(): void
    {
        $rule = new RecurrenceRuleData(
            frequency: RecurrenceFrequency::Weekly,
            startDate: '2026-03-09',
            timeOfDay: '18:00:00',
            timezone: 'UTC',
            weekdays: [Weekday::Monday],
            endCondition: RecurrenceEndCondition::AfterCount,
            occurrenceCount: 10,
        );

        $page = $this->scheduler->occurrences($rule, 3, '2026-03-23');

        $this->assertSame(['2026-03-30', '2026-04-06', '2026-04-13'], array_map(fn ($o) => $o->localDate, $page));
        $this->assertSame([4, 5, 6], array_map(fn ($o) => $o->sequence, $page));
    }

    public function test_the_intended_wall_clock_survives_a_daylight_saving_transition(): void
    {
        // Europe/London springs forward on 29 March 2026. A 18:00 class
        // must stay 18:00 local on both sides — 17:00 UTC before, 17:00
        // UTC minus the new offset after.
        $rule = new RecurrenceRuleData(
            frequency: RecurrenceFrequency::Weekly,
            startDate: '2026-03-23',
            timeOfDay: '18:00:00',
            timezone: 'Europe/London',
            weekdays: [Weekday::Monday],
            endCondition: RecurrenceEndCondition::AfterCount,
            occurrenceCount: 2,
        );

        $occurrences = $this->scheduler->occurrences($rule, 10);

        $this->assertSame('18:00', $occurrences[0]->startsAt->setTimezone('Europe/London')->format('H:i'));
        $this->assertSame('18:00', $occurrences[1]->startsAt->setTimezone('Europe/London')->format('H:i'));
        $this->assertSame('18:00', $occurrences[0]->startsAt->format('H:i'), 'GMT: 18:00 local is 18:00 UTC');
        $this->assertSame('17:00', $occurrences[1]->startsAt->format('H:i'), 'BST: 18:00 local is 17:00 UTC');
    }

    public function test_a_reading_deleted_by_spring_forward_is_reported_not_moved(): void
    {
        // Europe/London skips 01:00–02:00 on 29 March 2026.
        $rule = new RecurrenceRuleData(
            frequency: RecurrenceFrequency::Daily,
            startDate: '2026-03-28',
            timeOfDay: '01:30:00',
            timezone: 'Europe/London',
            endCondition: RecurrenceEndCondition::AfterCount,
            occurrenceCount: 2,
        );

        $occurrences = $this->scheduler->occurrences($rule, 10);

        $this->assertTrue($occurrences[0]->isRepresentable());
        $this->assertFalse($occurrences[1]->isRepresentable());
        $this->assertSame(LocalWallClock::NONEXISTENT, $occurrences[1]->wallClock);
        $this->assertNull($occurrences[1]->startsAt);
    }

    public function test_a_reading_repeated_by_fall_back_is_reported_not_guessed(): void
    {
        // America/New_York repeats 01:00–02:00 on 1 November 2026.
        $rule = new RecurrenceRuleData(
            frequency: RecurrenceFrequency::Daily,
            startDate: '2026-11-01',
            timeOfDay: '01:30:00',
            timezone: 'America/New_York',
            endCondition: RecurrenceEndCondition::AfterCount,
            occurrenceCount: 1,
        );

        $occurrences = $this->scheduler->occurrences($rule, 10);

        $this->assertSame(LocalWallClock::AMBIGUOUS, $occurrences[0]->wallClock);
        $this->assertFalse($occurrences[0]->isRepresentable());
    }

    // ── No hidden cap ──────────────────────────────────────────────────────

    /**
     * The arithmetic that lets a long schedule resume must agree exactly
     * with walking it. If it drifts, classes are numbered wrongly or the
     * "after N classes" stop fires in the wrong place — and both would be
     * silent.
     */
    public function test_counting_and_locating_agree_with_walking_the_calendar(): void
    {
        $rules = [];

        foreach ([1, 2, 3] as $interval) {
            foreach ([[Weekday::Monday], [Weekday::Tuesday, Weekday::Thursday], [Weekday::Sunday, Weekday::Wednesday, Weekday::Saturday], Weekday::cases()] as $days) {
                foreach (['2026-01-01', '2026-03-11', '2026-12-31'] as $start) {
                    $rules[] = new RecurrenceRuleData(
                        frequency: RecurrenceFrequency::Weekly,
                        startDate: $start,
                        timeOfDay: '18:00:00',
                        timezone: 'UTC',
                        interval: $interval,
                        weekdays: $days,
                        endCondition: RecurrenceEndCondition::AfterCount,
                        occurrenceCount: 400,
                    );
                }
            }

            foreach (['2026-01-01', '2026-06-15'] as $start) {
                $rules[] = new RecurrenceRuleData(
                    frequency: RecurrenceFrequency::Daily,
                    startDate: $start,
                    timeOfDay: '18:00:00',
                    timezone: 'UTC',
                    interval: $interval,
                    endCondition: RecurrenceEndCondition::AfterCount,
                    occurrenceCount: 400,
                );
            }
        }

        foreach ($rules as $rule) {
            $walked = array_map(
                static fn ($occurrence): string => $occurrence->localDate,
                $this->scheduler->occurrences($rule, 400),
            );

            $this->assertCount(400, $walked, 'the walk itself must produce the requested count');

            foreach ([1, 2, 7, 51, 200, 400] as $n) {
                $this->assertSame(
                    $walked[$n - 1],
                    $this->scheduler->nthCandidate($rule, $n),
                    sprintf('nthCandidate(%d) disagreed for %s', $n, json_encode($rule->toArray())),
                );

                $this->assertSame(
                    $n,
                    $this->scheduler->countCandidatesUpTo($rule, $walked[$n - 1]),
                    sprintf('countCandidatesUpTo disagreed at %d for %s', $n, json_encode($rule->toArray())),
                );

                // And the day BEFORE the n-th class must count n-1.
                $dayBefore = CarbonImmutable::parse($walked[$n - 1])->subDay()->toDateString();
                $this->assertSame($n - 1, $this->scheduler->countCandidatesUpTo($rule, $dayBefore));
            }
        }
    }

    public function test_a_schedule_far_longer_than_the_enumeration_guard_still_generates(): void
    {
        // The guard bounds ONE page, not the schedule. Asking for the
        // page that begins after class ~9,000 of a 10,000-class schedule
        // must return the right dates with the right numbers — not an
        // empty list, and not a silently truncated one.
        $rule = new RecurrenceRuleData(
            frequency: RecurrenceFrequency::Daily,
            startDate: '2026-01-01',
            timeOfDay: '18:00:00',
            timezone: 'UTC',
            endCondition: RecurrenceEndCondition::AfterCount,
            occurrenceCount: 10000,
        );

        $this->assertGreaterThan(RecurrenceRuleData::MAX_ENUMERATED_CANDIDATES, 10000);

        $watermark = CarbonImmutable::parse('2026-01-01')->addDays(8999)->toDateString();
        $page = $this->scheduler->occurrences($rule, 3, $watermark);

        $this->assertCount(3, $page, 'generation must continue past the enumeration guard');
        $this->assertSame([9001, 9002, 9003], array_map(fn ($o) => $o->sequence, $page));
        $this->assertSame(
            CarbonImmutable::parse('2026-01-01')->addDays(9000)->toDateString(),
            $page[0]->localDate,
        );

        // And the totals stay exact rather than stopping at the guard.
        $this->assertSame(10000, $this->scheduler->totalOccurrences($rule));
        $this->assertSame(
            CarbonImmutable::parse('2026-01-01')->addDays(9999)->toDateString(),
            $this->scheduler->lastLocalDate($rule),
        );
    }

    public function test_a_long_date_bounded_schedule_reports_an_exact_total(): void
    {
        $rule = new RecurrenceRuleData(
            frequency: RecurrenceFrequency::Daily,
            startDate: '2026-01-01',
            timeOfDay: '18:00:00',
            timezone: 'UTC',
            endCondition: RecurrenceEndCondition::OnDate,
            endDate: '2046-01-01',
        );

        // 20 years of daily classes — well past the guard.
        $expected = (int) CarbonImmutable::parse('2026-01-01')->diffInDays(CarbonImmutable::parse('2046-01-01')) + 1;

        $this->assertSame($expected, $this->scheduler->totalOccurrences($rule));
        $this->assertSame('2046-01-01', $this->scheduler->lastLocalDate($rule));
    }

    public function test_an_absurd_class_count_reports_an_unknown_end_rather_than_a_wrong_one(): void
    {
        $rule = new RecurrenceRuleData(
            frequency: RecurrenceFrequency::Daily,
            startDate: '2026-01-01',
            timeOfDay: '18:00:00',
            timezone: 'UTC',
            endCondition: RecurrenceEndCondition::AfterCount,
            occurrenceCount: 500_000_000,
        );

        // The count is what the student asked for and is reported as-is;
        // the last date is beyond anything worth quoting, so it is
        // reported as unknown rather than invented or crashed on.
        $this->assertSame(500_000_000, $this->scheduler->totalOccurrences($rule));
        $this->assertNull($this->scheduler->lastLocalDate($rule));
    }

    public function test_last_class_accounts_for_removed_dates_in_both_end_conditions(): void
    {
        $counted = new RecurrenceRuleData(
            frequency: RecurrenceFrequency::Weekly,
            startDate: '2026-03-09',
            timeOfDay: '18:00:00',
            timezone: 'UTC',
            weekdays: [Weekday::Monday],
            endCondition: RecurrenceEndCondition::AfterCount,
            occurrenceCount: 3,
        );

        // Removing one Monday pushes the third class a week later.
        $this->assertSame('2026-03-23', $this->scheduler->lastLocalDate($counted));
        $this->assertSame('2026-03-30', $this->scheduler->lastLocalDate($counted, ['2026-03-16']));

        $dated = new RecurrenceRuleData(
            frequency: RecurrenceFrequency::Weekly,
            startDate: '2026-03-09',
            timeOfDay: '18:00:00',
            timezone: 'UTC',
            weekdays: [Weekday::Monday],
            endCondition: RecurrenceEndCondition::OnDate,
            endDate: '2026-03-23',
        );

        // An end date does not move; removing the last class simply makes
        // the one before it the last.
        $this->assertSame('2026-03-23', $this->scheduler->lastLocalDate($dated));
        $this->assertSame('2026-03-16', $this->scheduler->lastLocalDate($dated, ['2026-03-23']));
    }

    public function test_a_weekly_rule_without_explicit_weekdays_uses_the_start_dates_own_day(): void
    {
        $rule = new RecurrenceRuleData(
            frequency: RecurrenceFrequency::Weekly,
            startDate: '2026-03-11',
            timeOfDay: '18:00:00',
            timezone: 'UTC',
            endCondition: RecurrenceEndCondition::AfterCount,
            occurrenceCount: 3,
        );

        $this->assertSame([Weekday::Wednesday], $rule->effectiveWeekdays());
        $this->assertSame(['2026-03-11', '2026-03-18', '2026-03-25'], $this->dates($rule));
    }
}

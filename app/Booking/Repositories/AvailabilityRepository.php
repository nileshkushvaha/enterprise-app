<?php

declare(strict_types=1);

namespace App\Booking\Repositories;

use App\Booking\Contracts\AvailabilityRepositoryInterface;
use App\Models\Holiday;
use App\Models\TeacherAvailability;
use App\Models\TeacherUnavailability;
use App\Models\User;
use App\Support\Timezone\IanaTimezone;
use App\Support\Timezone\LocalDay;
use App\Support\Timezone\LocalWallClock;
use App\Support\UserTimezoneResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class AvailabilityRepository implements AvailabilityRepositoryInterface
{
    /**
     * Read cache for one bounded, read-only availability pass.
     *
     * A wizard preview asks the same questions of the same instructor
     * for twenty-odd dates in a row, and the STATIC answers — which
     * weekly windows exist, which days are holidays, when the instructor
     * is on leave — cannot change between the first date and the last.
     * Re-querying them per date cost a 20-row preview several hundred
     * queries.
     *
     * Deliberately opt-in and instance-scoped rather than a global
     * cache. The same repository methods are used by the booking path
     * under the instructor lock, where a stale read would be a
     * correctness bug; that path simply never opens this bracket, and
     * holds its own instance anyway.
     *
     * Bookings are never cached here for the same reason: creating one
     * occurrence has to be visible to the next.
     */
    private bool $cachingReads = false;

    private ?int $cachedTeacherId = null;

    private ?CarbonImmutable $cachedFrom = null;

    private ?CarbonImmutable $cachedTo = null;

    /** @var Collection<int, TeacherAvailability>|null */
    private ?Collection $cachedWindowRows = null;

    /** @var Collection<int, string>|null `Y-m-d` holiday dates in range */
    private ?Collection $cachedHolidayDates = null;

    /** @var Collection<int, TeacherUnavailability>|null */
    private ?Collection $cachedBlackouts = null;

    public function beginCachedReads(int $teacherId, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $this->cachingReads = true;
        $this->cachedTeacherId = $teacherId;
        $this->cachedFrom = $from;
        $this->cachedTo = $to;
        $this->cachedWindowRows = null;
        $this->cachedHolidayDates = null;
        $this->cachedBlackouts = null;
    }

    public function endCachedReads(): void
    {
        $this->cachingReads = false;
        $this->cachedTeacherId = null;
        $this->cachedFrom = null;
        $this->cachedTo = null;
        $this->cachedWindowRows = null;
        $this->cachedHolidayDates = null;
        $this->cachedBlackouts = null;
    }

    /** Whether a question about $teacherId over [$startsAt, $endsAt] may be answered from cache. */
    private function servesFromCache(int $teacherId, CarbonImmutable $startsAt, CarbonImmutable $endsAt): bool
    {
        return $this->cachingReads
            && $this->cachedTeacherId === $teacherId
            && $this->cachedFrom !== null
            && $this->cachedTo !== null
            && ! $startsAt->lessThan($this->cachedFrom)
            && ! $endsAt->greaterThan($this->cachedTo);
    }

    public function windowsFor(int $teacherId, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $rows = TeacherAvailability::query()
            ->active()
            ->forTeacher($teacherId)
            ->with('teacher.profile')
            ->get();

        $windows = new Collection;

        foreach ($rows as $row) {
            $timezone = $row->timezone ?: self::teacherTimezone($row);
            $localFrom = $from->setTimezone($timezone)->startOfDay();
            $localTo = $to->setTimezone($timezone)->endOfDay();

            for ($date = $localFrom; $date->lessThanOrEqualTo($localTo); $date = $date->addDay()) {
                if ($row->day_of_week->value !== $date->dayOfWeek) {
                    continue;
                }

                if ($row->effective_from !== null && $date->lessThan($row->effective_from)) {
                    continue;
                }

                if ($row->effective_until !== null && $date->greaterThan($row->effective_until)) {
                    continue;
                }

                // TZ-6 (TZ-AUD-022): setTimeFromTimeString() answers even
                // when the wall clock it is given does not exist. On a
                // spring-forward date "09:00" may be skipped entirely and
                // PHP silently returns 10:00 — publishing a window the
                // instructor never offered. On a fall-back date a reading
                // can occur twice, and picking one arbitrarily means the
                // student and the instructor can each reasonably believe a
                // different hour was booked.
                //
                // Neither is ours to decide, so that ONE occurrence is
                // simply not generated. The instructor's weekly rule is
                // untouched and every other week is unaffected — only the
                // impossible or double reading disappears from the
                // bookable set, twice a year at most.
                if (! LocalWallClock::isValid($date->format('Y-m-d').' '.$row->start_time, $timezone)
                    || ! LocalWallClock::isValid($date->format('Y-m-d').' '.$row->end_time, $timezone)) {
                    continue;
                }

                $startsAt = $date->setTimeFromTimeString($row->start_time)->utc();
                // $date is already this instructor-local midnight (the loop
                // steps whole local days), so the next midnight is a plain
                // addDay() — DST-safe, and never a UTC startOfDay().
                $endsAt = self::endsAtMidnight($row->end_time)
                    ? $date->addDay()->utc()
                    : $date->setTimeFromTimeString($row->end_time)->utc();

                if ($endsAt->greaterThan($from) && $startsAt->lessThan($to)) {
                    $windows->push(['starts_at' => $startsAt, 'ends_at' => $endsAt]);
                }
            }
        }

        return $windows->sortBy('starts_at')->values();
    }

    public function windowCovers(int $teacherId, CarbonImmutable $startsAt, CarbonImmutable $endsAt): bool
    {
        return $this->rowsCover($this->windowRowsFor($teacherId), $startsAt, $endsAt);
    }

    /**
     * The instructor's published weekly windows.
     *
     * Loaded once per cached pass — this single query (plus its
     * teacher/profile eager loads) was previously repeated for every
     * date a preview checked.
     *
     * @return Collection<int, TeacherAvailability>
     */
    private function windowRowsFor(int $teacherId): Collection
    {
        if ($this->cachingReads && $this->cachedTeacherId === $teacherId && $this->cachedWindowRows !== null) {
            return $this->cachedWindowRows;
        }

        $rows = TeacherAvailability::query()
            ->active()
            ->forTeacher($teacherId)
            ->with('teacher.profile')
            ->get();

        if ($this->cachingReads && $this->cachedTeacherId === $teacherId) {
            $this->cachedWindowRows = $rows;
        }

        return $rows;
    }

    /**
     * The coverage calculation itself, extracted so a HYPOTHETICAL
     * window set (an availability mutation's proposed
     * after-state, built from unsaved model clones) can be evaluated
     * with the exact same timezone/day-of-week/effective-range/midnight
     * semantics as the live windowCovers() check — never a second
     * implementation. $fallbackTimezone covers rows whose teacher
     * relation isn't loaded (clones).
     *
     * @param  iterable<TeacherAvailability>  $rows
     */
    public function rowsCover(iterable $rows, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?string $fallbackTimezone = null): bool
    {
        if (! $startsAt->isSameDay($endsAt) && ! $endsAt->equalTo($startsAt->addDay()->startOfDay())) {
            return false;
        }

        foreach ($rows as $row) {
            if (! $row->is_active) {
                continue;
            }

            $timezone = $row->timezone
                ?: ($row->relationLoaded('teacher') ? self::teacherTimezone($row) : null)
                ?: $fallbackTimezone
                ?: UserTimezoneResolver::platformDefault();
            $localStart = $startsAt->setTimezone($timezone);
            $localEnd = $endsAt->setTimezone($timezone);

            if ($row->day_of_week->value !== $localStart->dayOfWeek) {
                continue;
            }

            if (! $localStart->isSameDay($localEnd) && ! $localEnd->equalTo($localStart->addDay()->startOfDay())) {
                continue;
            }

            if ($row->effective_from !== null && $localStart->lessThan($row->effective_from)) {
                continue;
            }

            if ($row->effective_until !== null && $localStart->greaterThan($row->effective_until)) {
                continue;
            }

            $endTime = $localStart->isSameDay($localEnd) ? $localEnd->format('H:i:s') : '24:00:00';
            $rowEnd = self::endsAtMidnight($row->end_time) ? '24:00:00' : (string) $row->end_time;

            if ($row->start_time <= $localStart->format('H:i:s') && $rowEnd >= $endTime) {
                return true;
            }
        }

        return false;
    }

    /**
     * "Until midnight" as instructors can actually type it.
     *
     * The availability form is an HTML time input, which cannot express
     * 24:00, so an instructor who teaches to the end of the day enters
     * 23:59 — every window in production reads 00:00-23:59. Taken
     * literally that window is one minute short of midnight and the LAST
     * lesson of the day can never be generated (a 23:00-00:00 slot ends a
     * minute past it) or, if a student tries to book it, is rejected by
     * windowCovers(). Both call sites therefore treat 23:59 as the next
     * midnight. Only the final minute is affected; 23:58 stays literal.
     */
    public static function endsAtMidnight(string $endTime): bool
    {
        return str_starts_with($endTime, '23:59');
    }

    /**
     * TZ-1: the defensive fallback for a window row whose own
     * `timezone` column is somehow blank. Every row written through
     * InstructorAvailabilityService carries one, and the 2026_07_14
     * migration backfilled the rest, so this path should be
     * unreachable — but when it is reached it now resolves the
     * teacher's real timezone through the canonical chain
     * (profile -> Country -> platform -> UTC) instead of silently
     * treating the window as if it had been authored in UTC.
     *
     * Takes an already-loaded teacher only. Callers guard with
     * relationLoaded() where the row may be an unsaved clone, so this
     * never turns a bulk coverage check into an N+1.
     */
    private static function teacherTimezone(TeacherAvailability $row): string
    {
        $teacher = $row->teacher;

        return $teacher !== null
            ? UserTimezoneResolver::resolve($teacher)
            : UserTimezoneResolver::platformDefault();
    }

    public function hasBlackout(int $teacherId, CarbonImmutable $startsAt, CarbonImmutable $endsAt): bool
    {
        if ($this->servesFromCache($teacherId, $startsAt, $endsAt)) {
            $this->cachedBlackouts ??= $this->blackoutsFor($teacherId, $this->cachedFrom, $this->cachedTo);

            // Half-open, matching TeacherUnavailability::overlapping().
            return $this->cachedBlackouts->contains(
                static fn (TeacherUnavailability $blackout): bool => $blackout->starts_at->lessThan($endsAt)
                    && $blackout->ends_at->greaterThan($startsAt),
            );
        }

        return TeacherUnavailability::query()
            ->forTeacher($teacherId)
            ->overlapping($startsAt, $endsAt)
            ->exists();
    }

    public function blackoutsFor(int $teacherId, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return TeacherUnavailability::query()
            ->forTeacher($teacherId)
            ->overlapping($from, $to)
            ->get();
    }

    /**
     * TZ-2A — see AvailabilityRepositoryInterface for the contract.
     *
     * Reads the timezone off the instructor's own active availability
     * rules rather than jumping straight to their profile, because that
     * column is what already governs when their windows actually occur;
     * deriving the calendar day from a different source than the
     * windows would let a slot exist on one day and be capped on
     * another.
     *
     * Distinct timezones across a teacher's rows are not a supported
     * configuration (InstructorAvailabilityService writes one timezone
     * per mutation), so the earliest-created rule's timezone wins and
     * the outcome stays deterministic rather than depending on row
     * order. With no rules at all — a brand-new instructor — the
     * canonical user timezone answers it.
     */
    /**
     * Memo for calendarTimezoneFor(), scoped to THIS repository
     * instance — which is one availability pass (a wizard preview, one
     * generation run), never the application.
     *
     * Deliberately limited to the timezone. It is configuration, and a
     * stale read cannot let an unavailable slot through: windows,
     * holidays, leave, existing bookings and the daily cap are all still
     * queried live on every check. The instructor-approval check is
     * NOT memoized for exactly that reason — it is a safety gate, and
     * AvailabilityService re-runs it under the booking lock precisely so
     * a teacher deactivated mid-pass cannot still be booked.
     *
     * @var array<int, string>
     */
    private array $calendarTimezones = [];

    public function calendarTimezoneFor(int $teacherId): string
    {
        if (isset($this->calendarTimezones[$teacherId])) {
            return $this->calendarTimezones[$teacherId];
        }

        return $this->calendarTimezones[$teacherId] = $this->resolveCalendarTimezone($teacherId);
    }

    private function resolveCalendarTimezone(int $teacherId): string
    {
        $ruleTimezone = TeacherAvailability::query()
            ->active()
            ->forTeacher($teacherId)
            ->whereNotNull('timezone')
            ->orderBy('created_at')
            ->orderBy('id')
            ->value('timezone');

        if (IanaTimezone::isValid($ruleTimezone)) {
            return $ruleTimezone;
        }

        $teacher = User::query()->find($teacherId);

        return $teacher !== null
            ? UserTimezoneResolver::resolve($teacher)
            : UserTimezoneResolver::platformDefault();
    }

    public function isHoliday(CarbonImmutable $date, string $timezone): bool
    {
        // The holiday is a calendar date, so the comparison must happen
        // in the calendar that owns it. `$date` arrives as a UTC
        // instant; its UTC date is an artifact of storage and is a
        // different day from the instructor's for a large part of every
        // day in most of the world.
        $localDate = LocalDay::containing($date, $timezone)->date;

        if ($this->cachingReads && $this->cachedFrom !== null && $this->cachedTo !== null
            && ! $date->lessThan($this->cachedFrom) && ! $date->greaterThan($this->cachedTo)) {
            $this->cachedHolidayDates ??= $this->holidayDatesBetween($this->cachedFrom, $this->cachedTo);

            return $this->cachedHolidayDates->contains($localDate);
        }

        return Holiday::query()
            ->onDate($localDate)
            ->exists();
    }

    public function holidayDatesBetween(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Holiday::query()
            // Widened by a day either side: a UTC range's edges can sit
            // on a local date outside it (up to ~14h of offset spread),
            // and a holiday missing from this set would silently fail to
            // exclude its slots. Over-fetching is free — the caller
            // matches on exact local dates.
            ->between($from->subDay(), $to->addDay())
            ->pluck('date')
            ->map(fn ($date): string => $date->toDateString());
    }
}

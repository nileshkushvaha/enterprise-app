<?php

declare(strict_types=1);

namespace App\Booking\Services;

use App\Booking\Contracts\AvailabilityRepositoryInterface;
use App\Booking\Contracts\BookingRepositoryInterface;
use App\Booking\Contracts\BookingServiceInterface;
use App\Booking\Contracts\BookingTypeRepositoryInterface;
use App\Booking\Contracts\StudentBookingServiceInterface;
use App\Booking\Contracts\TeacherCandidateRepositoryInterface;
use App\Booking\DTOs\AssignmentCriteriaData;
use App\Booking\DTOs\CreateBookingData;
use App\Booking\DTOs\CreateBookingSeriesData;
use App\Booking\DTOs\RecurrenceData;
use App\Booking\DTOs\RecurringBookingResult;
use App\Booking\DTOs\StudentBookingData;
use App\Booking\Enums\BookingStatus;
use App\Booking\Enums\RecurrenceFrequency;
use App\Booking\Exceptions\BookingException;
use App\Booking\Types\FreeDemoType;
use App\Contracts\StudentFinancialVerificationGate;
use App\Models\Booking;
use App\Models\SubjectTopic;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Student flow = teacher choice + recurrence on top of the core
 * engine. Every occurrence goes through BookingService::request, so
 * window rules, duplicates, availability, buffer, daily caps, locks,
 * events, and notifications behave exactly like every other flow.
 */
final class StudentBookingService implements StudentBookingServiceInterface
{
    public function __construct(
        private readonly BookingServiceInterface $bookings,
        private readonly BookingRepositoryInterface $repository,
        private readonly BookingTypeRepositoryInterface $types,
        private readonly TeacherCandidateRepositoryInterface $teachers,
        private readonly StudentFinancialVerificationGate $financialVerification,
        private readonly DemoAvailabilityResolver $demoAvailability,
        private readonly AvailabilityRepositoryInterface $availabilityRules,
        private readonly BookingSeriesService $series,
    ) {}

    /**
     * An explicit free-demo lookup while the platform-wide feature is
     * unavailable must never imply teachers are bookable for one: return
     * an empty list rather than a misleading candidate set.
     * Paid (and any other) type lookups are unaffected. The booking type
     * itself being inactive is already rejected above by
     * requireActiveByKey() before this check is even reached.
     */
    public function availableTeachers(string $typeKey, string $subject, int $grade): Collection
    {
        $type = $this->types->requireActiveByKey($typeKey);

        if ($typeKey === FreeDemoType::KEY && ! $this->demoAvailability->isAvailable()) {
            return new Collection;
        }

        return $this->teachers->eligible(new AssignmentCriteriaData(
            typeKey: $typeKey,
            subject: $subject,
            grade: $grade,
            startsAt: CarbonImmutable::now()->addDay(),
            durationMinutes: $type->duration_minutes,
        ));
    }

    public function previousTeachers(User $student): Collection
    {
        return $this->repository->previousInstructorsForStudent($student->id);
    }

    public function upcomingClasses(User $student, ?int $limit = null): Collection
    {
        return $this->repository->upcomingForUser($student->id, $limit);
    }

    public function bookingHistory(User $student, int $perPage = 15, ?BookingStatus $status = null): LengthAwarePaginator
    {
        return $this->repository->paginatedForUser($student->id, $perPage, $status);
    }

    public function paymentHistory(User $student, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginatedPaymentsForUser($student->id, $perPage);
    }

    public function attendanceStats(User $student): object
    {
        return $this->repository->attendanceStatsForUser($student->id);
    }

    public function attendanceHistory(User $student, int $limit = 50): Collection
    {
        return $this->repository->attendanceHistoryForUser($student->id, $limit);
    }

    public function progressStats(User $student): object
    {
        return $this->repository->progressStatsForUser($student->id);
    }

    public function subjectBreakdown(User $student): Collection
    {
        return $this->repository->subjectBreakdownForUser($student->id);
    }

    public function bookingJourney(User $student): object
    {
        return $this->repository->studentBookingJourney($student->id);
    }

    public function book(StudentBookingData $data): Booking
    {
        return $this->bookOccurrence($data, $data->startsAt);
    }

    /**
     * The JSON API's recurring shape ("N occurrences, daily or weekly"),
     * now expressed as a stored SERIES rather than N loose bookings.
     *
     * The request/response contract is unchanged — same fields, same
     * `RecurringBookingResult`, same `meta.recurring_group` on every
     * booking (it is the series id) — but two things are now true that
     * were not:
     *
     *  - `occurrences` is no longer capped at twelve. A long series is
     *    reserved as far as the confirmation horizon reaches and
     *    generated forward from there, so the response's `booked` list
     *    may legitimately be shorter than `occurrences`; the remainder
     *    is scheduled, not lost.
     *  - The cadence is anchored to the INSTRUCTOR's calendar, matching
     *    the wizard and TZ-6's product decision, so a series no longer
     *    walks out of the instructor's own availability window when the
     *    two countries change their clocks on different dates.
     *
     * @throws BookingException
     */
    public function bookRecurring(StudentBookingData $data, RecurrenceData $recurrence): RecurringBookingResult
    {
        $type = $this->types->requireActiveByKey($data->typeKey);

        if (! $type->is_paid) {
            throw new BookingException('Recurring sessions are only available for paid booking types.');
        }

        $student = User::query()->findOrFail($data->studentId);
        $this->financialVerification->assertEligible($student, $type);
        $this->assertTeacherBookable($data);
        $topic = $this->resolveTopic($data);

        // The instructor's own scheduling clock — the same anchor the
        // wizard uses. See WizardBookingService::anchorRule().
        $recurrenceTimezone = $this->availabilityRules->calendarTimezoneFor($data->teacherId);

        return $this->series->create(new CreateBookingSeriesData(
            typeKey: $data->typeKey,
            studentId: $data->studentId,
            instructorId: $data->teacherId,
            rule: $recurrence->toRule($data->startsAt, $recurrenceTimezone),
            durationMinutes: (int) $type->duration_minutes,
            studentTimezone: $data->timezone,
            meta: array_filter([
                'subject' => $data->subject,
                'grade' => $data->grade,
                // Snapshot both: the slug mirrors meta.subject's style for
                // display/analytics; the id survives topic renames.
                'topic' => $topic?->slug,
                'topic_id' => $topic?->id,
            ], static fn (mixed $value): bool => $value !== null),
            notes: $data->notes,
            createdBy: $data->studentId,
        ));
    }

    /** @param array<string, mixed> $extraMeta */
    private function bookOccurrence(StudentBookingData $data, CarbonImmutable $startsAt, array $extraMeta = [], ?RecurrenceFrequency $recurrenceFrequency = null): Booking
    {
        $type = $this->types->requireActiveByKey($data->typeKey);

        $student = User::query()->findOrFail($data->studentId);
        $this->financialVerification->assertEligible($student, $type);

        $this->assertTeacherBookable($data);
        $topic = $this->resolveTopic($data);

        return $this->bookings->request(new CreateBookingData(
            typeKey: $data->typeKey,
            studentId: $data->studentId,
            instructorId: $data->teacherId,
            startsAt: $startsAt,
            durationMinutes: $type->duration_minutes,
            timezone: $data->timezone,
            notes: $data->notes,
            meta: array_filter([
                'subject' => $data->subject,
                'grade' => $data->grade,
                // Snapshot both: the slug mirrors meta.subject's style for
                // display/analytics; the id survives topic renames.
                'topic' => $topic?->slug,
                'topic_id' => $topic?->id,
                ...$extraMeta,
            ]),
            recurrenceFrequency: $recurrenceFrequency,
        ));
    }

    /**
     * Resolves and validates the optional topic selection: the topic
     * must be an active topic of the selected subject, and the chosen
     * teacher must hold active, admin-approved coverage for it at the
     * requested grade. Whole-subject rows never imply topic coverage.
     * No topic selected → subject-level rules alone apply.
     *
     * @throws BookingException
     */
    private function resolveTopic(StudentBookingData $data): ?SubjectTopic
    {
        if ($data->topic === null) {
            return null;
        }

        if ($data->subject === null) {
            throw new BookingException('A subject is required when selecting a topic.');
        }

        $topic = SubjectTopic::query()
            ->active()
            ->where('slug', $data->topic)
            ->whereHas('subject', fn ($q) => $q->availableForAssignment()->where('slug', $data->subject))
            ->first();

        if ($topic === null) {
            throw new BookingException('The selected topic is not available.');
        }

        if (! $this->teachers->teachesTopic($data->teacherId, $topic, $data->grade)) {
            throw new BookingException('This teacher does not teach the selected topic.');
        }

        return $topic;
    }

    private function assertTeacherBookable(StudentBookingData $data): void
    {
        if ($data->subject !== null && $data->grade !== null) {
            $eligible = $this->teachers->isEligible($data->teacherId, new AssignmentCriteriaData(
                typeKey: $data->typeKey,
                subject: $data->subject,
                grade: $data->grade,
                startsAt: $data->startsAt,
                durationMinutes: 0,
            ));

            if (! $eligible) {
                throw new BookingException('This teacher does not teach the selected subject and grade.');
            }

            return;
        }

        if (! $this->teachers->isApprovedTeacher($data->teacherId)) {
            throw new BookingException('This teacher is not currently accepting bookings.');
        }
    }
}

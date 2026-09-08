<?php

declare(strict_types=1);

namespace App\Booking\DTOs;

/**
 * Everything needed to create a recurring schedule AND to keep
 * generating it months later, when the wizard, the session and the
 * request that started it are long gone.
 *
 * That second requirement is why subject/grade/notes are carried here
 * rather than read from the student's profile at generation time: a
 * class booked in March must be the class the student agreed to in
 * March, not one re-derived from whatever their profile says in
 * October.
 */
final readonly class CreateBookingSeriesData
{
    public function __construct(
        public string $typeKey,
        public int $studentId,
        public int $instructorId,
        public RecurrenceRuleData $rule,
        public int $durationMinutes,
        /** The student's display timezone — provenance for each booking, never scheduling. */
        public string $studentTimezone,
        /**
         * The type-specific payload every generated class carries
         * (subject, grade, topic, …) — snapshotted here rather than
         * re-derived later, for the reason above.
         *
         * @var array<string, mixed>
         */
        public array $meta = [],
        public ?string $notes = null,
        public ?int $createdBy = null,
        /**
         * Dates the student removed while resolving conflicts, and
         * classes they moved to another time. Passed in at creation
         * rather than applied afterwards, because the very first
         * generation pass runs inside create() — recording them later
         * would mean booking a date the student had already dropped and
         * then cancelling it.
         *
         * @var list<string>
         */
        public array $skippedDates = [],
        /** @var array<string, string> `Y-m-d => H:i:s` in the series timezone */
        public array $timeOverrides = [],
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Booking\DTOs;

/**
 * Everything the student needs to decide whether a repeating schedule
 * is the one they meant — before anything is reserved or charged.
 *
 * `$totalScheduled` is null for an ongoing series and callers must
 * render that as "ongoing", never as zero and never as the page size:
 * an ongoing schedule genuinely has no total, and quoting one would
 * imply a commitment and a price that do not exist.
 */
final readonly class SeriesSchedulePreviewData
{
    /**
     * @param  list<SeriesOccurrenceData>  $occurrences  the current page, chronological
     * @param  int|null  $totalScheduled  null when the series is ongoing
     */
    public function __construct(
        public array $occurrences,
        public ?int $totalScheduled,
        public int $conflictCount,
        /** Classes that will be reserved immediately on confirmation. */
        public int $bookableNowCount,
        /** Classes inside the rule but beyond the confirmation horizon. */
        public int $plannedCount,
        public int $horizonDays,
        /** `Y-m-d` in the series timezone; null when ongoing. */
        public ?string $lastLocalDate,
        public bool $hasMore,
        /**
         * The instructor the whole schedule belongs to — resolved once
         * and reused for every occurrence. Exposed so the UI can offer
         * that instructor's other times when a class has to move, and
         * never anyone else's.
         */
        public ?int $instructorId = null,
        /** The schedule's own calendar; per-date deviations are keyed in it. */
        public ?string $timezone = null,
    ) {}

    public function hasConflicts(): bool
    {
        return $this->conflictCount > 0;
    }

    /** @return list<SeriesOccurrenceData> */
    public function conflicts(): array
    {
        return array_values(array_filter(
            $this->occurrences,
            static fn (SeriesOccurrenceData $occurrence): bool => $occurrence->isConflict(),
        ));
    }
}

<?php

declare(strict_types=1);

namespace App\Booking\DTOs;

use App\Booking\Enums\SeriesOccurrenceStatus;
use Carbon\CarbonImmutable;

/**
 * One date of a recurring schedule, with what the platform currently
 * knows about it: whether it is already a class, whether it can become
 * one, or why it cannot.
 *
 * Presentation-agnostic. The wizard, the review step, the API and the
 * background generator all reason about occurrences in this shape, so
 * a date can never be described one way in the preview and confirmed a
 * different way.
 */
final readonly class SeriesOccurrenceData
{
    public function __construct(
        public int $sequence,
        /** `Y-m-d` in the series' timezone. */
        public string $localDate,
        /** `Y-m-d H:i:s` as the series intends it, in the series' timezone. */
        public string $localDateTime,
        public ?CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt,
        public SeriesOccurrenceStatus $status,
        /** Student-facing explanation, present only for conflicts. */
        public ?string $reason = null,
        /** Set once this occurrence is a real booking. */
        public ?string $bookingId = null,
        public ?string $bookingReference = null,
    ) {}

    public function isConflict(): bool
    {
        return $this->status->isConflict();
    }

    public function isBookable(): bool
    {
        return $this->status->isBookable();
    }

    public function with(
        ?SeriesOccurrenceStatus $status = null,
        ?string $reason = null,
        ?string $bookingId = null,
        ?string $bookingReference = null,
    ): self {
        return new self(
            sequence: $this->sequence,
            localDate: $this->localDate,
            localDateTime: $this->localDateTime,
            startsAt: $this->startsAt,
            endsAt: $this->endsAt,
            status: $status ?? $this->status,
            reason: $reason ?? $this->reason,
            bookingId: $bookingId ?? $this->bookingId,
            bookingReference: $bookingReference ?? $this->bookingReference,
        );
    }

    /**
     * Rendered in the VIEWER's timezone, never the series'. Which clock
     * a date is shown in is a property of who is looking, not of the
     * record — see the Booking model's TZ-1 note.
     *
     * @return array<string, mixed>
     */
    public function toDisplayArray(string $viewerTimezone): array
    {
        $start = $this->startsAt?->setTimezone($viewerTimezone);
        $end = $this->endsAt?->setTimezone($viewerTimezone);

        return [
            'sequence' => $this->sequence,
            'local_date' => $this->localDate,
            'starts_at' => $this->startsAt?->toIso8601String(),
            'date_label' => $start?->format('D, j M Y')
                // A reading daylight saving deleted has no instant to
                // render, so the intended local date is shown instead of
                // silently displaying the moved-to time.
                ?? CarbonImmutable::parse($this->localDate)->format('D, j M Y'),
            'time_label' => $start?->format('g:i A'),
            'ends_label' => $end?->format('g:i A'),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_conflict' => $this->isConflict(),
            'reason' => $this->reason,
            'booking_id' => $this->bookingId,
            'booking_reference' => $this->bookingReference,
        ];
    }
}

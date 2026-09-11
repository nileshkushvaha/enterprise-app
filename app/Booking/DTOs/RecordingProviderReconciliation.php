<?php

declare(strict_types=1);

namespace App\Booking\DTOs;

use App\Models\Recording;

/**
 * Outcome of comparing a recording row's provider with the provider of
 * the meeting it belongs to (RecordingService::reconcileProvider()).
 *
 * A booking has ONE booking_meetings row, reused when a cancelled
 * meeting is replaced on another provider, and the recording row is
 * keyed on that meeting id. After such a replacement the recording can
 * still name the old provider, and the capture job selects its adapter
 * from the recording — so it would ask Google for a Zoom recording.
 */
final readonly class RecordingProviderReconciliation
{
    /** Row and meeting already agree; nothing to do. */
    public const string ALIGNED = 'aligned';

    /** Nothing had been captured under the old provider; the row now names the meeting's provider. */
    public const string REALIGNED = 'realigned';

    /** The row carries an in-flight or stored transfer, or the meeting is not live; left untouched. */
    public const string PROTECTED = 'protected';

    public function __construct(
        public string $decision,
        public Recording $recording,
        public ?string $meetingProvider,
        public ?string $previousProvider = null,
        public ?string $reason = null,
    ) {}

    /** The row can be captured through its own provider. */
    public function aligned(): bool
    {
        return $this->decision !== self::PROTECTED;
    }
}

<?php

declare(strict_types=1);

namespace App\Lessons\Services;

use App\Booking\Enums\MeetingAttendanceProcessingStatus;
use App\Lessons\Enums\LessonAttendanceStatus;
use App\Models\Lesson;
use App\Models\LessonAttendanceRecord;
use App\Models\MeetingAttendanceProviderEvent;

/**
 * Distinguishes "the evidence says a party was absent" from "there is
 * no evidence at all". The evidence-driven finalizer may only finalize
 * a no-show outcome in the first case; in the second the lesson is held
 * for a human decision instead of being punished for a missing feed.
 *
 * A lesson has evidence coverage when at least one of these holds:
 *  - a human recorded an attendance status for either party;
 *  - attendance events were recorded on its record (any source);
 *  - its meeting's provider attendance pull settled ("synced" — the
 *    provider answered, even with an empty participant list);
 *  - a provider attendance event for its meeting or lesson was
 *    processed (webhook or sync).
 *
 * Meetings whose provider cannot report attendance ("unsupported"), a
 * pull that never happened, or a pull that failed permanently provide
 * NO coverage: absence cannot be inferred from silence.
 */
final class LessonEvidenceCoverage
{
    public function hasCoverage(Lesson $lesson, ?LessonAttendanceRecord $record): bool
    {
        if ($lesson->student_attendance_status !== LessonAttendanceStatus::Pending
            || $lesson->instructor_attendance_status !== LessonAttendanceStatus::Pending) {
            return true;
        }

        if ($record !== null && $record->events()->exists()) {
            return true;
        }

        $meeting = $lesson->booking?->meeting;

        if ($meeting !== null && $meeting->attendance_sync_status === 'synced') {
            return true;
        }

        return MeetingAttendanceProviderEvent::query()
            ->where('processing_status', MeetingAttendanceProcessingStatus::Processed)
            ->where(function ($query) use ($lesson, $meeting): void {
                $query->where('lesson_id', $lesson->id);

                if ($meeting !== null) {
                    $query->orWhere('booking_meeting_id', $meeting->id);
                }
            })
            ->exists();
    }
}

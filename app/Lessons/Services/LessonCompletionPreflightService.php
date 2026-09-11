<?php

declare(strict_types=1);

namespace App\Lessons\Services;

use App\Booking\Contracts\MeetingAttendanceProviderInterface;
use App\Booking\Enums\MeetingStatus;
use App\Booking\Registry\MeetingProviderRegistry;
use App\Lessons\Contracts\LessonOutcomeServiceInterface;
use App\Lessons\Contracts\LessonRepositoryInterface;
use App\Lessons\DTOs\LessonCompletionPreflightReport;
use App\Lessons\Enums\LessonAttendanceStatus;
use App\Models\BookingMeeting;
use App\Models\Lesson;
use App\Settings\LessonSettings;
use App\Settings\MeetingSettings;
use Illuminate\Console\Scheduling\Schedule;

/**
 * READ-ONLY. Computes everything an operator needs before changing the
 * completion policy: the effective timing, which automation policy is
 * live, whether any enabled meeting provider can feed attendance
 * evidence, how recently ended meetings actually synced, and which
 * ended lessons are still open (and why automation would hold them).
 *
 * Blockers are the conditions under which switching
 * lessons.automated_finalization_enabled on would finalize lessons
 * from silence — the activation command refuses while any is present.
 */
final class LessonCompletionPreflightService
{
    private const int RECENT_DAYS = 7;

    private const int UPCOMING_DAYS = 7;

    private const int OLDEST_LISTED = 20;

    public function __construct(
        private readonly LessonSettings $lessons,
        private readonly MeetingSettings $meetings,
        private readonly MeetingProviderRegistry $registry,
        private readonly LessonRepositoryInterface $lessonRepository,
        private readonly LessonOutcomeServiceInterface $outcomes,
        private readonly LessonEvidenceCoverage $coverage,
        private readonly Schedule $schedule,
    ) {}

    public function report(): LessonCompletionPreflightReport
    {
        $blockers = [];
        $warnings = [];

        $timing = $this->timing();
        $providers = $this->providers();

        $attendanceCapable = array_values(array_filter($providers, fn (array $p): bool => $p['enabled'] && ($p['attendance_sync'] || $p['attendance_webhooks'])));

        if ($attendanceCapable === []) {
            $blockers[] = 'No enabled meeting provider can report attendance (none implements MeetingAttendanceProviderInterface). Evidence-based finalization would find no evidence for any lesson.';
        }

        if (! $this->meetings->attendance_sync_enabled && ! $this->meetings->attendance_webhooks_enabled) {
            $blockers[] = 'Both attendance ingestion switches are off (meeting.attendance_sync_enabled, meeting.attendance_webhooks_enabled). No evidence can arrive.';
        }

        if ($this->meetings->attendance_sync_delay_minutes >= $this->lessons->attendance_finalize_delay_minutes) {
            $blockers[] = sprintf(
                'meeting.attendance_sync_delay_minutes (%d) is not below lessons.attendance_finalize_delay_minutes (%d): the record would be sealed before the first attendance pull.',
                $this->meetings->attendance_sync_delay_minutes,
                $this->lessons->attendance_finalize_delay_minutes,
            );
        }

        if ($this->lessons->attendance_finalize_delay_minutes > $this->lessons->auto_complete_grace_minutes) {
            $warnings[] = sprintf(
                'lessons.attendance_finalize_delay_minutes (%d) exceeds lessons.auto_complete_grace_minutes (%d): completion cannot happen before the seal, so the effective completion delay is the seal delay.',
                $this->lessons->attendance_finalize_delay_minutes,
                $this->lessons->auto_complete_grace_minutes,
            );
        }

        if ($this->meetings->meeting_link_visible_after_minutes >= $this->lessons->auto_complete_grace_minutes) {
            $warnings[] = 'The join window closes at or after the completion delay: a lesson could be completed while joining is still open.';
        }

        [$upcoming, $mapped] = $this->participantMapCoverage();

        if ($attendanceCapable !== [] && $upcoming > 0 && $mapped === 0) {
            $blockers[] = sprintf('None of the %d upcoming created meetings carries an attendance participant map (metadata.attendance_participants); provider participants could not be mapped to the student or instructor.', $upcoming);
        }

        $recentSync = $this->recentMeetingSync();

        if (($recentSync['synced'] ?? 0) === 0 && array_sum($recentSync) > 0) {
            $warnings[] = sprintf('No meeting that ended in the last %d days has a settled attendance pull.', self::RECENT_DAYS);
        }

        [$openCounts, $oldest] = $this->openLessons();

        if (($openCounts['no_policy_will_finalize'] ?? 0) > 0) {
            $warnings[] = sprintf('%d ended lesson(s) are open and would be held by every automatic policy (technical issue, late evidence, required attendance missing, or no evidence coverage). They need a manual decision in the admin panel.', $openCounts['no_policy_will_finalize']);
        }

        return new LessonCompletionPreflightReport(
            activePolicy: $this->activePolicy(),
            evidenceFinalizationEnabled: $this->lessons->automated_finalization_enabled,
            timing: $timing,
            providers: $providers,
            attendanceSyncEnabled: $this->meetings->attendance_sync_enabled,
            attendanceWebhooksEnabled: $this->meetings->attendance_webhooks_enabled,
            upcomingMeetings: $upcoming,
            upcomingMeetingsWithParticipantMap: $mapped,
            recentMeetingSync: $recentSync,
            openLessons: $openCounts,
            oldestOpenLessons: $oldest,
            blockers: $blockers,
            warnings: $warnings,
        );
    }

    // ── Internals ─────────────────────────────────────────────────────────

    private function activePolicy(): string
    {
        return match (true) {
            $this->lessons->automated_finalization_enabled => 'evidence',
            $this->lessons->auto_complete_enabled => 'lenient',
            default => 'none',
        };
    }

    /** @return array<string, int|bool|string|null> */
    private function timing(): array
    {
        return [
            'join_opens_minutes_before_start' => $this->meetings->meeting_link_visible_before_minutes,
            'join_closes_minutes_after_end' => $this->meetings->meeting_link_visible_after_minutes,
            'completion_delay_minutes' => $this->lessons->auto_complete_grace_minutes,
            'attendance_seal_delay_minutes' => $this->lessons->attendance_finalize_delay_minutes,
            'attendance_sync_delay_minutes' => $this->meetings->attendance_sync_delay_minutes,
            'auto_complete_enabled' => $this->lessons->auto_complete_enabled,
            'require_instructor_completion' => $this->lessons->require_instructor_completion,
            'require_student_attendance' => $this->lessons->require_student_attendance,
            'min_attendance_seconds' => $this->lessons->min_attendance_seconds,
            'auto_complete_cron' => $this->cronFor('lessons:auto-complete'),
            'finalize_due_cron' => $this->cronFor('lessons:finalize-due'),
            'attendance_sync_cron' => $this->cronFor('meetings:sync-attendance'),
        ];
    }

    private function cronFor(string $command): ?string
    {
        foreach ($this->schedule->events() as $event) {
            if (str_contains((string) $event->command, $command)) {
                return $event->expression;
            }
        }

        return null;
    }

    /** @return array<int, array<string, mixed>> */
    private function providers(): array
    {
        $enabled = [
            'manual' => $this->meetings->manual_provider_enabled,
            'google_meet' => $this->meetings->google_meet_enabled,
            'zoom' => $this->meetings->zoom_enabled,
        ];

        $rows = [];

        foreach ($this->registry->all() as $key => $provider) {
            $attendance = $provider instanceof MeetingAttendanceProviderInterface;

            $rows[] = [
                'key' => $key,
                'default' => $key === $this->meetings->default_provider,
                'enabled' => $this->meetings->meetings_enabled
                    && (($enabled[$key] ?? false) || $key === $this->meetings->default_provider),
                'attendance_sync' => $attendance && $provider->supportsAttendanceSync(),
                'attendance_webhooks' => $attendance && $provider->supportsAttendanceWebhooks(),
            ];
        }

        return $rows;
    }

    /** @return array{0: int, 1: int} */
    private function participantMapCoverage(): array
    {
        $query = BookingMeeting::query()
            ->where('status', MeetingStatus::Created)
            ->whereBetween('starts_at', [now(), now()->addDays(self::UPCOMING_DAYS)]);

        return [
            (clone $query)->count(),
            (clone $query)->whereNotNull('metadata->attendance_participants')->count(),
        ];
    }

    /** @return array<string, int> */
    private function recentMeetingSync(): array
    {
        return BookingMeeting::query()
            ->where('status', MeetingStatus::Created)
            ->whereBetween('ends_at', [now()->subDays(self::RECENT_DAYS), now()])
            ->selectRaw("COALESCE(attendance_sync_status, 'never') AS sync_status, COUNT(*) AS total")
            ->groupBy('sync_status')
            ->pluck('total', 'sync_status')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * Open lessons past their end, bucketed by what automation would do
     * with them. Read-only: determine() is pure and coverage only reads.
     *
     * @return array{0: array<string, int>, 1: array<int, array<string, mixed>>}
     */
    private function openLessons(): array
    {
        $counts = [
            'total' => 0,
            'within_completion_delay' => 0,
            'due' => 0,
            'lenient_would_complete' => 0,
            'evidence_would_complete' => 0,
            'evidence_would_no_show' => 0,
            'no_policy_will_finalize' => 0,
        ];
        $oldest = [];
        $grace = max(0, $this->lessons->auto_complete_grace_minutes);

        foreach ($this->lessonRepository->openEndedBefore(now()) as $lesson) {
            /** @var Lesson $lesson */
            $counts['total']++;

            $due = now()->greaterThanOrEqualTo($lesson->ends_at->addMinutes($grace));
            $counts[$due ? 'due' : 'within_completion_delay']++;

            $record = $lesson->attendanceRecord;
            $held = $record?->technical_issue_reported_at !== null || $record?->late_evidence_reported_at !== null;
            $determination = $this->outcomes->determine($lesson);
            $covered = $this->coverage->hasCoverage($lesson, $record);

            $lenient = ! $held && $this->meetsLenientRequirements($lesson);
            $evidenceCompletes = ! $held && $determination->outcome->value === 'completed';
            $evidenceNoShow = ! $held && $determination->outcome->isNoShow() && $covered;

            if ($lenient) {
                $counts['lenient_would_complete']++;
            }
            if ($evidenceCompletes) {
                $counts['evidence_would_complete']++;
            }
            if ($evidenceNoShow) {
                $counts['evidence_would_no_show']++;
            }
            if (! $lenient && ! $evidenceCompletes && ! $evidenceNoShow) {
                $counts['no_policy_will_finalize']++;
            }

            if (count($oldest) < self::OLDEST_LISTED) {
                $oldest[] = [
                    'booking' => $lesson->booking?->reference ?? $lesson->booking_id,
                    'ended_at' => $lesson->ends_at->utc()->toDateTimeString().' UTC',
                    'evidence_outcome' => $determination->outcome->value,
                    'evidence_coverage' => $covered,
                    'held' => $held,
                    'lenient_completes' => $lenient,
                ];
            }
        }

        return [$counts, $oldest];
    }

    /** Mirrors LessonLifecycleService::meetsCompletionRequirements for the read-only report. */
    private function meetsLenientRequirements(Lesson $lesson): bool
    {
        if ($lesson->attendanceRecord?->technical_issue_reported_at !== null) {
            return false;
        }

        if ($this->lessons->require_instructor_completion && $lesson->instructor_attendance_status !== LessonAttendanceStatus::Attended) {
            return false;
        }

        return ! $this->lessons->require_student_attendance || $lesson->student_attendance_status === LessonAttendanceStatus::Attended;
    }
}

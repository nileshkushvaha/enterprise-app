<?php

declare(strict_types=1);

namespace App\Lessons\DTOs;

/**
 * Read-only snapshot rendered by lessons:completion-preflight and used
 * as the activation gate by lessons:evidence-finalization. Contains no
 * secrets — only setting values, counts, and booking references.
 *
 * @param  array<string, int|bool|string|null>  $timing
 * @param  array<int, array<string, mixed>>  $providers
 * @param  array<string, int>  $recentMeetingSync
 * @param  array<string, int>  $openLessons
 * @param  array<int, array<string, mixed>>  $oldestOpenLessons
 * @param  list<string>  $blockers
 * @param  list<string>  $warnings
 */
final readonly class LessonCompletionPreflightReport
{
    public function __construct(
        public string $activePolicy,
        public bool $evidenceFinalizationEnabled,
        public array $timing,
        public array $providers,
        public bool $attendanceSyncEnabled,
        public bool $attendanceWebhooksEnabled,
        public int $upcomingMeetings,
        public int $upcomingMeetingsWithParticipantMap,
        public array $recentMeetingSync,
        public array $openLessons,
        public array $oldestOpenLessons,
        public array $blockers,
        public array $warnings,
    ) {}

    public function hasBlockers(): bool
    {
        return $this->blockers !== [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'active_policy' => $this->activePolicy,
            'evidence_finalization_enabled' => $this->evidenceFinalizationEnabled,
            'timing' => $this->timing,
            'providers' => $this->providers,
            'attendance_sync_enabled' => $this->attendanceSyncEnabled,
            'attendance_webhooks_enabled' => $this->attendanceWebhooksEnabled,
            'upcoming_meetings' => $this->upcomingMeetings,
            'upcoming_meetings_with_participant_map' => $this->upcomingMeetingsWithParticipantMap,
            'recent_meeting_sync' => $this->recentMeetingSync,
            'open_lessons' => $this->openLessons,
            'oldest_open_lessons' => $this->oldestOpenLessons,
            'blockers' => $this->blockers,
            'warnings' => $this->warnings,
        ];
    }
}

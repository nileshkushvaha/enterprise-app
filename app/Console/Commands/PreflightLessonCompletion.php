<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Lessons\DTOs\LessonCompletionPreflightReport;
use App\Lessons\Services\LessonCompletionPreflightService;
use Illuminate\Console\Command;

/**
 * READ-ONLY report on the lesson timing and completion policy: effective
 * settings and cadence, the live automation policy, attendance-evidence
 * readiness, recently ended meetings' sync state, and ended lessons that
 * are still open. Writes nothing, prints no secrets. Exit code 1 when a
 * blocker for evidence-based finalization is present.
 */
final class PreflightLessonCompletion extends Command
{
    protected $signature = 'lessons:completion-preflight {--json : Machine-readable output}';

    protected $description = 'Read-only report: lesson timing, completion policy, attendance-evidence readiness and open ended lessons';

    public function handle(LessonCompletionPreflightService $preflight): int
    {
        $report = $preflight->report();

        if ($this->option('json')) {
            $this->line(json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $report->hasBlockers() ? self::FAILURE : self::SUCCESS;
        }

        $this->render($report);

        return $report->hasBlockers() ? self::FAILURE : self::SUCCESS;
    }

    private function render(LessonCompletionPreflightReport $report): void
    {
        $t = $report->timing;

        $this->components->twoColumnDetail('Active completion policy', match ($report->activePolicy) {
            'evidence' => '<fg=green>evidence-based (lessons:finalize-due)</>',
            'lenient' => '<fg=yellow>time-based (lessons:auto-complete)</>',
            default => '<fg=red>none — nothing completes automatically</>',
        });
        $this->components->twoColumnDetail('Join window', sprintf('opens %d min before start · closes %d min after end', $t['join_opens_minutes_before_start'], $t['join_closes_minutes_after_end']));
        $this->components->twoColumnDetail('Completion delay', sprintf('%d min after end · sweep cron %s / %s', $t['completion_delay_minutes'], $t['auto_complete_cron'] ?? '—', $t['finalize_due_cron'] ?? '—'));
        $this->components->twoColumnDetail('Attendance seal / first pull', sprintf('%d min / %d min after end · sync cron %s', $t['attendance_seal_delay_minutes'], $t['attendance_sync_delay_minutes'], $t['attendance_sync_cron'] ?? '—'));
        $this->components->twoColumnDetail('Completion requires attendance', sprintf('instructor %s · student %s · min %d s', $t['require_instructor_completion'] ? 'yes' : 'no', $t['require_student_attendance'] ? 'yes' : 'no', $t['min_attendance_seconds']));

        $this->newLine();
        $this->components->twoColumnDetail('Attendance ingestion switches', sprintf('sync %s · webhooks %s', $report->attendanceSyncEnabled ? '<fg=green>on</>' : '<fg=yellow>off</>', $report->attendanceWebhooksEnabled ? '<fg=green>on</>' : '<fg=yellow>off</>'));

        foreach ($report->providers as $p) {
            $this->components->twoColumnDetail(
                sprintf('Provider %s%s', $p['key'], $p['default'] ? ' (default)' : ''),
                sprintf('%s · attendance sync %s · webhooks %s', $p['enabled'] ? 'enabled' : 'disabled', $p['attendance_sync'] ? '<fg=green>yes</>' : '<fg=red>no</>', $p['attendance_webhooks'] ? '<fg=green>yes</>' : '<fg=red>no</>'),
            );
        }

        $this->components->twoColumnDetail('Upcoming created meetings with a participant map', sprintf('%d of %d', $report->upcomingMeetingsWithParticipantMap, $report->upcomingMeetings));
        $this->components->twoColumnDetail('Meetings ended in the last 7 days, by sync status', $report->recentMeetingSync === [] ? 'none' : collect($report->recentMeetingSync)->map(fn (int $n, string $s): string => "{$s} {$n}")->implode(' · '));

        $this->newLine();
        $o = $report->openLessons;
        $this->components->twoColumnDetail('Ended lessons still open', sprintf('%d (within delay %d · due %d)', $o['total'], $o['within_completion_delay'], $o['due']));
        $this->components->twoColumnDetail('  time-based policy would complete', (string) $o['lenient_would_complete']);
        $this->components->twoColumnDetail('  evidence policy would complete / no-show', sprintf('%d / %d', $o['evidence_would_complete'], $o['evidence_would_no_show']));
        $this->components->twoColumnDetail('  held by every policy (manual decision)', (string) $o['no_policy_will_finalize']);

        if ($report->oldestOpenLessons !== []) {
            $this->table(
                ['Booking', 'Ended (UTC)', 'Evidence outcome', 'Coverage', 'Held', 'Time-based completes'],
                array_map(fn (array $row): array => [
                    $row['booking'], $row['ended_at'], $row['evidence_outcome'],
                    $row['evidence_coverage'] ? 'yes' : 'no', $row['held'] ? 'yes' : 'no', $row['lenient_completes'] ? 'yes' : 'no',
                ], $report->oldestOpenLessons),
            );
        }

        foreach ($report->warnings as $warning) {
            $this->components->warn($warning);
        }

        foreach ($report->blockers as $blocker) {
            $this->components->error($blocker);
        }

        $this->newLine();
        $this->components->info($report->hasBlockers()
            ? 'Evidence-based finalization must NOT be activated: blockers above. Nothing was changed.'
            : 'No blockers for evidence-based finalization. Nothing was changed.');
    }
}

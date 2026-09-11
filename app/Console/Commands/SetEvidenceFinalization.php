<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Lessons\Services\LessonCompletionPreflightService;
use App\Services\AuditTrailService;
use App\Settings\LessonSettings;
use Illuminate\Console\Command;

/**
 * The audited activation step for evidence-based lesson finalization
 * (lessons.automated_finalization_enabled) — deliberately separate from
 * the settings migration that ships the timing values.
 *
 *  - `on`  runs the read-only preflight first and REFUSES while any
 *          blocker is present: with no attendance evidence, the finalizer
 *          would hold every lesson instead of completing it.
 *  - `off` is the rollback and is always allowed.
 *
 * Without --confirm nothing is written (dry run). Every change is
 * recorded in the settings audit log with previous and new values.
 */
final class SetEvidenceFinalization extends Command
{
    protected $signature = 'lessons:evidence-finalization {state : on|off} {--confirm : Actually change the setting}';

    protected $description = 'Activate (after preflight) or deactivate evidence-based lesson finalization, audited';

    public function handle(LessonSettings $settings, LessonCompletionPreflightService $preflight, AuditTrailService $audit): int
    {
        $state = strtolower((string) $this->argument('state'));

        if (! in_array($state, ['on', 'off'], true)) {
            $this->components->error('State must be "on" or "off".');

            return self::INVALID;
        }

        $target = $state === 'on';
        $current = $settings->automated_finalization_enabled;

        if ($current === $target) {
            $this->components->info(sprintf('Evidence-based finalization is already %s. Nothing to do.', $state));

            return self::SUCCESS;
        }

        if ($target) {
            $report = $preflight->report();

            foreach ($report->blockers as $blocker) {
                $this->components->error($blocker);
            }

            if ($report->hasBlockers()) {
                $this->components->error('Refusing to activate: fix the blockers above, then run lessons:completion-preflight again. Nothing was changed.');

                return self::FAILURE;
            }

            foreach ($report->warnings as $warning) {
                $this->components->warn($warning);
            }
        }

        $this->components->twoColumnDetail('lessons.automated_finalization_enabled', sprintf('%s → %s', $current ? 'true' : 'false', $target ? 'true' : 'false'));
        $this->components->twoColumnDetail('Effect', $target
            ? 'lessons:finalize-due finalizes from attendance evidence; lessons:auto-complete defers.'
            : 'lessons:auto-complete resumes time-based completion; lessons:finalize-due is a no-op.');

        if (! $this->option('confirm')) {
            $this->components->warn('Dry run — re-run with --confirm to apply.');

            return self::SUCCESS;
        }

        $settings->automated_finalization_enabled = $target;
        $settings->save();

        $audit->logSystem(
            'settings',
            $target ? 'lesson_evidence_finalization_activated' : 'lesson_evidence_finalization_deactivated',
            sprintf('Evidence-based lesson finalization switched %s via lessons:evidence-finalization.', $state),
            null,
            ['setting' => 'lessons.automated_finalization_enabled', 'previous' => $current, 'new' => $target],
        );

        $this->components->info(sprintf('Evidence-based finalization is now %s (audited).', $state));

        return self::SUCCESS;
    }
}

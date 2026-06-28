<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SchedulerHistory;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\CronTranslator\CronTranslator;
use Symfony\Component\Console\Output\BufferedOutput;

class SchedulerService
{
    public function __construct(private readonly Schedule $schedule) {}

    // ── Public API ────────────────────────────────────────────────────────

    /**
     * Return one row per scheduled event, merged with latest history.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getTasks(): Collection
    {
        $this->ensureScheduleLoaded();

        // Eager-load the most recent history record per command so we don't
        // issue N+1 queries when there are many tasks.
        $latestHistory = SchedulerHistory::query()
            ->whereIn('command', collect($this->schedule->events())->pluck('command')->filter())
            ->orderByDesc('ran_at')
            ->get()
            ->keyBy('command');

        return collect($this->schedule->events())->map(function (Event $event) use ($latestHistory) {
            $history = $latestHistory->get($event->command ?? '');

            return [
                'id' => sha1(($event->command ?? '').$event->expression),
                'command' => $event->command ?? '',
                'name' => $this->displayName($event),
                'description' => $event->description ?? '',
                'expression' => $event->expression,
                'frequency' => $this->describeExpression($event->expression),
                'next_run' => $this->safeNextRun($event),
                'last_run' => $history?->ran_at,
                'duration_ms' => $history?->duration_ms,
                'status' => $history?->status,
                'is_closure' => $event instanceof CallbackEvent,
                'mutex_locked' => $event->withoutOverlapping && $this->isMutexLocked($event),
                'without_overlap' => $event->withoutOverlapping,
            ];
        });
    }

    /**
     * Run a scheduled task immediately, record history, and log activity.
     */
    public function runNow(string $taskId): array
    {
        $this->ensureScheduleLoaded();

        $event = collect($this->schedule->events())
            ->first(fn (Event $e) => sha1(($e->command ?? '').$e->expression) === $taskId);

        if (! $event) {
            throw new \RuntimeException('Scheduled task not found.');
        }

        $start = microtime(true);
        $output = '';
        $status = 'success';

        try {
            if (! $event instanceof CallbackEvent && preg_match('/artisan\s+(.+)$/', $event->command ?? '', $m)) {
                $buffer = new BufferedOutput;
                $exitCode = Artisan::call(trim($m[1]), [], $buffer);
                $output = trim($buffer->fetch());
                $status = $exitCode === 0 ? 'success' : 'failed';
            } else {
                $event->run(app());
            }
        } catch (\Throwable $e) {
            $status = 'failed';
            $output = $e->getMessage();
        }

        $durationMs = (int) ((microtime(true) - $start) * 1000);

        SchedulerHistory::create([
            'command' => $event->command ?? 'closure',
            'triggered_by' => 'manual',
            'status' => $status,
            'duration_ms' => $durationMs,
            'output' => $output ?: null,
            'ran_at' => now(),
        ]);

        $user = auth()->user();
        $logger = activity('scheduler_monitor')
            ->withProperties([
                'task' => $this->displayName($event),
                'status' => $status,
                'duration_ms' => $durationMs,
            ]);

        if ($user) {
            $logger = $logger->causedBy($user);
        }

        $logger->log('Manually ran: '.$this->displayName($event));

        return ['status' => $status, 'duration_ms' => $durationMs, 'output' => $output];
    }

    /**
     * Return the last $limit history records across all tasks, newest first.
     *
     * @return Collection<int, SchedulerHistory>
     */
    public function getRecentHistory(int $limit = 20): Collection
    {
        return SchedulerHistory::query()
            ->orderByDesc('ran_at')
            ->limit($limit)
            ->get();
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Load routes/console.php when running in a web context where the console
     * kernel hasn't been booted (and the schedule therefore has no events yet).
     */
    private function ensureScheduleLoaded(): void
    {
        if (! empty($this->schedule->events())) {
            return;
        }

        $consolePath = base_path('routes/console.php');
        if (file_exists($consolePath)) {
            // require_once is intentional: prevents double-registration if this
            // method is called more than once in the same process.
            require_once $consolePath;
        }
    }

    private function displayName(Event $event): string
    {
        if ($event instanceof CallbackEvent) {
            return $event->description ?: 'Closure';
        }

        if ($event->description) {
            return $event->description;
        }

        $cmd = $event->command ?? '';

        if (preg_match('/artisan\s+(.+)$/', $cmd, $m)) {
            return trim($m[1]);
        }

        return $cmd;
    }

    private function describeExpression(string $expression): string
    {
        try {
            return CronTranslator::translate($expression);
        } catch (\Throwable) {
            return $expression;
        }
    }

    private function isMutexLocked(Event $event): bool
    {
        try {
            return Cache::has($event->mutexName());
        } catch (\Throwable) {
            return false;
        }
    }

    private function safeNextRun(Event $event): ?Carbon
    {
        try {
            return $event->nextRunDate();
        } catch (\Throwable) {
            return null;
        }
    }
}

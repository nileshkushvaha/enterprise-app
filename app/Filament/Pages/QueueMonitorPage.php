<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class QueueMonitorPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $navigationLabel = 'Queue Monitor';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'system/queue-monitor';

    protected string $view = 'filament.pages.queue-monitor';

    // ── Authorization ─────────────────────────────────────────────────────

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        try {
            return method_exists($user, 'hasPermissionTo')
                && $user->hasPermissionTo('queue_monitor.view');
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    // ── Page metadata ──────────────────────────────────────────────────────

    public function getTitle(): string|Htmlable
    {
        return 'Queue Monitor';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Queue driver status, pending jobs, and failed job history.';
    }

    public function getBreadcrumbs(): array
    {
        return [
            '/admin' => 'Dashboard',
            '/admin/system/queue-monitor' => 'System',
            '#' => 'Queue Monitor',
        ];
    }

    // ── Data ──────────────────────────────────────────────────────────────

    public function getQueueInfo(): array
    {
        $driver = config('queue.default', 'sync');
        $connection = config("queue.connections.{$driver}", []);

        return [
            'driver' => strtoupper($driver),
            'connection' => $connection['driver'] ?? $driver,
            'table' => $connection['table'] ?? ($driver === 'database' ? 'jobs' : null),
        ];
    }

    /** @return array<array{queue: string, pending: int, oldest_age: string|null}> */
    public function getQueueDepths(): array
    {
        $driver = config('queue.default', 'sync');

        if ($driver !== 'database') {
            return [];
        }

        $rows = DB::table('jobs')
            ->select('queue', DB::raw('COUNT(*) as pending'), DB::raw('MIN(created_at) as oldest'))
            ->groupBy('queue')
            ->orderByDesc('pending')
            ->get();

        return $rows->map(function ($row) {
            $oldest = $row->oldest ? Carbon::createFromTimestamp($row->oldest) : null;
            $ageMinutes = $oldest ? (int) $oldest->diffInMinutes(now()) : null;

            return [
                'queue' => $row->queue,
                'pending' => (int) $row->pending,
                'oldest_age' => $ageMinutes !== null ? $this->formatAge($ageMinutes) : null,
                'stalled' => $ageMinutes !== null && $ageMinutes > 5,
            ];
        })->all();
    }

    public function getFailedJobStats(): array
    {
        $driver = config('queue.default', 'sync');

        if ($driver !== 'database') {
            return ['count' => 0, 'byQueue' => [], 'recent' => []];
        }

        $count = DB::table('failed_jobs')->count();

        $byQueue = DB::table('failed_jobs')
            ->select('queue', DB::raw('COUNT(*) as total'))
            ->groupBy('queue')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['queue' => $r->queue, 'count' => (int) $r->total])
            ->all();

        $recent = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(5)
            ->get(['uuid', 'queue', 'connection', 'failed_at', 'exception'])
            ->map(function ($r) {
                $firstLine = str($r->exception)->before("\n")->limit(120)->toString();

                return [
                    'uuid' => $r->uuid,
                    'queue' => $r->queue,
                    'failed_at' => Carbon::parse($r->failed_at)->diffForHumans(),
                    'exception' => $firstLine,
                ];
            })
            ->all();

        return compact('count', 'byQueue', 'recent');
    }

    public function isWorkerLikelyRunning(): bool
    {
        $driver = config('queue.default', 'sync');

        if ($driver === 'sync') {
            return true;
        }

        if ($driver !== 'database') {
            return false;
        }

        // If there are jobs older than 5 minutes still pending, the worker is likely stalled/down.
        $stalledCount = DB::table('jobs')
            ->where('created_at', '<', now()->subMinutes(5)->timestamp)
            ->whereNull('reserved_at')
            ->count();

        // A stalled queue does not definitively mean the worker is down (rate-limited or slow job),
        // but it is the best heuristic available without a heartbeat table.
        return $stalledCount === 0;
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function formatAge(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes}m";
        }

        $hours = intdiv($minutes, 60);
        $rem = $minutes % 60;

        return $rem > 0 ? "{$hours}h {$rem}m" : "{$hours}h";
    }
}

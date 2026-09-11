<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Booking\Enums\RecordingStatus;
use App\Booking\Jobs\CaptureLessonRecordingJob;
use App\Booking\Storage\GoogleDriveRecordingStorage;
use App\Booking\Storage\RecordingStorageResolver;
use App\Models\Recording;
use App\Settings\MeetingSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * READ-ONLY operational readiness report for recording ingestion and
 * delivery — the checks the 2026-09-11 incident needed before the
 * first Zoom recording ran:
 *
 *  - storage: which backend is configured, whether its configuration
 *    is PRESENT, and (only with --probe) whether the delegated account
 *    can actually reach the destination folder — presence is never
 *    reported as proven access, and a provider's "Ready" is not this;
 *  - logging: the effective channel, file path(s) and level, and
 *    whether THIS process user can write the directory and today's
 *    files — run it as every user that logs (deploy, www-data);
 *  - queue: the recordings connection, its backlog and recent failed
 *    capture jobs;
 *  - recordings: failed rows by code, transfers stuck past the stale
 *    threshold, pending rows past their capture window;
 *  - join handoff: the cache store must be shared across web nodes.
 *
 * Prints no credential, token, locator or provider URL. Exit 1 when
 * something needs attention. Changes nothing, even with --probe.
 */
final class PreflightRecordings extends Command
{
    protected $signature = 'recordings:preflight {--probe : Also perform one read-only remote check of the storage destination} {--json : Machine-readable output}';

    protected $description = 'Read-only readiness report: recording storage, log destination and writability, recordings queue, failed/stalled recordings, join-handoff cache';

    /** @var list<string> */
    private array $problems = [];

    public function handle(MeetingSettings $settings, RecordingStorageResolver $storages): int
    {
        $report = [
            'storage' => $this->storage($settings, $storages),
            'logging' => $this->logging(),
            'queue' => $this->queue(),
            'recordings' => $this->recordings($settings),
            'join_handoff' => $this->joinHandoff($settings),
            'problems' => $this->problems,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->render($report);
        }

        return $this->problems === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<string, mixed> */
    private function storage(MeetingSettings $settings, RecordingStorageResolver $storages): array
    {
        $driver = (string) config('recordings.storage_driver', 'filesystem');
        $out = [
            'driver' => $driver,
            'capture_enabled' => $settings->recording_enabled,
            'zoom_recording_enabled' => $settings->zoom_recording_enabled,
            'google_meet_recording_enabled' => $settings->google_meet_recording_enabled,
            'drive_root_folder_id' => filled($settings->recording_drive_root_folder_id) ? 'present' : 'missing',
            'drive_shared_drive_id' => filled($settings->recording_drive_shared_drive_id) ? 'present' : 'none',
            'platform_meeting_account' => filled($settings->platform_meeting_account) ? 'present' : 'missing',
            'google_credentials' => $settings->google_credentials_configured ? 'present' : 'missing',
            'configured' => null,
            'remote_access' => 'not probed (pass --probe for one read-only metadata check)',
        ];

        try {
            $storage = $storages->default();
            $out['configured'] = $storage->isConfigured();
        } catch (Throwable $e) {
            $out['configured'] = false;
            $this->problems[] = 'Recording storage driver could not be resolved: '.$e->getMessage();

            return $out;
        }

        if ($out['configured'] !== true && ($settings->recording_enabled)) {
            $this->problems[] = sprintf('Recording capture is on but the "%s" storage backend is not configured. Ingestion fails closed with storage_not_configured.', $driver);
        }

        if ($this->option('probe') && $storage instanceof GoogleDriveRecordingStorage) {
            $probe = $storage->probeAccess();
            $out['remote_access'] = ($probe['ok'] ? 'PROVEN: ' : 'FAILED: ').$probe['detail'];

            if (! $probe['ok']) {
                $this->problems[] = 'Google Drive destination is not reachable by the delegated account: '.$probe['detail'];
            }
        } elseif ($this->option('probe')) {
            $out['remote_access'] = 'n/a for the '.$driver.' backend (local disk)';
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function logging(): array
    {
        $default = (string) config('logging.default');
        $channels = $default === 'stack'
            ? (array) config('logging.channels.stack.channels', [])
            : [$default];

        $files = [];

        foreach ($channels as $channel) {
            $cfg = (array) config("logging.channels.{$channel}", []);
            $path = $cfg['path'] ?? null;

            if (! is_string($path)) {
                $files[] = ['channel' => $channel, 'driver' => $cfg['driver'] ?? '?', 'level' => $cfg['level'] ?? '?', 'path' => null];

                continue;
            }

            $today = ($cfg['driver'] ?? null) === 'daily'
                ? preg_replace('/\.log$/', '-'.now()->format('Y-m-d').'.log', $path)
                : $path;

            $files[] = [
                'channel' => $channel,
                'driver' => $cfg['driver'] ?? '?',
                'level' => $cfg['level'] ?? '?',
                'path' => $today,
                'create_mode' => isset($cfg['permission']) ? sprintf('%04o', (int) $cfg['permission']) : 'default (0644)',
                ...$this->fileState((string) $today),
            ];
        }

        $dir = storage_path('logs');
        $unwritable = [];

        foreach (glob($dir.'/*.log') ?: [] as $existing) {
            if (! is_writable($existing)) {
                $unwritable[] = basename($existing);
            }
        }

        $out = [
            'process_user' => $this->processUser(),
            'directory' => $dir,
            'directory_writable' => is_writable($dir),
            'directory_setgid' => file_exists($dir) && (fileperms($dir) & 02000) === 02000,
            'channels' => $files,
            'existing_logs_not_writable_by_this_user' => $unwritable,
        ];

        if (! $out['directory_writable']) {
            $this->problems[] = sprintf('storage/logs is not writable by %s: every log write from this user throws.', $out['process_user']);
        }

        foreach ($files as $file) {
            if (($file['exists'] ?? false) && ! ($file['writable'] ?? true)) {
                $this->problems[] = sprintf('%s exists, is owned by %s (%s) and is NOT writable by %s — a log write from this user throws.', basename((string) $file['path']), $file['owner'], $file['mode'], $out['process_user']);
            }
        }

        if ($unwritable !== []) {
            $this->problems[] = sprintf('%d log file(s) in storage/logs are not writable by %s: %s', count($unwritable), $out['process_user'], implode(', ', array_slice($unwritable, 0, 8)));
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function fileState(string $path): array
    {
        if (! file_exists($path)) {
            return ['exists' => false, 'writable' => is_writable(dirname($path)), 'owner' => null, 'mode' => null];
        }

        $owner = function_exists('posix_getpwuid') ? (posix_getpwuid(fileowner($path))['name'] ?? (string) fileowner($path)) : (string) fileowner($path);
        $group = function_exists('posix_getgrgid') ? (posix_getgrgid(filegroup($path))['name'] ?? (string) filegroup($path)) : (string) filegroup($path);

        return [
            'exists' => true,
            'writable' => is_writable($path),
            'owner' => $owner.':'.$group,
            'mode' => sprintf('%04o', fileperms($path) & 0777),
        ];
    }

    private function processUser(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            return posix_getpwuid(posix_geteuid())['name'] ?? (string) posix_geteuid();
        }

        return get_current_user();
    }

    /** @return array<string, mixed> */
    private function queue(): array
    {
        $connection = (array) config('queue.connections.recordings', []);
        $out = [
            'connection_driver' => $connection['driver'] ?? 'missing',
            'retry_after' => $connection['retry_after'] ?? null,
            'job_timeout' => (new CaptureLessonRecordingJob(''))->timeout,
            'pending_jobs' => null,
            'oldest_pending_seconds' => null,
            'failed_capture_jobs_7d' => null,
        ];

        if (($connection['driver'] ?? null) !== 'database') {
            return $out;
        }

        if (($out['retry_after'] ?? 0) <= $out['job_timeout']) {
            $this->problems[] = 'recordings queue retry_after must exceed the capture job timeout, or a second worker will re-run a transfer still in flight.';
        }

        $table = $connection['table'] ?? 'jobs';

        if (Schema::hasTable($table)) {
            $out['pending_jobs'] = (int) DB::table($table)->where('queue', 'recordings')->count();
            $oldest = DB::table($table)->where('queue', 'recordings')->min('available_at');
            $out['oldest_pending_seconds'] = $oldest !== null ? max(0, now()->getTimestamp() - (int) $oldest) : null;

            if (($out['oldest_pending_seconds'] ?? 0) > 3600) {
                $this->problems[] = 'A recordings job has been waiting over an hour: the recordings queue worker (Supervisor program siri-recordings) is probably not running.';
            }
        }

        $failedTable = (string) config('queue.failed.table', 'failed_jobs');

        if (Schema::hasTable($failedTable)) {
            $out['failed_capture_jobs_7d'] = (int) DB::table($failedTable)
                ->where('failed_at', '>=', now()->subDays(7))
                ->where('payload', 'like', '%CaptureLessonRecordingJob%')
                ->count();

            if ($out['failed_capture_jobs_7d'] > 0) {
                $this->problems[] = sprintf('%d capture job(s) failed at the queue level in the last 7 days — the job threw instead of settling the row (see failed_jobs; rows are reclaimed by the stale-transfer sweep).', $out['failed_capture_jobs_7d']);
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function recordings(MeetingSettings $settings): array
    {
        $stale = max(1, $settings->recording_transfer_stale_minutes);
        $window = max(0, $settings->recording_capture_retry_minutes);

        $byCode = Recording::query()
            ->where('status', RecordingStatus::Failed)
            ->selectRaw("COALESCE(failure_code, 'unknown') AS code, COUNT(*) AS total")
            ->groupBy('code')
            ->pluck('total', 'code')
            ->map(fn ($n): int => (int) $n)
            ->all();

        $stuck = Recording::query()->stalledInTransfer($stale)->count();

        $overdue = Recording::query()
            ->where('status', RecordingStatus::Pending)
            ->whereHas('bookingMeeting', fn ($q) => $q->where('ends_at', '<', now()->subMinutes($window)))
            ->count();

        $preserved = Recording::query()
            ->where('status', RecordingStatus::Failed)
            ->whereNotNull('storage_path')
            ->count();

        if ($stuck > 0) {
            $this->problems[] = sprintf('%d recording(s) have been Transferring for more than %d minutes; recordings:capture reclaims them when the scheduler runs.', $stuck, $stale);
        }

        return [
            'failed_by_code' => $byCode,
            'failed_with_preserved_object' => $preserved,
            'transferring_past_stale_threshold' => $stuck,
            'pending_past_capture_window' => $overdue,
            'by_status' => Recording::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->map(fn ($n): int => (int) $n)->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function joinHandoff(MeetingSettings $settings): array
    {
        $store = (string) config('cache.default');
        $configured = filled($settings->participant_join_base_url);
        $shared = ! in_array($store, ['array', 'file', 'null'], true);

        if ($configured && ! $shared) {
            $this->problems[] = sprintf('A participant join domain is configured but the cache store is "%s", which is not shared across web nodes: handoff tokens issued on one node cannot be redeemed on another, and single-use cannot be guaranteed.', $store);
        }

        return [
            'join_domain_configured' => $configured,
            'cache_store' => $store,
            'cache_store_shared' => $shared,
            'session_driver' => (string) config('session.driver'),
        ];
    }

    /** @param array<string, mixed> $report */
    private function render(array $report): void
    {
        $s = $report['storage'];
        $this->components->twoColumnDetail('Storage backend', sprintf('%s · configured: %s', $s['driver'], $s['configured'] === true ? '<fg=green>yes</>' : '<fg=red>no</>'));
        $this->components->twoColumnDetail('  Drive folder / shared drive / account / credentials', sprintf('%s / %s / %s / %s', $s['drive_root_folder_id'], $s['drive_shared_drive_id'], $s['platform_meeting_account'], $s['google_credentials']));
        $this->components->twoColumnDetail('  Remote access', $s['remote_access']);
        $this->components->twoColumnDetail('  Capture switches', sprintf('record by default %s · Zoom %s · Google Meet %s', $s['capture_enabled'] ? 'on' : 'off', $s['zoom_recording_enabled'] ? 'on' : 'off', $s['google_meet_recording_enabled'] ? 'on' : 'off'));

        $this->newLine();
        $l = $report['logging'];
        $this->components->twoColumnDetail('Logging as user', $l['process_user']);
        $this->components->twoColumnDetail('  storage/logs writable / setgid', sprintf('%s / %s', $l['directory_writable'] ? 'yes' : '<fg=red>no</>', $l['directory_setgid'] ? 'yes' : 'no'));

        foreach ($l['channels'] as $c) {
            $this->components->twoColumnDetail(
                sprintf('  channel %s (%s, %s)', $c['channel'], $c['driver'], $c['level']),
                $c['path'] === null ? 'no file' : sprintf('%s · %s · %s%s', basename((string) $c['path']), ($c['exists'] ?? false) ? 'exists '.$c['owner'].' '.$c['mode'] : 'not created yet', ($c['writable'] ?? false) ? 'writable' : '<fg=red>NOT writable</>', isset($c['create_mode']) ? ' · created as '.$c['create_mode'] : ''),
            );
        }

        if ($l['existing_logs_not_writable_by_this_user'] !== []) {
            $this->components->twoColumnDetail('  Other logs not writable by this user', implode(', ', $l['existing_logs_not_writable_by_this_user']));
        }

        $this->newLine();
        $q = $report['queue'];
        $this->components->twoColumnDetail('Recordings queue', sprintf('%s · retry_after %s · job timeout %d', $q['connection_driver'], $q['retry_after'] ?? '—', $q['job_timeout']));
        $this->components->twoColumnDetail('  Pending jobs / oldest wait', sprintf('%s / %s', $q['pending_jobs'] ?? '—', $q['oldest_pending_seconds'] !== null ? $q['oldest_pending_seconds'].' s' : '—'));
        $this->components->twoColumnDetail('  Capture jobs failed at queue level (7d)', (string) ($q['failed_capture_jobs_7d'] ?? '—'));

        $this->newLine();
        $r = $report['recordings'];
        $this->components->twoColumnDetail('Recordings by status', $r['by_status'] === [] ? 'none' : collect($r['by_status'])->map(fn (int $n, string $k): string => "{$k} {$n}")->implode(' · '));
        $this->components->twoColumnDetail('  Failed by code', $r['failed_by_code'] === [] ? 'none' : collect($r['failed_by_code'])->map(fn (int $n, string $k): string => "{$k} {$n}")->implode(' · '));
        $this->components->twoColumnDetail('  Failed with a preserved object (operator recovery)', (string) $r['failed_with_preserved_object']);
        $this->components->twoColumnDetail('  Transferring past stale threshold', (string) $r['transferring_past_stale_threshold']);
        $this->components->twoColumnDetail('  Pending past capture window', (string) $r['pending_past_capture_window']);

        $this->newLine();
        $j = $report['join_handoff'];
        $this->components->twoColumnDetail('Join handoff', sprintf('domain %s · cache %s (%s) · session %s', $j['join_domain_configured'] ? 'configured' : 'not configured', $j['cache_store'], $j['cache_store_shared'] ? 'shared' : '<fg=yellow>per-process</>', $j['session_driver']));

        $this->newLine();

        foreach ($report['problems'] as $problem) {
            $this->components->error($problem);
        }

        $this->components->info($report['problems'] === [] ? 'No problems found. Nothing was changed.' : 'Attention needed (see above). Nothing was changed.');
    }
}
